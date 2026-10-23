<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolicyFlow — Insurance Engine</title>
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --surface-hover: #334155;
            --border: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --badge-issued: #065f46;
            --badge-validated: #1e40af;
            --badge-pending: #92400e;
            --badge-failed: #991b1b;
            --badge-recon: #86198f;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            padding: 24px;
        }
        .container { max-width: 1300px; margin: 0 auto; }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .logo {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
        }
        h1 { font-size: 22px; font-weight: 700; }
        .subtitle { color: var(--text-muted); font-size: 13px; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-ISSUED { background: #065f46; color: #a7f3d0; }
        .badge-VALIDATED { background: #1e3a8a; color: #bfdbfe; }
        .badge-RECEIVED { background: #374151; color: #e5e7eb; }
        .badge-SUBMITTED { background: #164e63; color: #a5f3fc; }
        .badge-PENDING_CONFIRMATION { background: #78350f; color: #fde68a; }
        .badge-FAILED { background: #7f1d1d; color: #fecaca; }
        .badge-NEEDS_RECONCILIATION { background: #701a75; color: #f5d0fe; }

        .btn {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn:hover { background: var(--surface-hover); }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-sm { padding: 4px 8px; font-size: 12px; }

        .grid { display: grid; grid-template-columns: 380px 1fr; gap: 24px; }
        @media (max-width: 960px) { .grid { grid-template-columns: 1fr; } }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
        }
        .card-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-group { margin-bottom: 12px; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; }
        input, select {
            width: 100%;
            padding: 8px 10px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 13px;
        }
        input:focus, select:focus { outline: none; border-color: var(--primary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px; color: var(--text-muted); font-size: 11px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 20px;
        }
        .modal.open { display: flex; }
        .modal-content {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            max-width: 700px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 24px;
        }
        .history-item {
            padding: 8px 12px;
            background: #0f172a;
            border-left: 3px solid var(--primary);
            margin-bottom: 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .alert {
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 14px;
            font-size: 13px;
            display: none;
        }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid var(--success); color: #6ee7b7; }
        .alert-error { background: rgba(239, 68, 68, 0.2); border: 1px solid var(--danger); color: #fca5a5; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">
                <div class="logo">PF</div>
                <div>
                    <h1>PolicyFlow Engine</h1>
                    <div class="subtitle">Fault-Tolerant Travel Insurance Issuance &amp; Reconciliation</div>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button class="btn btn-primary" onclick="runReconciliation()">Run Reconciliation Sweep</button>
                <button class="btn" onclick="loadRequests()">Refresh</button>
            </div>
        </header>

        <div id="topAlert" class="alert"></div>

        <div class="grid">
            <!-- Left: Create Policy Request -->
            <div>
                <div class="card">
                    <div class="card-title">
                        <span>New Policy Intake</span>
                        <button type="button" class="btn btn-sm" onclick="prefillExample()">Prefill ₹225 Example</button>
                    </div>

                    <form id="createForm" onsubmit="handleCreate(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Agent ID</label>
                                <input type="text" id="agent_id" required value="agent-001">
                            </div>
                            <div class="form-group">
                                <label>Client Request ID</label>
                                <input type="text" id="client_request_id" required value="client-req-101">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Customer Name</label>
                            <input type="text" id="customer_name" required value="Asha Rao">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="date" id="date_of_birth" required value="1998-04-10">
                            </div>
                            <div class="form-group">
                                <label>Destination</label>
                                <select id="destination">
                                    <option value="INTERNATIONAL">INTERNATIONAL (1.8x)</option>
                                    <option value="DOMESTIC">DOMESTIC (1.0x)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Trip Start</label>
                                <input type="date" id="trip_start" required value="2026-11-10">
                            </div>
                            <div class="form-group">
                                <label>Trip End</label>
                                <input type="date" id="trip_end" required value="2026-11-14">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Plan Code</label>
                                <select id="plan_code">
                                    <option value="PLUS">PLUS (₹20/day)</option>
                                    <option value="BASIC">BASIC (₹12/day)</option>
                                    <option value="PREMIUM">PREMIUM (₹30/day)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Sum Insured (INR)</label>
                                <select id="sum_insured">
                                    <option value="300000">₹300,000 (1.25x)</option>
                                    <option value="100000">₹100,000 (1.00x)</option>
                                    <option value="500000">₹500,000 (1.50x)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Mock Scenario (Dev Mode)</label>
                            <select id="mock_scenario">
                                <option value="SUCCESS">SUCCESS (Immediate Issue)</option>
                                <option value="TEMPORARY_FAILURE">TEMPORARY_FAILURE (503 then Retry)</option>
                                <option value="TIMEOUT_AFTER_ISSUE">TIMEOUT_AFTER_ISSUE (504, Reconcile)</option>
                                <option value="DELAYED_CALLBACK">DELAYED_CALLBACK (202 Pending)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 8px;">Create Policy Request</button>
                    </form>
                </div>
            </div>

            <!-- Right: Requests List -->
            <div>
                <div class="card">
                    <div class="card-title">
                        <span>Policy Requests</span>
                        <div>
                            <select id="stateFilter" onchange="loadRequests()" style="width: auto; padding: 4px 8px; font-size: 12px;">
                                <option value="">All States</option>
                                <option value="VALIDATED">VALIDATED</option>
                                <option value="SUBMITTED">SUBMITTED</option>
                                <option value="PENDING_CONFIRMATION">PENDING_CONFIRMATION</option>
                                <option value="ISSUED">ISSUED</option>
                                <option value="FAILED">FAILED</option>
                                <option value="NEEDS_RECONCILIATION">NEEDS_RECONCILIATION</option>
                            </select>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer / Agent</th>
                                    <th>Plan &amp; Dates</th>
                                    <th>Premium</th>
                                    <th>State</th>
                                    <th>Policy #</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="requestsTableBody">
                                <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">Loading requests...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" class="modal" onclick="if(event.target === this) closeModal()">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 16px; font-weight: 700;">Request Details</h3>
                <button class="btn btn-sm" onclick="closeModal()">✕</button>
            </div>
            <div id="modalBody">Loading...</div>
        </div>
    </div>

    <script>
        function showAlert(msg, isSuccess = true) {
            const el = document.getElementById('topAlert');
            el.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-error');
            el.innerHTML = msg;
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 6000);
        }

        function prefillExample() {
            document.getElementById('agent_id').value = 'agent-' + Math.floor(Math.random() * 900 + 100);
            document.getElementById('client_request_id').value = 'req-' + Date.now();
            document.getElementById('customer_name').value = 'Asha Rao';
            document.getElementById('date_of_birth').value = '1998-04-10';
            document.getElementById('destination').value = 'INTERNATIONAL';
            document.getElementById('trip_start').value = '2026-11-10';
            document.getElementById('trip_end').value = '2026-11-14';
            document.getElementById('plan_code').value = 'PLUS';
            document.getElementById('sum_insured').value = '300000';
            document.getElementById('mock_scenario').value = 'SUCCESS';
        }

        async function handleCreate(e) {
            e.preventDefault();
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
                    const statusText = res.status === 201 ? 'Created (201)' : 'Existing Request Returned (200)';
                    showAlert(`${statusText}: ${data.message} — Premium: ₹${data.data.premium.amount} INR`, true);
                    loadRequests();
                } else {
                    showAlert(`Error (${res.status}): ${data.message || JSON.stringify(data.errors)}`, false);
                }
            } catch (err) {
                showAlert('Network error: ' + err.message, false);
            }
        }

        async function loadRequests() {
            const state = document.getElementById('stateFilter').value;
            const url = state ? `/api/policy-requests?state=${encodeURIComponent(state)}` : '/api/policy-requests';
            const tbody = document.getElementById('requestsTableBody');

            try {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                const list = json.data || [];

                if (list.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">No policy requests found.</td></tr>';
                    return;
                }

                tbody.innerHTML = list.map(req => `
                    <tr>
                        <td>
                            <strong>${escapeHtml(req.customer_name)}</strong><br>
                            <span style="font-size: 11px; color: var(--text-muted);">Agent: ${escapeHtml(req.agent_id)} &bull; ID: ${escapeHtml(req.id.substring(0, 8))}...</span>
                        </td>
                        <td>
                            ${escapeHtml(req.plan_code)} &bull; ${escapeHtml(req.destination)}<br>
                            <span style="font-size: 11px; color: var(--text-muted);">${req.trip_start} &rarr; ${req.trip_end} (${req.premium.travel_days}d)</span>
                        </td>
                        <td>
                            <strong>₹${req.premium.amount}</strong><br>
                            <span style="font-size: 11px; color: var(--text-muted);">${req.premium.currency}</span>
                        </td>
                        <td>
                            <span class="badge badge-${req.state}">${req.state}</span>
                        </td>
                        <td>
                            <span style="font-family: monospace; font-size: 12px;">${req.policy_number || '&mdash;'}</span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 6px;">
                                ${req.state !== 'ISSUED' ? `<button class="btn btn-sm btn-primary" onclick="submitIssuance('${req.id}')">Submit</button>` : ''}
                                <button class="btn btn-sm" onclick="showDetails('${req.id}')">Details</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger); padding: 20px;">Failed to load: ${err.message}</td></tr>`;
            }
        }

        async function submitIssuance(id) {
            try {
                const res = await fetch(`/api/policy-requests/${id}/submit`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (res.ok) {
                    showAlert(`Issuance response (${res.status}): ${json.message} &bull; State: ${json.data.state}`, true);
                } else {
                    showAlert(`Error (${res.status}): ${json.message}`, false);
                }
                loadRequests();
            } catch (err) {
                showAlert('Error submitting: ' + err.message, false);
            }
        }

        async function runReconciliation() {
            try {
                const res = await fetch('/api/reconcile', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (res.ok) {
                    const d = json.data;
                    showAlert(`Reconciliation completed: Checked ${d.checked}, Resolved: ${d.resolved}, Still Pending: ${d.pending}, Failed: ${d.failed}`, true);
                    loadRequests();
                } else {
                    showAlert(`Reconciliation error: ${json.message}`, false);
                }
            } catch (err) {
                showAlert('Reconciliation failed: ' + err.message, false);
            }
        }

        async function showDetails(id) {
            const modal = document.getElementById('detailModal');
            const body = document.getElementById('modalBody');
            modal.classList.add('open');
            body.innerHTML = 'Loading request details...';

            try {
                const res = await fetch(`/api/policy-requests/${id}`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                const d = json.data;

                const historyHtml = (d.state_history || []).map(h => `
                    <div class="history-item">
                        <strong>${h.from || 'START'} &rarr; ${h.to}</strong><br>
                        <span style="color: var(--text-muted);">${escapeHtml(h.reason)}</span>
                        <div style="font-size: 10px; color: var(--text-muted); margin-top: 2px;">${h.at}</div>
                    </div>
                `).join('') || '<div style="color: var(--text-muted);">No history recorded</div>';

                body.innerHTML = `
                    <div style="margin-bottom: 14px;">
                        <span class="badge badge-${d.state}">${d.state}</span>
                        <strong style="margin-left: 8px;">UUID:</strong> <code style="color: #38bdf8;">${d.id}</code>
                    </div>

                    <div style="background: #0f172a; padding: 12px; border-radius: 6px; font-size: 12px; margin-bottom: 16px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                            <div><strong>Agent:</strong> ${escapeHtml(d.agent_id)}</div>
                            <div><strong>Client Req:</strong> ${escapeHtml(d.client_request_id)}</div>
                            <div><strong>Customer:</strong> ${escapeHtml(d.customer_name)} (Age: ${d.premium.customer_age})</div>
                            <div><strong>DOB:</strong> ${d.date_of_birth}</div>
                            <div><strong>Plan:</strong> ${d.plan_code} (Base: ₹${d.premium.base_daily_rate})</div>
                            <div><strong>Destination:</strong> ${d.destination} (Mult: ${d.premium.destination_multiplier})</div>
                            <div><strong>Coverage:</strong> ₹${d.sum_insured.toLocaleString()} (Mult: ${d.premium.coverage_multiplier})</div>
                            <div><strong>Travel Days:</strong> ${d.premium.travel_days} (${d.trip_start} to ${d.trip_end})</div>
                            <div><strong>Age Band:</strong> ${d.premium.age_band} (Mult: ${d.premium.age_multiplier})</div>
                            <div><strong>Total Premium:</strong> <span style="color: #4ade80; font-weight: bold;">₹${d.premium.amount} ${d.premium.currency}</span></div>
                            <div><strong>Provider Ref:</strong> ${d.provider_reference || '&mdash;'}</div>
                            <div><strong>Policy Number:</strong> ${d.policy_number || '&mdash;'}</div>
                            <div><strong>Attempts:</strong> ${d.attempt_count}</div>
                            <div><strong>Summary:</strong> ${escapeHtml(d.last_provider_summary || '&mdash;')}</div>
                        </div>
                    </div>

                    <h4 style="font-size: 13px; font-weight: 700; margin-bottom: 8px;">State Transition Audit Trail</h4>
                    <div>${historyHtml}</div>
                `;
            } catch (err) {
                body.innerHTML = '<div style="color: var(--danger);">Failed to load details: ' + err.message + '</div>';
            }
        }

        function closeModal() {
            document.getElementById('detailModal').classList.remove('open');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Initial load
        loadRequests();
    </script>
</body>
</html>
