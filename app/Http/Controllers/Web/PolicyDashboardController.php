<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\CoverageAmount;
use App\Enums\Destination;
use App\Enums\MockScenario;
use App\Enums\PlanCode;
use App\Enums\PolicyState;
use App\Http\Controllers\Controller;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PolicyDashboardController extends Controller
{
    public function index(): mixed
    {
        try {
            $total = PolicyRequest::query()->count();
            $issued = PolicyRequest::query()->where('state', PolicyState::Issued)->count();
            $pending = PolicyRequest::query()->whereIn('state', [
                PolicyState::PendingConfirmation,
                PolicyState::NeedsReconciliation,
            ])->count();
            $failed = PolicyRequest::query()->where('state', PolicyState::Failed)->count();

            $recentRequests = PolicyRequest::query()->latest('id')->limit(8)->get();
            $recentHistory = PolicyStateHistory::query()->with('policyRequest')->latest('id')->limit(6)->get();

            return view('dashboard.index', compact('total', 'issued', 'pending', 'failed', 'recentRequests', 'recentHistory'));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 5),
            ], 500);
        }
    }

    public function intake(): mixed
    {
        try {
            return view('dashboard.intake', [
                'plans' => PlanCode::cases(),
                'destinations' => Destination::cases(),
                'coverages' => CoverageAmount::cases(),
                'scenarios' => MockScenario::cases(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 5),
            ], 500);
        }
    }

    public function list(Request $request): View
    {
        $query = PolicyRequest::query()->orderByDesc('id');

        if ($request->filled('state')) {
            $query->where('state', $request->string('state')->toString());
        }

        if ($request->filled('q')) {
            $search = '%'.$request->string('q')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', $search)
                    ->orWhere('agent_id', 'like', $search)
                    ->orWhere('client_request_id', 'like', $search)
                    ->orWhere('policy_number', 'like', $search)
                    ->orWhere('uuid', 'like', $search);
            });
        }

        $requests = $query->paginate(15)->withQueryString();
        $states = PolicyState::cases();

        return view('dashboard.list', compact('requests', 'states'));
    }

    public function show(PolicyRequest $policyRequest): View
    {
        $policyRequest->load([
            'stateHistory' => fn ($q) => $q->orderByDesc('id'),
            'attempts' => fn ($q) => $q->orderByDesc('id'),
            'providerEvents' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $sharedSecret = (string) config('policyflow.mock_insurer.shared_secret');

        return view('dashboard.show', compact('policyRequest', 'sharedSecret'));
    }
}
