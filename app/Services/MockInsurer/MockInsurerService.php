<?php

declare(strict_types=1);

namespace App\Services\MockInsurer;

use App\Enums\MockScenario;
use App\Models\MockInsurerPolicy;
use Illuminate\Support\Str;

final class MockInsurerService
{
    /**
     * Independently persist mock insurer state. Repeating the same idempotency key
     * never creates a second policy number.
     *
     * TEMPORARY_FAILURE is not a completed operation: the first call for a key
     * fails without issuing; the next call issues successfully. That keeps the
     * key stable while still allowing a documented retry-after-transient-failure.
     */
    public function issue(array $payload, string $idempotencyKey, MockScenario $scenario): array
    {
        $existing = MockInsurerPolicy::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null && in_array($existing->status, ['ISSUED', 'PENDING'], true)) {
            return $this->present($existing, $scenario, alreadyExisted: true);
        }

        if ($scenario === MockScenario::TemporaryFailure && ($existing === null || $existing->status === 'FAILED')) {
            if ($existing === null) {
                MockInsurerPolicy::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'provider_reference' => $this->providerReference(),
                    'policy_number' => null,
                    'policy_request_uuid' => (string) ($payload['policy_request_id'] ?? ''),
                    'status' => 'FAILED',
                    'scenario' => $scenario->value,
                    'issue_call_count' => 1,
                    'request_payload' => $payload,
                ]);

                return [
                    'outcome' => 'TEMPORARY_FAILURE',
                    'http_status' => 503,
                    'error_code' => 'TEMPORARY_UNAVAILABLE',
                    'error_message' => 'Mock insurer temporarily unavailable.',
                    'provider_reference' => null,
                    'policy_number' => null,
                    'already_existed' => false,
                ];
            }

            $existing->update([
                'status' => 'ISSUED',
                'policy_number' => $this->policyNumber(),
                'issue_call_count' => $existing->issue_call_count + 1,
                'scenario' => $scenario->value,
            ]);

            return $this->present($existing->fresh(), $scenario, alreadyExisted: false);
        }

        $policy = $existing ?? MockInsurerPolicy::query()->create([
            'idempotency_key' => $idempotencyKey,
            'provider_reference' => $this->providerReference(),
            'policy_number' => $scenario === MockScenario::DelayedCallback ? null : $this->policyNumber(),
            'policy_request_uuid' => (string) ($payload['policy_request_id'] ?? ''),
            'status' => $scenario === MockScenario::DelayedCallback ? 'PENDING' : 'ISSUED',
            'scenario' => $scenario->value,
            'issue_call_count' => 1,
            'request_payload' => $payload,
        ]);

        if ($existing !== null) {
            $policy->increment('issue_call_count');
            $policy->refresh();
        }

        return $this->present($policy, $scenario, alreadyExisted: $existing !== null);
    }

    public function lookupByIdempotencyKey(string $idempotencyKey): ?array
    {
        $policy = MockInsurerPolicy::query()->where('idempotency_key', $idempotencyKey)->first();

        return $policy === null ? null : $this->present($policy, MockScenario::from($policy->scenario), alreadyExisted: true);
    }

    public function lookupByProviderReference(string $providerReference): ?array
    {
        $policy = MockInsurerPolicy::query()->where('provider_reference', $providerReference)->first();

        return $policy === null ? null : $this->present($policy, MockScenario::from($policy->scenario), alreadyExisted: true);
    }

    public function confirmPending(string $providerReference): ?array
    {
        $policy = MockInsurerPolicy::query()->where('provider_reference', $providerReference)->first();

        if ($policy === null) {
            return null;
        }

        if ($policy->status === 'PENDING' && $policy->policy_number === null) {
            $policy->update([
                'status' => 'ISSUED',
                'policy_number' => $this->policyNumber(),
            ]);
            $policy->refresh();
        }

        return $this->present($policy, MockScenario::from($policy->scenario), alreadyExisted: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MockInsurerPolicy $policy, MockScenario $scenario, bool $alreadyExisted): array
    {
        $timeout = $scenario === MockScenario::TimeoutAfterIssue && $policy->status === 'ISSUED' && ! $alreadyExisted;

        if ($timeout) {
            return [
                'outcome' => 'UNKNOWN',
                'http_status' => 504,
                'error_code' => 'GATEWAY_TIMEOUT',
                'error_message' => 'Mock insurer issued a policy but the response was lost.',
                'provider_reference' => null,
                'policy_number' => null,
                'already_existed' => false,
                'hidden_issued' => true,
            ];
        }

        if ($policy->status === 'PENDING') {
            return [
                'outcome' => 'ACCEPTED',
                'http_status' => 202,
                'error_code' => null,
                'error_message' => null,
                'provider_reference' => $policy->provider_reference,
                'policy_number' => null,
                'already_existed' => $alreadyExisted,
            ];
        }

        return [
            'outcome' => 'SUCCESS',
            'http_status' => 200,
            'error_code' => null,
            'error_message' => null,
            'provider_reference' => $policy->provider_reference,
            'policy_number' => $policy->policy_number,
            'already_existed' => $alreadyExisted,
        ];
    }

    private function providerReference(): string
    {
        return 'PRV-'.strtoupper(Str::random(12));
    }

    private function policyNumber(): string
    {
        return 'POL-'.strtoupper(Str::random(10));
    }
}
