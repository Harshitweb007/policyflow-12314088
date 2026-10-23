<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ErrorClassification;
use App\Enums\MockScenario;
use App\Enums\PolicyState;
use App\Models\IssuanceAttempt;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IssuanceReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_issuance_creates_one_policy_number(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload(), [
            'X-Mock-Scenario' => MockScenario::Success->value,
        ])->json('data.id');

        $submit = $this->postJson("/api/policy-requests/{$id}/submit");
        $submit->assertOk()
            ->assertJsonPath('data.state', 'ISSUED');

        $this->assertNotEmpty($submit->json('data.policy_number'));
        $this->assertDatabaseCount('mock_insurer_policies', 1);
        $this->assertDatabaseCount('issuance_attempts', 1);
    }

    public function test_repeated_submit_does_not_issue_another_policy(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload(), [
            'X-Mock-Scenario' => MockScenario::Success->value,
        ])->json('data.id');

        $first = $this->postJson("/api/policy-requests/{$id}/submit")->assertOk();
        $second = $this->postJson("/api/policy-requests/{$id}/submit")->assertOk();

        $this->assertSame($first->json('data.policy_number'), $second->json('data.policy_number'));
        $this->assertDatabaseCount('mock_insurer_policies', 1);
        $this->assertSame(1, MockInsurerPolicy::query()->count());
    }

    public function test_temporary_failure_is_recorded_and_retried_with_same_premium_and_key(): void
    {
        $create = $this->postJson('/api/policy-requests', $this->policyPayload(), [
            'X-Mock-Scenario' => MockScenario::TemporaryFailure->value,
        ])->assertCreated();
        $id = $create->json('data.id');
        $premium = $create->json('data.premium.amount');

        $first = $this->postJson("/api/policy-requests/{$id}/submit");
        $first->assertOk()->assertJsonPath('data.state', 'FAILED');
        $this->assertSame('RETRYABLE', PolicyRequest::query()->where('uuid', $id)->first()->attempts()->first()->error_classification->value);

        $second = $this->postJson("/api/policy-requests/{$id}/submit");
        $second->assertOk()->assertJsonPath('data.state', 'ISSUED');
        $this->assertSame($premium, $second->json('data.premium.amount'));
        $this->assertDatabaseCount('issuance_attempts', 2);
        $this->assertDatabaseCount('mock_insurer_policies', 1);
        $this->assertNotEmpty($second->json('data.policy_number'));
    }

    public function test_timeout_after_issue_is_reconciled_without_duplicate_provider_policy(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload(), [
            'X-Mock-Scenario' => MockScenario::TimeoutAfterIssue->value,
        ])->json('data.id');

        $first = $this->postJson("/api/policy-requests/{$id}/submit");
        $first->assertStatus(202)->assertJsonPath('data.state', 'PENDING_CONFIRMATION');
        $this->assertNull($first->json('data.policy_number'));

        $this->assertDatabaseCount('mock_insurer_policies', 1);
        $insurerPolicy = MockInsurerPolicy::query()->firstOrFail();
        $this->assertSame('ISSUED', $insurerPolicy->status);
        $this->assertNotEmpty($insurerPolicy->policy_number);

        $second = $this->postJson("/api/policy-requests/{$id}/submit");
        $second->assertOk()->assertJsonPath('data.state', 'ISSUED');
        $this->assertSame($insurerPolicy->policy_number, $second->json('data.policy_number'));
        $this->assertDatabaseCount('mock_insurer_policies', 1);
    }

    public function test_delayed_callback_then_duplicate_callback_is_idempotent(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload([
            'client_request_id' => 'delayed-1',
        ]), [
            'X-Mock-Scenario' => MockScenario::DelayedCallback->value,
        ])->json('data.id');

        $submit = $this->postJson("/api/policy-requests/{$id}/submit");
        $submit->assertStatus(202)->assertJsonPath('data.state', 'PENDING_CONFIRMATION');
        $providerReference = $submit->json('data.provider_reference');
        $this->assertNotEmpty($providerReference);

        $callback = [
            'provider_event_id' => 'evt-001',
            'provider_reference' => $providerReference,
            'policy_request_id' => $id,
            'event_type' => 'ISSUED',
            'policy_number' => 'POL-FROM-CALLBACK',
            'occurred_at' => '2026-10-23T12:00:00+00:00',
        ];

        $this->postJson('/api/mock-insurer/callback', $callback, $this->callbackHeaders($callback))
            ->assertOk()
            ->assertJsonPath('data.applied', true)
            ->assertJsonPath('data.policy_request.state', 'ISSUED');

        $duplicate = $this->postJson('/api/mock-insurer/callback', $callback, $this->callbackHeaders($callback));
        $duplicate->assertOk()
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.policy_request.state', 'ISSUED')
            ->assertJsonPath('data.policy_request.policy_number', 'POL-FROM-CALLBACK');

        $this->assertDatabaseCount('provider_events', 1);

        $stale = [
            'provider_event_id' => 'evt-002',
            'provider_reference' => $providerReference,
            'policy_request_id' => $id,
            'event_type' => 'FAILED',
            'error_code' => 'LATE_FAILURE',
            'occurred_at' => '2026-10-23T13:00:00+00:00',
        ];

        $this->postJson('/api/mock-insurer/callback', $stale, $this->callbackHeaders($stale))
            ->assertOk()
            ->assertJsonPath('data.processing_outcome', 'IGNORED_STALE');

        $this->getJson("/api/policy-requests/{$id}")
            ->assertOk()
            ->assertJsonPath('data.state', 'ISSUED')
            ->assertJsonPath('data.policy_number', 'POL-FROM-CALLBACK');
    }

    public function test_invalid_callback_does_not_change_state(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload([
            'client_request_id' => 'cb-invalid',
        ]))->json('data.id');

        $this->postJson('/api/mock-insurer/callback', [
            'provider_event_id' => 'evt-bad',
        ], $this->callbackHeaders(['provider_event_id' => 'evt-bad']))
            ->assertStatus(422);

        $this->getJson("/api/policy-requests/{$id}")
            ->assertOk()
            ->assertJsonPath('data.state', 'VALIDATED');
    }

    public function test_unknown_callback_reference_is_404(): void
    {
        $payload = [
            'provider_event_id' => 'evt-unknown',
            'provider_reference' => 'PRV-DOES-NOT-EXIST',
            'event_type' => 'ISSUED',
            'policy_number' => 'POL-X',
        ];

        $this->postJson('/api/mock-insurer/callback', $payload, $this->callbackHeaders($payload))
            ->assertNotFound();
    }

    public function test_unsigned_callback_is_rejected(): void
    {
        $this->postJson('/api/mock-insurer/callback', [
            'provider_event_id' => 'evt-unsigned',
            'event_type' => 'ISSUED',
            'policy_number' => 'POL-X',
        ])->assertStatus(401);
    }

    public function test_submit_returns_409_when_request_is_not_eligible(): void
    {
        $request = PolicyRequest::factory()->create(['state' => PolicyState::Received]);

        $this->postJson('/api/policy-requests/'.$request->uuid.'/submit')
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_concurrent_submit_while_in_progress_returns_existing_without_second_attempt(): void
    {
        $request = PolicyRequest::factory()->create(['state' => PolicyState::Submitted, 'attempt_count' => 1]);

        IssuanceAttempt::query()->create([
            'policy_request_id' => $request->id,
            'attempt_number' => 1,
            'idempotency_key' => $request->issuance_idempotency_key,
            'status' => AttemptStatus::InProgress,
            'error_classification' => ErrorClassification::None,
            'started_at' => now()->subSeconds(5),
        ]);

        $response = $this->postJson("/api/policy-requests/{$request->uuid}/submit");
        $response->assertOk()
            ->assertJsonPath('data.state', 'SUBMITTED');

        $this->assertDatabaseCount('issuance_attempts', 1);
        $this->assertDatabaseCount('mock_insurer_policies', 0);
    }

    public function test_stale_in_progress_attempt_is_reconciled(): void
    {
        $request = PolicyRequest::factory()->create([
            'state' => PolicyState::Submitted,
            'attempt_count' => 1,
            'mock_scenario' => MockScenario::Success,
        ]);

        IssuanceAttempt::query()->create([
            'policy_request_id' => $request->id,
            'attempt_number' => 1,
            'idempotency_key' => $request->issuance_idempotency_key,
            'status' => AttemptStatus::InProgress,
            'error_classification' => ErrorClassification::None,
            'started_at' => now()->subSeconds(45), // older than 30s threshold
        ]);

        // Insurer policy was created previously
        MockInsurerPolicy::query()->create([
            'idempotency_key' => $request->issuance_idempotency_key,
            'provider_reference' => 'PRV-RECON-1',
            'policy_number' => 'POL-RECON-1',
            'policy_request_uuid' => $request->uuid,
            'status' => 'ISSUED',
            'scenario' => MockScenario::Success->value,
            'issue_call_count' => 1,
        ]);

        $response = $this->postJson("/api/policy-requests/{$request->uuid}/submit");
        $response->assertOk()
            ->assertJsonPath('data.state', 'ISSUED')
            ->assertJsonPath('data.policy_number', 'POL-RECON-1');

        $this->assertDatabaseCount('mock_insurer_policies', 1);
    }

    public function test_permanent_failure_cannot_be_retried(): void
    {
        $request = PolicyRequest::factory()->create([
            'state' => PolicyState::Failed,
            'attempt_count' => 1,
        ]);

        IssuanceAttempt::query()->create([
            'policy_request_id' => $request->id,
            'attempt_number' => 1,
            'idempotency_key' => $request->issuance_idempotency_key,
            'status' => AttemptStatus::Failed,
            'error_classification' => ErrorClassification::Definitive,
            'error_code' => 'CUSTOMER_SANCTIONED',
            'error_message' => 'Customer is on a sanctions list',
            'started_at' => now()->subMinutes(1),
            'completed_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson("/api/policy-requests/{$request->uuid}/submit");
        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This request failed permanently and cannot be retried.');
    }

    public function test_unsolicited_callback_on_validated_state_is_rejected_as_invalid_state_409(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload([
            'client_request_id' => 'cb-validated-state',
        ]))->json('data.id');

        $callback = [
            'provider_event_id' => 'evt-early',
            'policy_request_id' => $id,
            'event_type' => 'ISSUED',
            'policy_number' => 'POL-EARLY',
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->postJson('/api/mock-insurer/callback', $callback, $this->callbackHeaders($callback))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Callback cannot be applied to policy request in its current state');

        $this->getJson("/api/policy-requests/{$id}")
            ->assertOk()
            ->assertJsonPath('data.state', 'VALIDATED');
    }
}
