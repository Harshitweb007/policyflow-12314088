<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PolicyState;
use App\Exceptions\IllegalStateTransitionException;
use App\Models\PolicyRequest;
use App\Services\StateMachine\PolicyStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IllegalTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_cannot_move_to_failed(): void
    {
        $request = PolicyRequest::factory()->create([
            'state' => PolicyState::Issued,
            'policy_number' => 'POL-LOCKED',
            'provider_reference' => 'PRV-LOCKED',
        ]);

        $this->expectException(IllegalStateTransitionException::class);
        app(PolicyStateMachine::class)->transition($request, PolicyState::Failed, 'should not happen');
    }
}
