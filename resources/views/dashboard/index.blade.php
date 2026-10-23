@extends('layouts.app')

@section('title', 'Dashboard Overview — PolicyFlow')

@section('content')
<div style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700;">PolicyFlow Overview</h1>
            <p style="color: var(--text-muted); font-size: 13px;">Fault-Tolerant Travel Insurance Issuance &amp; Reconciliation Engine Dashboard</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('intake') }}" class="btn btn-primary">➕ New Policy Intake</a>
            <a href="{{ route('requests.index') }}" class="btn">📋 View All Requests</a>
        </div>
    </div>
</div>

<!-- Metrics Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px;">
    <div class="card" style="border-left: 4px solid var(--primary);">
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Total Policy Requests</div>
        <div style="font-size: 32px; font-weight: 800; margin: 4px 0;">{{ $total }}</div>
        <div style="font-size: 11px; color: var(--text-muted);">Unique across (agent_id, client_request_id)</div>
    </div>

    <div class="card" style="border-left: 4px solid var(--success);">
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Confirmed Issued</div>
        <div style="font-size: 32px; font-weight: 800; color: #34d399; margin: 4px 0;">{{ $issued }}</div>
        <div style="font-size: 11px; color: var(--text-muted);">Never regresses to earlier states</div>
    </div>

    <div class="card" style="border-left: 4px solid var(--warning);">
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Pending / Reconciliation</div>
        <div style="font-size: 32px; font-weight: 800; color: #fbbf24; margin: 4px 0;">{{ $pending }}</div>
        <div style="font-size: 11px; color: var(--text-muted);">Awaiting callback or status lookup</div>
    </div>

    <div class="card" style="border-left: 4px solid var(--danger);">
        <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">Failed</div>
        <div style="font-size: 32px; font-weight: 800; color: #f87171; margin: 4px 0;">{{ $failed }}</div>
        <div style="font-size: 11px; color: var(--text-muted);">Transient retriable or definitive</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Recent Requests -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Policy Requests</span>
            <a href="{{ route('requests.index') }}" style="font-size: 12px; color: #60a5fa; text-decoration: none;">View All &rarr;</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>Premium</th>
                        <th>State</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentRequests as $req)
                        <tr>
                            <td>
                                <a href="{{ route('requests.show', $req->uuid) }}" style="color: #60a5fa; font-weight: 600; text-decoration: none;">
                                    {{ $req->customer_name }}
                                </a>
                                <div style="font-size: 11px; color: var(--text-muted);">Agent: {{ $req->agent_id }}</div>
                            </td>
                            <td>
                                <strong>{{ $req->plan_code->value }}</strong>
                                <div style="font-size: 11px; color: var(--text-muted);">{{ $req->travel_days }} days &bull; {{ $req->destination->value }}</div>
                            </td>
                            <td>
                                <strong style="color: #4ade80;">₹{{ number_format((float) $req->premium_amount, 2) }}</strong>
                            </td>
                            <td>
                                <span class="badge badge-{{ $req->state->value }}">{{ $req->state->value }}</span>
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('requests.show', $req->uuid) }}" class="btn btn-sm">Details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                No requests recorded yet. <a href="{{ route('intake') }}" style="color: #60a5fa;">Start intake</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Audit Stream -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">⚡ Live Audit Stream</span>
            <span style="font-size: 11px; color: var(--text-muted);">State Machine</span>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            @forelse($recentHistory as $hist)
                <div style="padding: 10px 12px; background: rgba(0, 0, 0, 0.2); border-radius: 6px; border-left: 3px solid var(--primary); font-size: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                        <span class="badge badge-{{ $hist->to_state->value }}">{{ $hist->to_state->value }}</span>
                        <span style="color: var(--text-muted); font-size: 11px;">{{ $hist->created_at->diffForHumans() }}</span>
                    </div>
                    <div style="color: var(--text); margin-top: 4px;">{{ $hist->reason }}</div>
                    @if($hist->policyRequest)
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                            For: <a href="{{ route('requests.show', $hist->policyRequest->uuid) }}" style="color: #60a5fa; text-decoration: none;">{{ $hist->policyRequest->customer_name }}</a>
                        </div>
                    @endif
                </div>
            @empty
                <div style="color: var(--text-muted); text-align: center; padding: 20px;">No transitions recorded.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
