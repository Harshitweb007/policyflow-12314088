<?php

declare(strict_types=1);

namespace App\Services\Reconciliation;

use App\Enums\AttemptStatus;
use App\Enums\ErrorClassification;
use App\Enums\PolicyState;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Services\MockInsurer\MockInsurerClient;
use App\Services\StateMachine\PolicyStateMachine;
use Illuminate\Support\Facades\DB;

final class ReconciliationService
{
    public function __construct(
        private readonly MockInsurerClient $mockInsurer,
        private readonly PolicyStateMachine $stateMachine,
    ) {}

    /**
     * Look up the mock insurer by the stable issuance key before any new issue call.
     */
    public function resolve(PolicyRequest $request, string $reason = 'Provider status lookup'): PolicyRequest
    {
        $lookup = $this->mockInsurer->lookupByIdempotencyKey($request->issuance_idempotency_key);

        return DB::transaction(function () use ($request, $lookup, $reason) {
            /** @var PolicyRequest $locked */
            $locked = PolicyRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->state === PolicyState::Issued) {
                return $locked->fresh(['stateHistory', 'attempts']);
            }

            if ($lookup !== null && ($lookup['policy_number'] ?? null) && ($lookup['outcome'] ?? '') === 'SUCCESS') {
                $locked->provider_reference = $lookup['provider_reference'] ?? $locked->provider_reference;
                $locked->policy_number = $lookup['policy_number'];
                $locked->last_error_code = null;
                $locked->last_error_message = null;
                $locked->last_provider_summary = 'Recovered issued policy from provider lookup';
                $locked->save();

                $this->stateMachine->transition(
                    $locked,
                    PolicyState::Issued,
                    $reason.': existing policy '.$locked->policy_number.' recovered',
                );

                $this->completeOpenAttempts($locked, AttemptStatus::Succeeded, ErrorClassification::None, $lookup);

                return $locked->fresh(['stateHistory', 'attempts']);
            }

            if ($lookup !== null && ($lookup['outcome'] ?? '') === 'ACCEPTED') {
                $locked->provider_reference = $lookup['provider_reference'] ?? $locked->provider_reference;
                $locked->last_provider_summary = 'Provider still pending confirmation';
                $locked->save();

                if ($locked->state === PolicyState::NeedsReconciliation) {
                    $this->stateMachine->transition(
                        $locked,
                        PolicyState::PendingConfirmation,
                        $reason.': provider still pending',
                    );
                }

                return $locked->fresh(['stateHistory', 'attempts']);
            }

            if ($lookup !== null && ($lookup['outcome'] ?? '') === 'TEMPORARY_FAILURE') {
                $locked->last_error_code = $lookup['error_code'] ?? 'TEMPORARY_UNAVAILABLE';
                $locked->last_error_message = $lookup['error_message'] ?? 'Temporary failure';
                $locked->last_provider_summary = 'Provider lookup: retryable failure';
                $locked->save();
                $this->stateMachine->transition($locked, PolicyState::Failed, $reason.': retryable provider failure');

                return $locked->fresh(['stateHistory', 'attempts']);
            }

            if ($lookup === null) {
                $locked->last_provider_summary = 'Provider lookup found no policy';
                $locked->save();

                if ($locked->state !== PolicyState::NeedsReconciliation) {
                    $this->stateMachine->transition(
                        $locked,
                        PolicyState::NeedsReconciliation,
                        $reason.': no provider policy found',
                    );
                }

                return $locked->fresh(['stateHistory', 'attempts']);
            }

            if ($locked->state !== PolicyState::NeedsReconciliation && $locked->state !== PolicyState::PendingConfirmation) {
                $this->stateMachine->transition(
                    $locked,
                    PolicyState::NeedsReconciliation,
                    $reason.': ambiguous provider status',
                );
            }

            return $locked->fresh(['stateHistory', 'attempts']);
        });
    }

    /**
     * @param  array<string, mixed>  $lookup
     */
    private function completeOpenAttempts(
        PolicyRequest $request,
        AttemptStatus $status,
        ErrorClassification $classification,
        array $lookup,
    ): void {
        IssuanceAttempt::query()
            ->where('policy_request_id', $request->id)
            ->where('status', AttemptStatus::InProgress->value)
            ->get()
            ->each(function (IssuanceAttempt $attempt) use ($status, $classification, $lookup): void {
                $attempt->status = $status;
                $attempt->error_classification = $classification;
                $attempt->provider_reference = $lookup['provider_reference'] ?? null;
                $attempt->response_summary = $lookup;
                $attempt->completed_at = now();
                $attempt->save();
            });
    }
}
