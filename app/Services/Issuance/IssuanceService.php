<?php

declare(strict_types=1);

namespace App\Services\Issuance;

use App\Enums\AttemptStatus;
use App\Enums\ErrorClassification;
use App\Enums\PolicyState;
use App\Exceptions\IssuanceNotEligibleException;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Services\MockInsurer\MockInsurerClient;
use App\Services\MockInsurer\ProviderIssueResult;
use App\Services\Reconciliation\ReconciliationService;
use App\Services\StateMachine\PolicyStateMachine;
use Illuminate\Support\Facades\DB;

final class IssuanceService
{
    public function __construct(
        private readonly PolicyStateMachine $stateMachine,
        private readonly MockInsurerClient $mockInsurer,
        private readonly ReconciliationService $reconciliation,
    ) {}

    /**
     * Claim locally, call the mock insurer outside the DB transaction, then finalize.
     */
    public function submit(PolicyRequest $request): PolicyRequest
    {
        $claim = $this->claim($request);

        if ($claim['action'] === 'return') {
            return $claim['request'];
        }

        if ($claim['action'] === 'reconcile') {
            return $this->reconciliation->resolve($claim['request'], 'Reconcile before any new issuance');
        }

        /** @var IssuanceAttempt $attempt */
        $attempt = $claim['attempt'];
        $lockedRequest = $claim['request'];

        $result = $this->mockInsurer->issue(
            $this->providerPayload($lockedRequest),
            $lockedRequest->issuance_idempotency_key,
            $lockedRequest->mock_scenario,
        );

        return $this->finalize($lockedRequest->id, $attempt->id, $result);
    }

    /**
     * @return array{action: string, request: PolicyRequest, attempt?: IssuanceAttempt}
     */
    private function claim(PolicyRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            /** @var PolicyRequest $locked */
            $locked = PolicyRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->state === PolicyState::Issued) {
                return ['action' => 'return', 'request' => $locked->fresh(['stateHistory', 'attempts'])];
            }

            if (in_array($locked->state, [PolicyState::PendingConfirmation, PolicyState::NeedsReconciliation], true)) {
                return ['action' => 'reconcile', 'request' => $locked];
            }

            $inProgress = $locked->latestInProgressAttempt();
            if ($inProgress !== null) {
                if ($inProgress->started_at->greaterThan($locked->staleAttemptDeadline())) {
                    return ['action' => 'return', 'request' => $locked->fresh(['stateHistory', 'attempts'])];
                }

                $this->stateMachine->transition(
                    $locked,
                    PolicyState::NeedsReconciliation,
                    'In-progress attempt is stale; provider status must be checked',
                    $inProgress->id,
                );

                return ['action' => 'reconcile', 'request' => $locked];
            }

            if ($locked->state === PolicyState::Submitted) {
                return ['action' => 'reconcile', 'request' => $locked];
            }

            if ($locked->state === PolicyState::Failed && ! $locked->lastAttemptIsRetryableFailure()) {
                throw new IssuanceNotEligibleException('This request failed permanently and cannot be retried.');
            }

            if (! in_array($locked->state, [PolicyState::Validated, PolicyState::Failed], true)) {
                throw new IssuanceNotEligibleException("Request in {$locked->state->value} cannot be submitted.");
            }

            $attemptNumber = $locked->attempt_count + 1;
            $attempt = IssuanceAttempt::query()->create([
                'policy_request_id' => $locked->id,
                'attempt_number' => $attemptNumber,
                'idempotency_key' => $locked->issuance_idempotency_key,
                'status' => AttemptStatus::InProgress,
                'error_classification' => ErrorClassification::None,
                'request_summary' => $this->providerPayload($locked),
                'started_at' => now(),
            ]);

            $locked->attempt_count = $attemptNumber;
            $locked->last_error_code = null;
            $locked->last_error_message = null;
            $locked->save();

            $this->stateMachine->transition(
                $locked,
                PolicyState::Submitted,
                'Issuance attempt '.$attemptNumber.' claimed',
                $attempt->id,
            );

            return ['action' => 'issue', 'request' => $locked, 'attempt' => $attempt];
        });
    }

    private function finalize(int $requestId, int $attemptId, ProviderIssueResult $result): PolicyRequest
    {
        return DB::transaction(function () use ($requestId, $attemptId, $result) {
            /** @var PolicyRequest $locked */
            $locked = PolicyRequest::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();
            /** @var IssuanceAttempt $attempt */
            $attempt = IssuanceAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();

            $attempt->response_summary = $result->body;
            $attempt->provider_reference = $result->providerReference;
            $attempt->error_code = $result->errorCode;
            $attempt->error_message = $result->errorMessage;
            $attempt->completed_at = now();

            if ($locked->state === PolicyState::Issued) {
                $attempt->status = AttemptStatus::Succeeded;
                $attempt->error_classification = ErrorClassification::None;
                $attempt->save();

                return $locked->fresh(['stateHistory', 'attempts']);
            }

            if ($result->isSuccess() && $result->policyNumber) {
                $locked->provider_reference = $result->providerReference;
                $locked->policy_number = $result->policyNumber;
                $locked->last_provider_summary = 'Provider issued policy '.$result->policyNumber;
                $locked->last_error_code = null;
                $locked->last_error_message = null;
                $locked->save();

                $attempt->status = AttemptStatus::Succeeded;
                $attempt->error_classification = ErrorClassification::None;
                $attempt->save();

                $this->stateMachine->transition(
                    $locked,
                    PolicyState::Issued,
                    'Provider confirmed issuance',
                    $attempt->id,
                );

                return $locked->fresh(['stateHistory', 'attempts']);
            }

            if ($result->isAcceptedPending()) {
                $locked->provider_reference = $result->providerReference;
                $locked->last_provider_summary = 'Provider accepted; awaiting callback';
                $locked->save();

                $attempt->status = AttemptStatus::Accepted;
                $attempt->error_classification = ErrorClassification::Unknown;
                $attempt->save();

                $this->stateMachine->transition(
                    $locked,
                    PolicyState::PendingConfirmation,
                    'Provider accepted without a policy number',
                    $attempt->id,
                );

                return $locked->fresh(['stateHistory', 'attempts']);
            }

            if ($result->isRetryableFailure()) {
                $locked->last_error_code = $result->errorCode;
                $locked->last_error_message = $result->errorMessage;
                $locked->last_provider_summary = 'Retryable provider failure';
                $locked->save();

                $attempt->status = AttemptStatus::Failed;
                $attempt->error_classification = ErrorClassification::Retryable;
                $attempt->save();

                $this->stateMachine->transition(
                    $locked,
                    PolicyState::Failed,
                    'Temporary provider failure',
                    $attempt->id,
                );

                return $locked->fresh(['stateHistory', 'attempts']);
            }

            $locked->last_error_code = $result->errorCode ?? 'UNKNOWN_OUTCOME';
            $locked->last_error_message = $result->errorMessage ?? 'Provider outcome was not confirmed';
            $locked->last_provider_summary = 'Unknown provider outcome; do not re-issue blindly';
            $locked->save();

            $attempt->status = AttemptStatus::TimedOut;
            $attempt->error_classification = ErrorClassification::Unknown;
            $attempt->save();

            $this->stateMachine->transition(
                $locked,
                PolicyState::PendingConfirmation,
                'Provider response missing after issue call; reconciliation required',
                $attempt->id,
            );

            return $locked->fresh(['stateHistory', 'attempts']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function providerPayload(PolicyRequest $request): array
    {
        return [
            'policy_request_id' => $request->uuid,
            'plan_code' => $request->plan_code->value,
            'destination' => $request->destination->value,
            'trip_start' => $request->trip_start->toDateString(),
            'trip_end' => $request->trip_end->toDateString(),
            'sum_insured' => $request->sum_insured,
            'premium_amount' => $request->premium_amount,
            'currency' => $request->currency,
        ];
    }
}
