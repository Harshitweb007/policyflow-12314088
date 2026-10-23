<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PolicyRequestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagination_and_state_filter(): void
    {
        PolicyRequest::factory()->count(3)->create(['state' => PolicyState::Validated]);
        PolicyRequest::factory()->create(['state' => PolicyState::Issued, 'policy_number' => 'POL-LIST-1', 'provider_reference' => 'PRV-LIST-1']);

        $this->getJson('/api/policy-requests?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.current_page', 1);

        $filtered = $this->getJson('/api/policy-requests?state=ISSUED');
        $filtered->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame('ISSUED', $filtered->json('data.0.state'));
    }

    public function test_invalid_pagination_is_422(): void
    {
        $this->getJson('/api/policy-requests?per_page=1000&state=NOT_A_STATE')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
