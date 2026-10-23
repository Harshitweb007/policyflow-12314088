<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PolicyFlow — Insurance Engine')</title>
    <style>
        :root {
            --bg: #0b1120;
            --surface: #1e293b;
            --surface-card: #182234;
            --surface-hover: #334155;
            --border: #334155;
            --border-light: #475569;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --primary-glow: rgba(59, 130, 246, 0.25);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #a855f7;
            --cyan: #06b6d4;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container { max-width: 1280px; margin: 0 auto; padding: 0 20px; width: 100%; }

        /* Top Navbar */
        .navbar {
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .nav-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 64px;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text);
        }
        .nav-logo {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            box-shadow: 0 0 16px var(--primary-glow);
        }
        .nav-title { font-size: 18px; font-weight: 700; }
        .nav-tag { font-size: 11px; color: var(--text-muted); }

        .nav-links {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .nav-link {
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-link:hover { color: var(--text); background: var(--surface-hover); }
        .nav-link.active {
            color: #60a5fa;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .btn:hover { background: var(--surface-hover); border-color: var(--border-light); }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-success { background: #059669; border-color: #059669; }
        .btn-success:hover { background: #047857; }
        .btn-warning { background: #d97706; border-color: #d97706; }
        .btn-warning:hover { background: #b45309; }
        .btn-danger { background: #dc2626; border-color: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-ISSUED { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .badge-VALIDATED { background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.4); }
        .badge-RECEIVED { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; border: 1px solid rgba(148, 163, 184, 0.3); }
        .badge-SUBMITTED { background: rgba(6, 182, 212, 0.2); color: #67e8f9; border: 1px solid rgba(6, 182, 212, 0.4); }
        .badge-PENDING_CONFIRMATION { background: rgba(245, 158, 11, 0.2); color: #fcd34d; border: 1px solid rgba(245, 158, 11, 0.4); }
        .badge-FAILED { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); }
        .badge-NEEDS_RECONCILIATION { background: rgba(168, 85, 247, 0.2); color: #d8b4fe; border: 1px solid rgba(168, 85, 247, 0.4); }

        /* Cards */
        .card {
            background: var(--surface-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        .card-title { font-size: 15px; font-weight: 700; color: var(--text); }

        /* Form elements */
        .form-group { margin-bottom: 14px; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
        input, select, textarea {
            width: 100%;
            padding: 9px 12px;
            background: #0b1120;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 13px;
            transition: border-color 0.15s ease;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-glow);
        }

        /* Tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th {
            text-align: left;
            padding: 12px 14px;
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.15);
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        /* Toast notifications */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            min-width: 320px;
            max-width: 440px;
            padding: 14px 16px;
            border-radius: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            animation: slideIn 0.25s ease-out;
        }
        .toast-success { border-color: var(--success); }
        .toast-error { border-color: var(--danger); }
        .toast-info { border-color: var(--primary); }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal.open { display: flex; }
        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        main { flex: 1; padding: 24px 0 40px; }
        footer {
            border-top: 1px solid var(--border);
            padding: 16px 0;
            font-size: 12px;
            color: var(--text-muted);
            text-align: center;
        }
    </style>
    @yield('styles')
</head>
<body>
    <nav class="navbar">
        <div class="container nav-inner">
            <a href="{{ route('dashboard') }}" class="nav-brand">
                <div class="nav-logo">PF</div>
                <div>
                    <div class="nav-title">PolicyFlow</div>
                    <div class="nav-tag">Insurance Issuance Engine</div>
                </div>
            </a>

            <div class="nav-links">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    📊 Dashboard
                </a>
                <a href="{{ route('intake') }}" class="nav-link {{ request()->routeIs('intake') ? 'active' : '' }}">
                    ➕ New Intake
                </a>
                <a href="{{ route('requests.index') }}" class="nav-link {{ request()->routeIs('requests.*') ? 'active' : '' }}">
                    📋 Policy Requests
                </a>
                <button type="button" class="btn btn-sm btn-primary" onclick="triggerReconcile(this)" style="margin-left: 10px;">
                    ⚡ Reconcile Sweep
                </button>
            </div>
        </div>
    </nav>

    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>

    <footer>
        <div class="container">
            PolicyFlow &bull; Fault-Tolerant Insurance Engine &bull; Laravel {{ app()->version() }} &bull; PHP {{ PHP_VERSION }}
        </div>
    </footer>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div>${message}</div>
                <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;">✕</button>
            `;
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 5000);
        }

        async function triggerReconcile(btn) {
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Reconciling...';

            try {
                const res = await fetch('/api/reconcile', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (res.ok) {
                    const d = json.data;
                    showToast(`<strong>Reconciliation Completed:</strong> Checked ${d.checked}, Resolved: ${d.resolved}, Pending: ${d.pending}, Failed: ${d.failed}`, 'success');
                    if (typeof refreshData === 'function') {
                        refreshData();
                    }
                } else {
                    showToast(`Reconciliation error: ${json.message}`, 'error');
                }
            } catch (err) {
                showToast(`Network error: ${err.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
    @yield('scripts')
</body>
</html>
