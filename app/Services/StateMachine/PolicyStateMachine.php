<?php

declare(strict_types=1);

namespace App\Services\StateMachine;

use App\Enums\PolicyState;
use App\Exceptions\IllegalStateTransitionException;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;

final class PolicyStateMachine
{
    /**
     * Legal transitions. ISSUED has none — an issued policy never regresses.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        PolicyState::Received->value => [
            PolicyState::Validated->value,
            PolicyState::Failed->value,
        ],
        PolicyState::Validated->value => [
            PolicyState::Submitted->value,
            PolicyState::Failed->value,
        ],
        PolicyState::Submitted->value => [
            PolicyState::Issued->value,
            PolicyState::PendingConfirmation->value,
            PolicyState::Failed->value,
            PolicyState::NeedsReconciliation->value,
        ],
        PolicyState::PendingConfirmation->value => [
            PolicyState::Issued->value,
            PolicyState::Failed->value,
            PolicyState::NeedsReconciliation->value,
        ],
        PolicyState::NeedsReconciliation->value => [
            PolicyState::Issued->value,
            PolicyState::Failed->value,
            PolicyState::PendingConfirmation->value,
        ],
        PolicyState::Issued->value => [],
        PolicyState::Failed->value => [
            PolicyState::Submitted->value,
        ],
    ];

    public function canTransition(PolicyState $from, PolicyState $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function transition(
        PolicyRequest $request,
        PolicyState $to,
        string $reason,
        ?int $attemptId = null,
        ?int $eventId = null,
    ): void {
        $from = $request->state;

        if ($from === $to) {
            return;
        }

        if (! $this->canTransition($from, $to)) {
            throw new IllegalStateTransitionException($from, $to);
        }

        $request->state = $to;
        $request->save();

        PolicyStateHistory::query()->create([
            'policy_request_id' => $request->id,
            'from_state' => $from,
            'to_state' => $to,
            'reason' => $reason,
            'issuance_attempt_id' => $attemptId,
            'provider_event_id' => $eventId,
            'created_at' => now(),
        ]);
    }

    public function recordInitial(PolicyRequest $request, string $reason): void
    {
        PolicyStateHistory::query()->create([
            'policy_request_id' => $request->id,
            'from_state' => null,
            'to_state' => PolicyState::Received,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
