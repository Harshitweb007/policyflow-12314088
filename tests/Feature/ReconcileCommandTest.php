<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MockScenario;
use App\Models\MockInsurerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_recovers_timeout_after_issue(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload(), [
            'X-Mock-Scenario' => MockScenario::TimeoutAfterIssue->value,
        ])->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit")->assertStatus(202);

        $this->artisan('policyflow:reconcile')
            ->expectsOutputToContain('Checked: 1')
            ->expectsOutputToContain('Resolved (ISSUED): 1')
            ->assertSuccessful();

        $this->getJson("/api/policy-requests/{$id}")
            ->assertOk()
            ->assertJsonPath('data.state', 'ISSUED')
            ->assertJsonPath('data.policy_number', MockInsurerPolicy::query()->firstOrFail()->policy_number);

        $this->artisan('policyflow:reconcile')
            ->expectsOutputToContain('Checked: 0')
            ->assertSuccessful();
    }

    public function test_api_reconcile_endpoint_recovers_pending_policies(): void
    {
        $id = $this->postJson('/api/policy-requests', $this->policyPayload(), [
            'X-Mock-Scenario' => MockScenario::TimeoutAfterIssue->value,
        ])->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit")->assertStatus(202);

        $reconcile = $this->postJson('/api/reconcile');
        $reconcile->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checked', 1)
            ->assertJsonPath('data.resolved', 1)
            ->assertJsonPath('data.pending', 0);

        $this->getJson("/api/policy-requests/{$id}")
            ->assertOk()
            ->assertJsonPath('data.state', 'ISSUED');
    }
}
