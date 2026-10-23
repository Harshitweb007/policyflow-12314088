<?php

declare(strict_types=1);

namespace App\Services\Callbacks;

use App\Enums\PolicyState;
use App\Enums\ProviderEventType;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\StateMachine\PolicyStateMachine;
use App\Support\UniqueConstraint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CallbackProcessor
{
    public function __construct(private readonly PolicyStateMachine $stateMachine) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{applied: bool, duplicate: bool, event: ProviderEvent, request: ?PolicyRequest}
     */
    public function process(array $payload): array
    {
        try {
            return DB::transaction(function () use ($payload) {
                $event = ProviderEvent::query()->create([
                    'provider_event_id' => $payload['provider_event_id'],
                    'provider_reference' => $payload['provider_reference'] ?? null,
                    'event_type' => $payload['event_type'],
                    'payload' => $payload,
                    'processing_outcome' => 'RECEIVED',
                    'event_occurred_at' => $payload['occurred_at'] ?? now(),
                    'received_at' => now(),
                ]);

                $request = $this->findRequest($payload);

                if ($request === null) {
                    $event->processing_outcome = 'UNKNOWN_REFERENCE';
                    $event->processed_at = now();
                    $event->save();

                    return ['applied' => false, 'duplicate' => false, 'event' => $event, 'request' => null];
                }

                $event->policy_request_id = $request->id;
                $event->save();

                /** @var PolicyRequest $locked */
                $locked = PolicyRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
                $type = ProviderEventType::from($payload['event_type']);

                if ($locked->state === PolicyState::Issued) {
                    $event->processing_outcome = 'IGNORED_STALE';
                    $event->processed_at = now();
                    $event->save();

                    return ['applied' => false, 'duplicate' => false, 'event' => $event, 'request' => $locked->fresh(['stateHistory'])];
                }

                if ($type === ProviderEventType::Issued) {
                    if (! $this->stateMachine->canTransition($locked->state, PolicyState::Issued)) {
                        $event->processing_outcome = 'INVALID_STATE';
                        $event->processed_at = now();
                        $event->save();

                        return ['applied' => false, 'duplicate' => false, 'event' => $event, 'request' => $locked->fresh(['stateHistory'])];
                    }

                    $policyNumber = $payload['policy_number'] ?? null;
                    if (! is_string($policyNumber) || $policyNumber === '') {
                        $event->processing_outcome = 'INVALID';
                        $event->processed_at = now();
                        $event->save();

                        return ['applied' => false, 'duplicate' => false, 'event' => $event, 'request' => $locked->fresh(['stateHistory'])];
                    }

                    $locked->provider_reference = $payload['provider_reference'] ?? $locked->provider_reference;
                    $locked->policy_number = $policyNumber;
                    $locked->last_provider_summary = 'Callback issued policy '.$policyNumber;
                    $locked->last_error_code = null;
                    $locked->last_error_message = null;
                    $locked->save();

                    $this->stateMachine->transition(
                        $locked,
                        PolicyState::Issued,
                        'Provider callback confirmed issuance',
                        eventId: $event->id,
                    );

                    $event->processing_outcome = 'APPLIED';
                    $event->processed_at = now();
                    $event->save();

                    return ['applied' => true, 'duplicate' => false, 'event' => $event, 'request' => $locked->fresh(['stateHistory'])];
                }

                if (in_array($type, [ProviderEventType::Failed, ProviderEventType::Rejected], true)) {
                    if (! $this->stateMachine->canTransition($locked->state, PolicyState::Failed)) {
                        $event->processing_outcome = 'INVALID_STATE';
                        $event->processed_at = now();
                        $event->save();

                        return ['applied' => false, 'duplicate' => false, 'event' => $event, 'request' => $locked->fresh(['stateHistory'])];
                    }

                    $locked->last_error_code = $payload['error_code'] ?? $type->value;
                    $locked->last_error_message = $payload['error_message'] ?? 'Provider reported failure';
                    $locked->last_provider_summary = 'Callback: '.$type->value;
                    $locked->save();

                    $this->stateMachine->transition(
                        $locked,
                        PolicyState::Failed,
                        'Provider callback reported '.$type->value,
                        eventId: $event->id,
                    );

                    $event->processing_outcome = 'APPLIED';
                    $event->processed_at = now();
                    $event->save();

                    return ['applied' => true, 'duplicate' => false, 'event' => $event, 'request' => $locked->fresh(['stateHistory'])];
                }

                $event->processing_outcome = 'IGNORED';
                $event->processed_at = now();
                $event->save();

                return ['applied' => false, 'duplicate' => false, 'event' => $event, 'request' => $locked->fresh(['stateHistory'])];
            });
        } catch (QueryException $exception) {
            if (! UniqueConstraint::isViolation($exception, 'provider_event_id')
                && ! UniqueConstraint::isViolation($exception, 'provider_events_provider_event_id_unique')) {
                throw $exception;
            }

            $existing = ProviderEvent::query()
                ->where('provider_event_id', $payload['provider_event_id'])
                ->firstOrFail();

            $request = $existing->policy_request_id
                ? PolicyRequest::query()->with('stateHistory')->find($existing->policy_request_id)
                : null;

            return ['applied' => false, 'duplicate' => true, 'event' => $existing, 'request' => $request];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findRequest(array $payload): ?PolicyRequest
    {
        if (! empty($payload['policy_request_id'])) {
            $idOrUuid = (string) $payload['policy_request_id'];
            $found = PolicyRequest::query()
                ->where('uuid', $idOrUuid)
                ->when(is_numeric($idOrUuid), fn ($q) => $q->orWhere('id', (int) $idOrUuid))
                ->first();
            if ($found !== null) {
                return $found;
            }
        }

        if (! empty($payload['provider_reference'])) {
            return PolicyRequest::query()->where('provider_reference', $payload['provider_reference'])->first();
        }

        return null;
    }
}
