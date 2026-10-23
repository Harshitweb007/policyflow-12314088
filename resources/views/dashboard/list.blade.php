@extends('layouts.app')

@section('title', 'Policy Requests — PolicyFlow')

@section('content')
<div style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700;">Policy Requests</h1>
            <p style="color: var(--text-muted); font-size: 13px;">Browse, filter, and track issuance status across all policy requests.</p>
        </div>
        <a href="{{ route('intake') }}" class="btn btn-primary">➕ New Policy Intake</a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom: 20px; padding: 14px 20px;">
    <form method="GET" action="{{ route('requests.index') }}" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by customer, agent, client req ID, policy #, or UUID...">
        </div>

        <div style="width: 200px;">
            <select name="state" onchange="this.form.submit()">
                <option value="">All States</option>
                @foreach($states as $st)
                    <option value="{{ $st->value }}" {{ request('state') === $st->value ? 'selected' : '' }}>
                        {{ $st->value }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="btn">Filter</button>
        @if(request()->filled('q') || request()->filled('state'))
            <a href="{{ route('requests.index') }}" class="btn btn-sm" style="color: var(--text-muted);">Clear</a>
        @endif
    </form>
</div>

<!-- Table Card -->
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Customer / Agent</th>
                    <th>Plan &amp; Duration</th>
                    <th>Premium (INR)</th>
                    <th>State</th>
                    <th>Policy #</th>
                    <th>Created</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    <tr id="row-{{ $req->uuid }}">
                        <td>
                            <a href="{{ route('requests.show', $req->uuid) }}" style="color: #60a5fa; font-weight: 700; text-decoration: none;">
                                {{ $req->customer_name }}
                            </a>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                Agent: <code>{{ $req->agent_id }}</code> &bull; Client Req: <code>{{ $req->client_request_id }}</code>
                            </div>
                        </td>
                        <td>
                            <strong>{{ $req->plan_code->value }}</strong> &bull; {{ $req->destination->value }}
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                {{ $req->trip_start->format('Y-m-d') }} &rarr; {{ $req->trip_end->format('Y-m-d') }} ({{ $req->travel_days }}d)
                            </div>
                        </td>
                        <td>
                            <strong style="color: #4ade80;">₹{{ number_format((float) $req->premium_amount, 2) }}</strong>
                            <div style="font-size: 11px; color: var(--text-muted);">Sum: ₹{{ number_format($req->sum_insured) }}</div>
                        </td>
                        <td>
                            <span class="badge badge-{{ $req->state->value }}" id="badge-{{ $req->uuid }}">{{ $req->state->value }}</span>
                        </td>
                        <td>
                            @if($req->policy_number)
                                <code style="color: #38bdf8; font-weight: 600;">{{ $req->policy_number }}</code>
                            @else
                                <span style="color: var(--text-muted);">&mdash;</span>
                            @endif
                        </td>
                        <td style="font-size: 12px; color: var(--text-muted);">
                            {{ $req->created_at->diffForHumans() }}
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 6px;">
                                @if($req->state->value !== 'ISSUED')
                                    <button type="button" class="btn btn-sm btn-primary" onclick="submitRowIssuance('{{ $req->uuid }}', this)">
                                        Submit
                                    </button>
                                @endif
                                <a href="{{ route('requests.show', $req->uuid) }}" class="btn btn-sm">
                                    Details &rarr;
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 40px;">
                            No policy requests found. <a href="{{ route('intake') }}" style="color: #60a5fa;">Create the first request</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($requests->hasPages())
        <div style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
            <div style="color: var(--text-muted);">
                Showing {{ $requests->firstItem() }} to {{ $requests->lastItem() }} of {{ $requests->total() }} policy requests
            </div>
            <div style="display: flex; gap: 6px;">
                @if($requests->onFirstPage())
                    <span class="btn btn-sm" style="opacity: 0.5; pointer-events: none;">&laquo; Previous</span>
                @else
                    <a href="{{ $requests->previousPageUrl() }}" class="btn btn-sm">&laquo; Previous</a>
                @endif

                @if($requests->hasMorePages())
                    <a href="{{ $requests->nextPageUrl() }}" class="btn btn-sm">Next &raquo;</a>
                @else
                    <span class="btn btn-sm" style="opacity: 0.5; pointer-events: none;">Next &raquo;</span>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    async function submitRowIssuance(uuid, btn) {
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';

        try {
            const res = await fetch(`/api/policy-requests/${uuid}/submit`, {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (res.ok) {
                showToast(`Issuance (${data.data.state}): ${data.message}`, 'success');
                const badge = document.getElementById(`badge-${uuid}`);
                if (badge) {
                    badge.className = `badge badge-${data.data.state}`;
                    badge.innerText = data.data.state;
                }
                if (data.data.state === 'ISSUED') {
                    btn.remove();
                }
            } else {
                showToast(`Submit failed: ${data.message}`, 'error');
            }
        } catch (err) {
            showToast(`Error: ${err.message}`, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
</script>
@endsection
