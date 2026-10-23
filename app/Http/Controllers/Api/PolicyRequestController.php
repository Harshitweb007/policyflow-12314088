<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MockScenario;
use App\Enums\PolicyState;
use App\Exceptions\IssuanceNotEligibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListPolicyRequestsRequest;
use App\Http\Requests\StorePolicyRequest;
use App\Http\Resources\PolicyRequestResource;
use App\Models\PolicyRequest;
use App\Services\Issuance\IssuanceService;
use App\Services\PolicyRequests\PolicyRequestIntakeService;
use App\Services\Reconciliation\ReconciliationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyRequestController extends Controller
{
    public function index(ListPolicyRequestsRequest $request): JsonResponse
    {
        $perPage = min(
            (int) $request->integer('per_page', (int) config('policyflow.pagination.default_per_page', 15)),
            (int) config('policyflow.pagination.max_per_page', 100),
        );

        $query = PolicyRequest::query()->orderBy('id');

        if ($request->filled('state')) {
            $query->where('state', $request->string('state')->toString());
        }

        $page = $query->paginate($perPage);

        $data = $page->getCollection()
            ->map(fn (PolicyRequest $item) => (new PolicyRequestResource($item))->additional(['include_history' => false])->resolve())
            ->all();

        return ApiResponse::success('Policy requests retrieved', $data, 200, [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function store(StorePolicyRequest $request, PolicyRequestIntakeService $intake): JsonResponse
    {
        $scenario = $this->resolveScenario($request);
        $result = $intake->createOrGet($request->validated(), $scenario);
        $resource = (new PolicyRequestResource($result['request']))->resolve();

        if ($result['created']) {
            return ApiResponse::success('Policy request created successfully', $resource, 201);
        }

        return ApiResponse::success('Policy request already exists', $resource, 200);
    }

    public function show(PolicyRequest $policyRequest): JsonResponse
    {
        $policyRequest->load(['stateHistory', 'attempts']);

        return ApiResponse::success(
            'Policy request retrieved',
            (new PolicyRequestResource($policyRequest))->resolve(),
        );
    }

    public function submit(PolicyRequest $policyRequest, IssuanceService $issuance): JsonResponse
    {
        try {
            $updated = $issuance->submit($policyRequest);
        } catch (IssuanceNotEligibleException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        }

        $status = match ($updated->state) {
            PolicyState::PendingConfirmation, PolicyState::NeedsReconciliation => 202,
            default => 200,
        };

        $message = match ($updated->state) {
            PolicyState::Issued => 'Policy issued',
            PolicyState::PendingConfirmation => 'Issuance pending confirmation',
            PolicyState::NeedsReconciliation => 'Issuance requires reconciliation',
            PolicyState::Failed => 'Issuance failed',
            PolicyState::Submitted => 'Issuance in progress',
            default => 'Issuance processed',
        };

        return ApiResponse::success(
            $message,
            (new PolicyRequestResource($updated))->resolve(),
            $status,
        );
    }

    private function resolveScenario(Request $request): MockScenario
    {
        $allowHeader = (bool) config('policyflow.mock_insurer.allow_scenario_header')
            && ! app()->environment('production');

        if (! $allowHeader) {
            return MockScenario::Success;
        }

        $header = $request->header('X-Mock-Scenario');

        return MockScenario::tryFrom((string) $header) ?? MockScenario::Success;
    }

    public function reconcile(ReconciliationService $reconciliation): JsonResponse
    {
        $requests = PolicyRequest::query()
            ->whereIn('state', [
                PolicyState::PendingConfirmation->value,
                PolicyState::NeedsReconciliation->value,
            ])
            ->orderBy('id')
            ->get();

        $checked = 0;
        $resolved = 0;
        $pending = 0;
        $failed = 0;

        foreach ($requests as $policy) {
            $checked++;
            $updated = $reconciliation->resolve($policy, 'API sweep reconciliation');

            match ($updated->state) {
                PolicyState::Issued => $resolved++,
                PolicyState::Failed => $failed++,
                default => $pending++,
            };
        }

        return ApiResponse::success('Reconciliation completed', [
            'checked' => $checked,
            'resolved' => $resolved,
            'pending' => $pending,
            'failed' => $failed,
        ]);
    }
}
