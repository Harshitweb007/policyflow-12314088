<?php

declare(strict_types=1);

namespace App\Services\PolicyRequests;

use App\Enums\Destination;
use App\Enums\MockScenario;
use App\Enums\PlanCode;
use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Services\Pricing\PremiumCalculator;
use App\Services\StateMachine\PolicyStateMachine;
use App\Support\UniqueConstraint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PolicyRequestIntakeService
{
    public function __construct(
        private readonly PremiumCalculator $calculator,
        private readonly PolicyStateMachine $stateMachine,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{created: bool, request: PolicyRequest}
     */
    public function createOrGet(array $input, ?MockScenario $scenario = null): array
    {
        $snapshot = $this->calculator->calculate(
            PlanCode::from($input['plan_code']),
            Destination::from($input['destination']),
            (int) $input['sum_insured'],
            (string) $input['date_of_birth'],
            (string) $input['trip_start'],
            (string) $input['trip_end'],
        );

        try {
            $request = DB::transaction(function () use ($input, $snapshot, $scenario) {
                $uuid = (string) Str::uuid();
                $request = PolicyRequest::query()->create([
                    'uuid' => $uuid,
                    'agent_id' => $input['agent_id'],
                    'client_request_id' => $input['client_request_id'],
                    'customer_name' => $input['customer_name'],
                    'date_of_birth' => $input['date_of_birth'],
                    'destination' => $input['destination'],
                    'trip_start' => $input['trip_start'],
                    'trip_end' => $input['trip_end'],
                    'plan_code' => $input['plan_code'],
                    'sum_insured' => $input['sum_insured'],
                    ...$snapshot->toPersistenceArray(),
                    'state' => PolicyState::Received,
                    'attempt_count' => 0,
                    'mock_scenario' => $scenario ?? MockScenario::Success,
                    'issuance_idempotency_key' => 'issue:'.$uuid,
                ]);

                $this->stateMachine->recordInitial($request, 'Policy request received');
                $this->stateMachine->transition($request, PolicyState::Validated, 'Input validated; premium snapshot persisted');

                return $request;
            });

            return ['created' => true, 'request' => $request->fresh(['stateHistory'])];
        } catch (QueryException $exception) {
            if (! UniqueConstraint::isViolation($exception, 'policy_requests_agent_client_unique')
                && ! UniqueConstraint::isViolation($exception, 'agent_id')) {
                throw $exception;
            }

            $existing = PolicyRequest::query()
                ->where('agent_id', $input['agent_id'])
                ->where('client_request_id', $input['client_request_id'])
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            return ['created' => false, 'request' => $existing->load('stateHistory')];
        }
    }
}
