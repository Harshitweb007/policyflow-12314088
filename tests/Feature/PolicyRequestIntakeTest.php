<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PolicyRequestIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_request_with_premium_snapshot_and_201(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->policyPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.state', 'VALIDATED')
            ->assertJsonPath('data.premium.amount', '225.00')
            ->assertJsonPath('data.premium.currency', 'INR')
            ->assertJsonPath('data.premium.travel_days', 5);

        $this->assertDatabaseCount('policy_requests', 1);
        $this->assertDatabaseHas('policy_state_history', ['to_state' => 'RECEIVED']);
        $this->assertDatabaseHas('policy_state_history', ['to_state' => 'VALIDATED']);
    }

    public function test_duplicate_intake_returns_200_and_same_record(): void
    {
        $first = $this->postJson('/api/policy-requests', $this->policyPayload())->assertCreated();
        $second = $this->postJson('/api/policy-requests', $this->policyPayload())->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame('Policy request already exists', $second->json('message'));
        $this->assertDatabaseCount('policy_requests', 1);
    }

    public function test_unique_constraint_rejects_a_second_row(): void
    {
        $this->postJson('/api/policy-requests', $this->policyPayload())->assertCreated();
        $existing = PolicyRequest::query()->firstOrFail();
        $attributes = $existing->getAttributes();
        unset($attributes['id']);
        $attributes['uuid'] = '11111111-1111-1111-1111-111111111111';
        $attributes['issuance_idempotency_key'] = 'issue:other';

        $this->expectException(QueryException::class);

        PolicyRequest::query()->create($attributes);
    }

    public function test_invalid_input_returns_422_with_field_errors(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->policyPayload([
            'plan_code' => 'GOLD',
            'sum_insured' => 250000,
            'trip_end' => '2026-11-01',
            'date_of_birth' => '2099-01-01',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['plan_code', 'sum_insured', 'trip_end', 'date_of_birth']]);
    }

    public function test_show_includes_state_history_and_premium_snapshot(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload())->json('data.id');

        $this->getJson('/api/policy-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.premium.amount', '225.00')
            ->assertJsonPath('data.state_history.0.to', 'RECEIVED')
            ->assertJsonPath('data.state_history.1.to', 'VALIDATED');
    }

    public function test_show_resolves_numeric_id_as_well_as_uuid(): void
    {
        $created = $this->postJson('/api/policy-requests', $this->policyPayload())->json('data.id');
        $model = PolicyRequest::query()->where('uuid', $created)->firstOrFail();

        $this->getJson('/api/policy-requests/'.$model->id)
            ->assertOk()
            ->assertJsonPath('data.id', $created)
            ->assertJsonPath('data.agent_id', 'agent-001');
    }

    public function test_intake_rejects_birth_date_before_1900(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->policyPayload([
            'date_of_birth' => '1890-05-15',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_intake_rejects_unsupported_destination(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->policyPayload([
            'destination' => 'INTERPLANETARY',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['destination']);
    }

    public function test_show_unknown_id_returns_404(): void
    {
        $this->getJson('/api/policy-requests/11111111-1111-1111-1111-111111111111')
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->getJson('/api/policy-requests/99999999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
