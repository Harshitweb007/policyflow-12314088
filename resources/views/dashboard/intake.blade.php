@extends('layouts.app')

@section('title', 'New Policy Intake — PolicyFlow')

@section('content')
<div style="margin-bottom: 24px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 24px; font-weight: 700;">New Policy Intake</h1>
            <p style="color: var(--text-muted); font-size: 13px;">Submit and snapshot insurance policies with real-time premium calculation &amp; deterministic mock scenarios.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-sm btn-primary" onclick="loadPreset('benchmark')">⚡ ₹225 Benchmark</button>
            <button type="button" class="btn btn-sm" onclick="loadPreset('duplicate')">🔄 Case A: Duplicate Intake</button>
            <button type="button" class="btn btn-sm" onclick="loadPreset('timeout')">⏳ Case B: Timeout Recovery</button>
            <button type="button" class="btn btn-sm" onclick="loadPreset('transient')">⚠️ Case D: Retriable Failure</button>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 420px; gap: 24px;">
    <!-- Intake Form -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Policy Request Information</span>
            <span style="font-size: 12px; color: var(--text-muted);">Unique: (agent_id, client_request_id)</span>
        </div>

        <form id="intakeForm" onsubmit="submitIntake(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label>Agent Identifier</label>
                    <input type="text" id="agent_id" name="agent_id" required value="agent-001" placeholder="e.g. agent-001">
                </div>
                <div class="form-group">
                    <label>Client Request Identifier</label>
                    <input type="text" id="client_request_id" name="client_request_id" required value="client-req-001" placeholder="e.g. req-1001">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label>Customer Full Name</label>
                    <input type="text" id="customer_name" name="customer_name" required value="Asha Rao" placeholder="e.g. Asha Rao">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" required value="1998-04-10" oninput="calculateLivePremium()">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label>Trip Start Date</label>
                    <input type="date" id="trip_start" name="trip_start" required value="2026-11-10" oninput="calculateLivePremium()">
                </div>
                <div class="form-group">
                    <label>Trip End Date</label>
                    <input type="date" id="trip_end" name="trip_end" required value="2026-11-14" oninput="calculateLivePremium()">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
                <div class="form-group">
                    <label>Plan Code</label>
                    <select id="plan_code" name="plan_code" onchange="calculateLivePremium()">
                        @foreach($plans as $plan)
                            <option value="{{ $plan->value }}" {{ $plan->value === 'PLUS' ? 'selected' : '' }}>
                                {{ $plan->value }} (₹{{ $plan->baseDailyRate() }}/d)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Destination</label>
                    <select id="destination" name="destination" onchange="calculateLivePremium()">
                        @foreach($destinations as $dest)
                            <option value="{{ $dest->value }}" {{ $dest->value === 'INTERNATIONAL' ? 'selected' : '' }}>
                                {{ $dest->value }} ({{ $dest->multiplier() }}x)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Sum Insured (INR)</label>
                    <select id="sum_insured" name="sum_insured" onchange="calculateLivePremium()">
                        @foreach($coverages as $cov)
                            <option value="{{ $cov->value }}" {{ $cov->value === 300000 ? 'selected' : '' }}>
                                ₹{{ number_format($cov->value) }} ({{ $cov->multiplier() }}x)
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Mock Insurer Scenario (Simulated Outcome)</label>
                <select id="mock_scenario" name="mock_scenario">
                    @foreach($scenarios as $scen)
                        <option value="{{ $scen->value }}">
                            {{ $scen->value }} — 
                            @if($scen->value === 'SUCCESS') Immediate Provider Issuance &amp; Policy #
                            @elseif($scen->value === 'TEMPORARY_FAILURE') Retriable 503 Provider Error
                            @elseif($scen->value === 'TIMEOUT_AFTER_ISSUE') 504 Timeout after provider issuance (Requires Reconciliation)
                            @elseif($scen->value === 'DELAYED_CALLBACK') 202 Accepted, awaits webhook confirmation
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="margin-top: 18px; display: flex; gap: 12px; align-items: center;">
                <button type="submit" id="submitBtn" class="btn btn-primary" style="padding: 10px 20px; font-size: 14px;">
                    Create Policy Request
                </button>
                <span id="formFeedback" style="font-size: 13px; color: var(--text-muted);"></span>
            </div>
        </form>

        <!-- Response Alert Box -->
        <div id="responseBox" style="display: none; margin-top: 20px; padding: 16px; border-radius: 8px; border: 1px solid var(--border);"></div>
    </div>

    <!-- Live Interactive Premium Breakdown -->
    <div>
        <div class="card" style="border-color: rgba(59, 130, 246, 0.4); background: linear-gradient(180deg, #182234, #0f172a);">
            <div class="card-header" style="border-bottom-color: rgba(59, 130, 246, 0.2);">
                <span class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    🧮 Live Premium Breakdown
                </span>
                <span class="badge badge-VALIDATED">Deterministic</span>
            </div>

            <div style="text-align: center; padding: 16px 0; background: rgba(0, 0, 0, 0.2); border-radius: 8px; margin-bottom: 16px;">
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Calculated Premium</div>
                <div id="liveTotal" style="font-size: 36px; font-weight: 800; color: #4ade80; margin: 4px 0;">₹225.00</div>
                <div style="font-size: 11px; color: var(--text-muted);">Currency: INR &bull; Rounding: HALF_UP</div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; padding-bottom: 6px; border-bottom: 1px dashed var(--border);">
                    <span style="color: var(--text-muted);">Base Daily Rate</span>
                    <strong id="liveBaseRate">₹20.00 / day</strong>
                </div>

                <div style="display: flex; justify-content: space-between; padding-bottom: 6px; border-bottom: 1px dashed var(--border);">
                    <span style="color: var(--text-muted);">Travel Duration</span>
                    <strong id="liveTravelDays">5 calendar days</strong>
                </div>

                <div style="display: flex; justify-content: space-between; padding-bottom: 6px; border-bottom: 1px dashed var(--border);">
                    <span style="color: var(--text-muted);">Customer Age on Departure</span>
                    <strong id="liveCustomerAge">28 yrs (ADULT &bull; 1.00x)</strong>
                </div>

                <div style="display: flex; justify-content: space-between; padding-bottom: 6px; border-bottom: 1px dashed var(--border);">
                    <span style="color: var(--text-muted);">Destination Multiplier</span>
                    <strong id="liveDestMultiplier">1.80x (INTERNATIONAL)</strong>
                </div>

                <div style="display: flex; justify-content: space-between; padding-bottom: 6px; border-bottom: 1px dashed var(--border);">
                    <span style="color: var(--text-muted);">Coverage Multiplier</span>
                    <strong id="liveCovMultiplier">1.25x (₹300,000)</strong>
                </div>
            </div>

            <div style="margin-top: 16px; padding: 10px; background: rgba(59, 130, 246, 0.1); border-radius: 6px; font-size: 11px; color: #93c5fd; line-height: 1.4;">
                <strong>Formula:</strong> <code>Rate × Days × AgeMult × DestMult × CovMult</code><br>
                <span id="liveFormula">₹20 × 5 × 1.00 × 1.80 × 1.25 = ₹225.00</span>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const planRates = { 'BASIC': 12.00, 'PLUS': 20.00, 'PREMIUM': 30.00 };
    const destMultipliers = { 'DOMESTIC': 1.00, 'INTERNATIONAL': 1.80 };
    const covMultipliers = { 100000: 1.00, 300000: 1.25, 500000: 1.50 };

    function calculateLivePremium() {
        const plan = document.getElementById('plan_code').value;
        const dest = document.getElementById('destination').value;
        const sum = parseInt(document.getElementById('sum_insured').value, 10);
        const dobStr = document.getElementById('date_of_birth').value;
        const startStr = document.getElementById('trip_start').value;
        const endStr = document.getElementById('trip_end').value;

        const baseRate = planRates[plan] || 20.00;
        const destMult = destMultipliers[dest] || 1.80;
        const covMult = covMultipliers[sum] || 1.25;

        // Calculate days
        let travelDays = 1;
        if (startStr && endStr) {
            const start = new Date(startStr);
            const end = new Date(endStr);
            if (end >= start) {
                const diffTime = Math.abs(end - start);
                travelDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            }
        }

        // Calculate age
        let age = 28;
        let ageMult = 1.00;
        let ageBand = 'ADULT';
        if (dobStr && startStr) {
            const dob = new Date(dobStr);
            const start = new Date(startStr);
            age = start.getFullYear() - dob.getFullYear();
            const m = start.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && start.getDate() < dob.getDate())) {
                age--;
            }
            if (age < 0) age = 0;

            if (age <= 17) { ageBand = 'CHILD'; ageMult = 1.10; }
            else if (age <= 60) { ageBand = 'ADULT'; ageMult = 1.00; }
            else if (age <= 70) { ageBand = 'SENIOR'; ageMult = 1.50; }
            else { ageBand = 'SUPER_SENIOR'; ageMult = 2.00; }
        }

        const raw = baseRate * travelDays * ageMult * destMult * covMult;
        const total = (Math.round((raw + Number.EPSILON) * 100) / 100).toFixed(2);

        document.getElementById('liveTotal').innerText = `₹${total}`;
        document.getElementById('liveBaseRate').innerText = `₹${baseRate.toFixed(2)} / day`;
        document.getElementById('liveTravelDays').innerText = `${travelDays} calendar day${travelDays > 1 ? 's' : ''}`;
        document.getElementById('liveCustomerAge').innerText = `${age} yrs (${ageBand} • ${ageMult.toFixed(2)}x)`;
        document.getElementById('liveDestMultiplier').innerText = `${destMult.toFixed(2)}x (${dest})`;
        document.getElementById('liveCovMultiplier').innerText = `${covMult.toFixed(2)}x (₹${sum.toLocaleString()})`;
        document.getElementById('liveFormula').innerText = `₹${baseRate.toFixed(2)} × ${travelDays} × ${ageMult.toFixed(2)} × ${destMult.toFixed(2)} × ${covMult.toFixed(2)} = ₹${total}`;
    }

    function loadPreset(type) {
        if (type === 'benchmark') {
            document.getElementById('agent_id').value = 'agent-' + Math.floor(Math.random() * 900 + 100);
            document.getElementById('client_request_id').value = 'client-req-' + Date.now().toString().slice(-4);
            document.getElementById('customer_name').value = 'Asha Rao';
            document.getElementById('date_of_birth').value = '1998-04-10';
            document.getElementById('destination').value = 'INTERNATIONAL';
            document.getElementById('trip_start').value = '2026-11-10';
            document.getElementById('trip_end').value = '2026-11-14';
            document.getElementById('plan_code').value = 'PLUS';
            document.getElementById('sum_insured').value = '300000';
            document.getElementById('mock_scenario').value = 'SUCCESS';
        } else if (type === 'duplicate') {
            document.getElementById('agent_id').value = 'agent-001';
            document.getElementById('client_request_id').value = 'client-req-001';
            document.getElementById('mock_scenario').value = 'SUCCESS';
        } else if (type === 'timeout') {
            document.getElementById('agent_id').value = 'agent-' + Math.floor(Math.random() * 900 + 100);
            document.getElementById('client_request_id').value = 'client-req-' + Date.now().toString().slice(-4);
            document.getElementById('mock_scenario').value = 'TIMEOUT_AFTER_ISSUE';
        } else if (type === 'transient') {
            document.getElementById('agent_id').value = 'agent-' + Math.floor(Math.random() * 900 + 100);
            document.getElementById('client_request_id').value = 'client-req-' + Date.now().toString().slice(-4);
            document.getElementById('mock_scenario').value = 'TEMPORARY_FAILURE';
        }
        calculateLivePremium();
        showToast(`Preset loaded: ${type.toUpperCase()}`, 'info');
    }

    async function submitIntake(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const box = document.getElementById('responseBox');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Creating...';

        const payload = {
            agent_id: document.getElementById('agent_id').value,
            client_request_id: document.getElementById('client_request_id').value,
            customer_name: document.getElementById('customer_name').value,
            date_of_birth: document.getElementById('date_of_birth').value,
            destination: document.getElementById('destination').value,
            trip_start: document.getElementById('trip_start').value,
            trip_end: document.getElementById('trip_end').value,
            plan_code: document.getElementById('plan_code').value,
            sum_insured: parseInt(document.getElementById('sum_insured').value, 10),
        };
        const scenario = document.getElementById('mock_scenario').value;

        try {
            const res = await fetch('/api/policy-requests', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Mock-Scenario': scenario
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (res.ok) {
                const isNew = res.status === 201;
                const statusTitle = isNew ? '201 Created (New Policy Request)' : '200 OK (Idempotent Duplicate Replay)';
                const badgeColor = isNew ? 'badge-VALIDATED' : 'badge-PENDING_CONFIRMATION';

                box.style.display = 'block';
                box.style.borderColor = isNew ? 'var(--success)' : 'var(--warning)';
                box.style.background = isNew ? 'rgba(16, 185, 129, 0.1)' : 'rgba(245, 158, 11, 0.1)';
                box.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <strong>${statusTitle}</strong>
                        <span class="badge ${badgeColor}">${data.data.state}</span>
                    </div>
                    <div style="font-size: 13px; color: var(--text);">
                        ${data.message}. Request UUID: <code style="color: #38bdf8;">${data.data.id}</code><br>
                        <strong>Premium Snapshot:</strong> ₹${data.data.premium.amount} ${data.data.premium.currency}
                    </div>
                    <div style="margin-top: 12px; display: flex; gap: 8px;">
                        <a href="/requests/${data.data.id}" class="btn btn-sm btn-primary">View Request Details &rarr;</a>
                        <button type="button" class="btn btn-sm btn-success" onclick="quickSubmit('${data.data.id}', this)">Submit Issuance Now</button>
                    </div>
                `;
                showToast(statusTitle, 'success');
            } else {
                box.style.display = 'block';
                box.style.borderColor = 'var(--danger)';
                box.style.background = 'rgba(239, 68, 68, 0.1)';
                box.innerHTML = `
                    <strong style="color: #f87171;">Error ${res.status}: ${data.message}</strong>
                    <pre style="font-size: 11px; margin-top: 8px; color: var(--text-muted);">${JSON.stringify(data.errors || data, null, 2)}</pre>
                `;
                showToast(`Validation error: ${data.message}`, 'error');
            }
        } catch (err) {
            showToast('Network error: ' + err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Create Policy Request';
        }
    }

    async function quickSubmit(id, btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Issuing...';
        try {
            const res = await fetch(`/api/policy-requests/${id}/submit`, {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (res.ok) {
                showToast(`Issuance complete (${data.data.state}): ${data.message}`, 'success');
                setTimeout(() => { window.location.href = `/requests/${id}`; }, 1000);
            } else {
                showToast(`Error: ${data.message}`, 'error');
            }
        } catch (err) {
            showToast(`Submit failed: ${err.message}`, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Submit Issuance Now';
        }
    }

    // Initialize calculation on load
    calculateLivePremium();
</script>
@endsection
