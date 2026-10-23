@extends('layouts.app')

@section('title', 'Policy Request Details — PolicyFlow')

@section('content')
<div style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
                <a href="{{ route('requests.index') }}" style="color: var(--text-muted); text-decoration: none; font-size: 13px;">&larr; Back to Requests</a>
                <span style="color: var(--border);">&bull;</span>
                <span class="badge badge-{{ $policyRequest->state->value }}" id="detailBadge" style="font-size: 13px; padding: 4px 12px;">
                    {{ $policyRequest->state->value }}
                </span>
            </div>
            <h1 style="font-size: 24px; font-weight: 700;">
                Policy Request: <code style="color: #38bdf8;">{{ $policyRequest->customer_name }}</code>
            </h1>
            <p style="color: var(--text-muted); font-size: 12px; margin-top: 2px;">
                UUID: <code>{{ $policyRequest->uuid }}</code> &bull; DB ID: <code>#{{ $policyRequest->id }}</code>
            </p>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;" id="actionBar">
            @if($policyRequest->state->value === 'FAILED' && $policyRequest->lastAttemptIsRetryableFailure())
                <button type="button" class="btn btn-primary" id="btnSubmitIssuance" onclick="submitIssuance(this)">
                    🔄 Retry Issuance (Attempt {{ $policyRequest->attempt_count + 1 }})
                </button>
            @elseif($policyRequest->state->value !== 'ISSUED')
                <button type="button" class="btn btn-primary" id="btnSubmitIssuance" onclick="submitIssuance(this)">
                    ⚡ Submit Issuance
                </button>
            @endif

            @if(in_array($policyRequest->state->value, ['PENDING_CONFIRMATION', 'NEEDS_RECONCILIATION']))
                <button type="button" class="btn btn-warning" id="btnReconcile" onclick="submitIssuance(this)">
                    🔄 Reconcile Provider Status
                </button>
            @endif

            <button type="button" class="btn btn-sm" onclick="openWebhookModal()">
                📬 Simulate Webhook Callback
            </button>
        </div>
    </div>
</div>

@if($policyRequest->last_error_message)
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); border-radius: 8px; padding: 14px 16px; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <strong style="color: #fca5a5;">Last Error: [{{ $policyRequest->last_error_code ?? 'ERROR' }}]</strong>
            @if($policyRequest->lastAttemptIsRetryableFailure())
                <span class="badge badge-PENDING_CONFIRMATION">RETRYABLE FAILURE</span>
            @else
                <span class="badge badge-FAILED">DEFINITIVE FAILURE</span>
            @endif
        </div>
        <div style="color: var(--text); font-size: 13px; margin-top: 6px;">{{ $policyRequest->last_error_message }}</div>
        @if($policyRequest->lastAttemptIsRetryableFailure())
            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed rgba(239, 68, 68, 0.3); font-size: 12px; color: #93c5fd; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                <span>ℹ️ This is a simulated <strong>transient failure (Case D)</strong>. The original premium snapshot is preserved. Click "Retry Issuance" to execute Attempt #2.</span>
                <button type="button" class="btn btn-sm btn-primary" onclick="submitIssuance(this)">🔄 Retry Issuance Now</button>
            </div>
        @endif
    </div>
@endif

<!-- Info Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <!-- Customer & Trip Details -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Customer &amp; Trip Data</span>
            <span style="font-size: 11px; color: var(--text-muted);">Intake Input</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Customer Name:</span>
                <strong>{{ $policyRequest->customer_name }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Date of Birth:</span>
                <span>{{ $policyRequest->date_of_birth->format('Y-m-d') }} (Age: {{ $policyRequest->customer_age }} yrs)</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Destination:</span>
                <strong>{{ $policyRequest->destination->value }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Travel Dates:</span>
                <span>{{ $policyRequest->trip_start->format('Y-m-d') }} &rarr; {{ $policyRequest->trip_end->format('Y-m-d') }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Duration:</span>
                <strong>{{ $policyRequest->travel_days }} calendar days</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Agent / Client Req:</span>
                <span><code>{{ $policyRequest->agent_id }}</code> / <code>{{ $policyRequest->client_request_id }}</code></span>
            </div>
        </div>
    </div>

    <!-- Immutable Pricing Snapshot -->
    <div class="card" style="border-color: rgba(59, 130, 246, 0.3);">
        <div class="card-header">
            <span class="card-title">Immutable Premium Snapshot</span>
            <span class="badge badge-VALIDATED">Protected</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Plan Code:</span>
                <strong>{{ $policyRequest->plan_code->value }} (₹{{ $policyRequest->base_daily_rate }}/d)</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Age Multiplier:</span>
                <span>{{ $policyRequest->age_multiplier }}x ({{ $policyRequest->age_band }})</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Destination Multiplier:</span>
                <span>{{ $policyRequest->destination_multiplier }}x</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Coverage Multiplier:</span>
                <span>{{ $policyRequest->coverage_multiplier }}x (₹{{ number_format($policyRequest->sum_insured) }})</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding-top: 6px; border-top: 1px dashed var(--border); font-size: 15px;">
                <span style="font-weight: 600;">Total Premium:</span>
                <strong style="color: #4ade80;">₹{{ number_format((float) $policyRequest->premium_amount, 2) }} {{ $policyRequest->currency }}</strong>
            </div>
            <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                * Snapshot created during intake and never recalculated on retry.
            </div>
        </div>
    </div>

    <!-- Insurer & Issuance Status -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Insurer &amp; Policy Status</span>
            <span style="font-size: 11px; color: var(--text-muted);">Provider State</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Policy Number:</span>
                <strong id="livePolicyNumber" style="color: #38bdf8;">{{ $policyRequest->policy_number ?? 'Not issued yet' }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Provider Reference:</span>
                <span id="liveProviderRef"><code>{{ $policyRequest->provider_reference ?? 'None' }}</code></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Issuance Attempts:</span>
                <span id="liveAttemptCount"><strong>{{ $policyRequest->attempt_count }}</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Idempotency Key:</span>
                <span><code style="font-size: 11px;">{{ $policyRequest->issuance_idempotency_key }}</code></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Mock Scenario:</span>
                <span class="badge" style="background: rgba(148, 163, 184, 0.15);">{{ $policyRequest->mock_scenario->value }}</span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Last Provider Summary:</span>
                <span style="font-size: 12px; color: var(--text-muted); text-align: right;" id="liveSummary">
                    {{ $policyRequest->last_provider_summary ?? 'No provider calls made yet' }}
                </span>
            </div>
        </div>
    </div>
</div>

<!-- State Transition Audit Timeline -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <span class="card-title">📜 State Transition Audit Trail (`policy_state_history`)</span>
        <span style="font-size: 12px; color: var(--text-muted);">Append-Only Transition Log</span>
    </div>

    <div style="display: flex; flex-direction: column; gap: 12px;" id="timelineContainer">
        @forelse($policyRequest->stateHistory as $hist)
            <div style="display: flex; gap: 14px; align-items: flex-start; padding: 10px 14px; background: rgba(0, 0, 0, 0.2); border-radius: 8px; border-left: 3px solid var(--primary);">
                <div style="min-width: 170px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="color: var(--text-muted); font-size: 11px;">{{ $hist->from_state?->value ?? 'START' }}</span>
                        <span style="color: var(--primary);">&rarr;</span>
                        <span class="badge badge-{{ $hist->to_state->value }}">{{ $hist->to_state->value }}</span>
                    </div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        {{ $hist->created_at->format('Y-m-d H:i:s') }}
                    </div>
                </div>
                <div style="flex: 1; font-size: 13px;">
                    <div>{{ $hist->reason }}</div>
                    @if($hist->issuance_attempt_id)
                        <span style="font-size: 11px; color: var(--cyan); margin-right: 8px;">Attempt #{{ $hist->issuance_attempt_id }}</span>
                    @endif
                    @if($hist->provider_event_id)
                        <span style="font-size: 11px; color: var(--purple);">Provider Event #{{ $hist->provider_event_id }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div style="color: var(--text-muted); text-align: center; padding: 20px;">No transitions recorded.</div>
        @endforelse
    </div>
</div>

<!-- Issuance Attempts Table -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <span class="card-title">⚙️ Issuance Attempts (`issuance_attempts`)</span>
        <span style="font-size: 12px; color: var(--text-muted);">Attempt Counter &bull; Idempotency Keys</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Status</th>
                    <th>Error Class</th>
                    <th>Provider Ref</th>
                    <th>Started</th>
                    <th>Completed</th>
                    <th>Error Detail</th>
                </tr>
            </thead>
            <tbody>
                @forelse($policyRequest->attempts as $att)
                    <tr>
                        <td><strong>Attempt {{ $att->attempt_number }}</strong></td>
                        <td>
                            <span class="badge" style="background: rgba(148, 163, 184, 0.2);">{{ $att->status->value }}</span>
                        </td>
                        <td>
                            @if($att->error_classification->value === 'RETRYABLE')
                                <span class="badge badge-PENDING_CONFIRMATION">RETRYABLE</span>
                            @elseif($att->error_classification->value === 'DEFINITIVE')
                                <span class="badge badge-FAILED">DEFINITIVE</span>
                            @else
                                <span style="color: var(--text-muted); font-size: 11px;">{{ $att->error_classification->value }}</span>
                            @endif
                        </td>
                        <td><code>{{ $att->provider_reference ?? '&mdash;' }}</code></td>
                        <td style="font-size: 12px; color: var(--text-muted);">{{ $att->started_at?->format('H:i:s') }}</td>
                        <td style="font-size: 12px; color: var(--text-muted);">{{ $att->completed_at?->format('H:i:s') ?? 'In Progress' }}</td>
                        <td style="font-size: 12px;">
                            @if($att->error_message)
                                <span style="color: #f87171;">[{{ $att->error_code }}] {{ $att->error_message }}</span>
                            @else
                                <span style="color: var(--success);">&check; Ok</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 20px;">No issuance attempts have been made yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for Webhook Callback Simulation -->
<div id="webhookModal" class="modal" onclick="if(event.target === this) closeWebhookModal()">
    <div class="modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 16px; font-weight: 700;">Simulate Mock Insurer Webhook</h3>
            <button type="button" class="btn btn-sm" onclick="closeWebhookModal()">✕</button>
        </div>

        <form id="webhookForm" onsubmit="sendWebhook(event)">
            <div class="form-group">
                <label>Provider Event ID (Must be unique)</label>
                <input type="text" id="wbk_event_id" value="evt-{{ time() }}" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Event Type</label>
                    <select id="wbk_event_type" onchange="togglePolicyNumField()">
                        <option value="ISSUED">ISSUED (Confirmed Policy)</option>
                        <option value="FAILED">FAILED (Provider Rejection)</option>
                        <option value="REJECTED">REJECTED (Definitive Rejection)</option>
                    </select>
                </div>
                <div class="form-group" id="policyNumGroup">
                    <label>Issued Policy Number</label>
                    <input type="text" id="wbk_policy_num" value="POL-SIM-{{ rand(10000, 99999) }}">
                </div>
            </div>

            <div class="form-group">
                <label>Provider Reference</label>
                <input type="text" id="wbk_prov_ref" value="{{ $policyRequest->provider_reference ?? 'PRV-SIM-'.rand(1000, 9999) }}">
            </div>

            <div class="form-group">
                <label>HMAC Shared Secret (Used to sign X-Mock-Insurer-Signature)</label>
                <input type="text" id="wbk_secret" readonly value="{{ $sharedSecret }}" style="background: rgba(0, 0, 0, 0.4); color: var(--text-muted);">
            </div>

            <div style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-sm" onclick="closeWebhookModal()">Cancel</button>
                <button type="submit" id="btnSendWebhook" class="btn btn-primary">Fire Webhook Callback</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    async function submitIssuance(btn) {
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Processing...';

        try {
            const res = await fetch(`/api/policy-requests/{{ $policyRequest->uuid }}/submit`, {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (res.ok) {
                showToast(`Issuance (${data.data.state}): ${data.message}`, 'success');
                setTimeout(() => { window.location.reload(); }, 800);
            } else {
                showToast(`Submit failed (${res.status}): ${data.message}`, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (err) {
            showToast(`Error: ${err.message}`, 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function openWebhookModal() {
        document.getElementById('webhookModal').classList.add('open');
    }

    function closeWebhookModal() {
        document.getElementById('webhookModal').classList.remove('open');
    }

    function togglePolicyNumField() {
        const type = document.getElementById('wbk_event_type').value;
        document.getElementById('policyNumGroup').style.display = type === 'ISSUED' ? 'block' : 'none';
    }

    async function sendWebhook(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSendWebhook');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Signing &amp; Sending...';

        const eventType = document.getElementById('wbk_event_type').value;
        const payload = {
            provider_event_id: document.getElementById('wbk_event_id').value,
            provider_reference: document.getElementById('wbk_prov_ref').value,
            policy_request_id: '{{ $policyRequest->uuid }}',
            event_type: eventType,
            policy_number: eventType === 'ISSUED' ? document.getElementById('wbk_policy_num').value : null,
            occurred_at: new Date().toISOString()
        };

        const secret = document.getElementById('wbk_secret').value;
        const rawBody = JSON.stringify(payload);

        // Compute HMAC SHA-256 signature in browser
        const enc = new TextEncoder();
        const key = await crypto.subtle.importKey(
            'raw',
            enc.encode(secret),
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        );
        const signatureBuffer = await crypto.subtle.sign('HMAC', key, enc.encode(rawBody));
        const hashArray = Array.from(new Uint8Array(signatureBuffer));
        const signatureHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');

        try {
            const res = await fetch('/api/mock-insurer/callback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Mock-Insurer-Signature': signatureHex
                },
                body: rawBody
            });
            const data = await res.json();
            if (res.ok) {
                showToast(`Webhook Processed (${data.data.processing_outcome}): ${data.message}`, 'success');
                closeWebhookModal();
                setTimeout(() => { window.location.reload(); }, 900);
            } else {
                showToast(`Webhook Error (${res.status}): ${data.message}`, 'error');
            }
        } catch (err) {
            showToast(`Webhook failed: ${err.message}`, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Fire Webhook Callback';
        }
    }
</script>
@endsection
