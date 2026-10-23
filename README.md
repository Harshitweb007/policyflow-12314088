# PolicyFlow

Fault-tolerant travel-insurance issuance and reconciliation engine (assignment implementation). Agents submit policy purchase requests; the application validates input, snapshots a deterministic INR premium, talks to a **local mock insurer**, records attempts and state history, and recovers from retries, duplicate callbacks, and timeout-after-success without creating a second policy.

This is a Laravel 13 / PHP 8.3+ JSON API. It does **not** call real insurers, payment gateways, or production customer systems.

## Architecture

| Component | Responsibility |
| --- | --- |
| `PolicyRequestController` / `MockInsurerCallbackController` | HTTP mapping only |
| Form requests | Input validation (422 field errors) |
| `PolicyRequestIntakeService` | Create-or-get using DB unique `(agent_id, client_request_id)` |
| `PremiumCalculator` | Decimal-safe premium using `brick/math` (HALF_UP) |
| `PolicyStateMachine` | Legal transitions + audit rows |
| `IssuanceService` | Claim (lock) → provider HTTP call **outside** the DB transaction → finalize |
| `MockInsurerClient` | Laravel HTTP client to the mock insurer |
| `MockInsurerHttpGateway` + `MockInsurerService` | Deterministic in-process mock with its **own** `mock_insurer_policies` table |
| `CallbackProcessor` | Idempotent provider events (`provider_event_id` unique) |
| `ReconciliationService` | Lookup by issuance idempotency key before any new issue |
| `policyflow:reconcile` | Repeatable artisan sweep of stuck requests |

Controllers do not update `state` directly. All transitions go through `PolicyStateMachine`.

## Prerequisites

- PHP 8.3+ (developed on PHP 8.5.3)
- Composer 2
- SQLite (default) **or** MySQL 8+ / PostgreSQL
- Optional: Node/npm only if you want the default Laravel welcome page assets

Redis, queues, Docker, and Filament are **not** required.

## Local setup

```bash
cd /path/to/Trippers
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

The API is then at `http://127.0.0.1:8000/api`.

### Environment

See `.env.example`. Important variables:

| Variable | Purpose |
| --- | --- |
| `DB_CONNECTION` | `sqlite` (default) or `mysql` / `pgsql` |
| `MOCK_INSURER_DRIVER` | `in_process` (default, no network) or `http` |
| `MOCK_INSURER_SHARED_SECRET` | HMAC key for the **simulated** callback signature |
| `MOCK_INSURER_ALLOW_SCENARIO_HEADER` | Local/dev only; ignored in `production` |
| `POLICYFLOW_STALE_ATTEMPT_SECONDS` | In-progress attempts older than this are treated as unknown |

`.env` is gitignored. Do not commit secrets.

### Reset local data (SQLite)

```bash
php artisan migrate:fresh
```

### Tests

```bash
php artisan test
```

Tests use in-memory SQLite (`phpunit.xml`) and `MOCK_INSURER_DRIVER=in_process`. They do not require the network.

## API

All JSON bodies use:

```json
{
  "success": true,
  "message": "...",
  "data": {}
}
```

Errors use `"success": false` and, for validation, an `errors` object. List endpoints add `meta` pagination.

Public request id is a UUID (`data.id`).

### POST `/api/policy-requests`

Create or return the existing logical request.

```bash
curl -sS -X POST http://127.0.0.1:8000/api/policy-requests \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'X-Mock-Scenario: SUCCESS' \
  -d '{
    "client_request_id": "client-req-001",
    "agent_id": "agent-001",
    "customer_name": "Asha Rao",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
```

- **201** new request (`VALIDATED`, premium snapshot stored)
- **200** same `agent_id` + `client_request_id` already exists (no second row)
- **422** field errors

`X-Mock-Scenario` is development-only (`SUCCESS`, `TEMPORARY_FAILURE`, `TIMEOUT_AFTER_ISSUE`, `DELAYED_CALLBACK`). Production always uses `SUCCESS`.

### GET `/api/policy-requests/{id}`

Supports both public UUID (e.g. `9f8e7d6c-...`) and numeric database ID (`1`).

**200** with state, premium snapshot, provider reference, policy number, attempt count, last error/summary, and `state_history`. **404** if unknown.

### POST `/api/policy-requests/{id}/submit`

Claim an issuance attempt, call the mock insurer, then finalize. Supports UUID or numeric ID.

- **200** `ISSUED` or retryable `FAILED`
- **202** `PENDING_CONFIRMATION` or `NEEDS_RECONCILIATION`
- **409** not eligible (wrong state, or non-retryable failure)
- **404** unknown id

Repeating submit on an issued request returns the same policy number. Ambiguous states **lookup** the mock insurer; they do not issue again. In-progress submissions are protected from concurrent duplicate execution.

### POST `/api/mock-insurer/callback`

Simulated provider webhook. Sign the **raw JSON body** with HMAC-SHA256 using `MOCK_INSURER_SHARED_SECRET` and send header `X-Mock-Insurer-Signature`.

This is a **local authenticity simulation**, not a production webhook control.

```bash
BODY='{"provider_event_id":"evt-001","provider_reference":"PRV-EXAMPLE","policy_request_id":"UUID-HERE","event_type":"ISSUED","policy_number":"POL-FROM-CALLBACK","occurred_at":"2026-10-23T12:00:00+00:00"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac 'change-me-local-only' | awk '{print $2}')
curl -sS -X POST http://127.0.0.1:8000/api/mock-insurer/callback \
  -H 'Content-Type: application/json' \
  -H "X-Mock-Insurer-Signature: $SIG" \
  -d "$BODY"
```

- **200** applied, duplicate, or ignored stale event
- **401** missing/invalid signature
- **404** unknown reference (event is still stored)
- **422** invalid payload

### GET `/api/policy-requests`

Pagination: `page`, `per_page` (default 15, max 100). Optional `state` filter. Ordered by `id` ascending.

**422** if `per_page` or `state` is invalid.

## Premium rules

`premium = base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier`

Rounded to 2 decimal places with **HALF_UP** (`Brick\Math\RoundingMode::HalfUp`, ties away from zero). Currency **INR**. Arithmetic uses `brick/math`, not floats.

| Plan | Base / day |
| --- | --- |
| BASIC | 12.00 |
| PLUS | 20.00 |
| PREMIUM | 30.00 |

Age is calculated on **trip_start**, not “today”:

| Age | Multiplier |
| --- | --- |
| 0–17 | 1.10 |
| 18–60 | 1.00 |
| 61–70 | 1.50 |
| 71+ | 2.00 |

| Destination | Multiplier |
| --- | --- |
| DOMESTIC | 1.00 |
| INTERNATIONAL | 1.80 |

| Sum insured | Multiplier |
| --- | --- |
| 100000 | 1.00 |
| 300000 | 1.25 |
| 500000 | 1.50 |

`travel_days = trip_end - trip_start + 1` (inclusive). Minimum one day.

Acceptance example: PLUS, 2026-11-10–2026-11-14, DOB 1998-04-10, INTERNATIONAL, 300000 → 5 days × 20 × 1.00 × 1.80 × 1.25 = **₹225.00**.

The snapshot is immutable after create. Retries reuse it.

## State machine

| From | To |
| --- | --- |
| (new) | RECEIVED |
| RECEIVED | VALIDATED, FAILED |
| VALIDATED | SUBMITTED, FAILED |
| SUBMITTED | ISSUED, PENDING_CONFIRMATION, FAILED, NEEDS_RECONCILIATION |
| PENDING_CONFIRMATION | ISSUED, FAILED, NEEDS_RECONCILIATION |
| NEEDS_RECONCILIATION | ISSUED, FAILED, PENDING_CONFIRMATION |
| FAILED | SUBMITTED (retryable failures only, enforced in `IssuanceService`) |
| ISSUED | *(none — never regresses)* |

Every actual change is stored in `policy_state_history`.

Outcomes:

- Definitive success → `ISSUED`
- Known retryable provider failure → `FAILED` with `RETRYABLE`
- Accepted without policy number → `PENDING_CONFIRMATION`
- Timeout / lost response after a possible issue → `PENDING_CONFIRMATION` (do **not** re-issue)

## Duplicate prevention

1. **Intake:** unique index `policy_requests_agent_client_unique` on `(agent_id, client_request_id)`. Concurrent duplicates surface as a unique violation; the service reloads the existing row and returns 200. Unrelated DB errors are rethrown.
2. **Issuance:** one stable `issuance_idempotency_key` (`issue:{uuid}`) for the logical operation. `lockForUpdate()` claims a row before calling the provider. In-progress attempts are not duplicated; stale in-progress work is reconciled, not re-issued.
3. **Mock insurer:** unique `idempotency_key` on `mock_insurer_policies`. Repeating the same key never creates a second policy number.
4. **Callbacks:** unique `provider_event_id`. Duplicates return the stored event. `ISSUED` ignores later `FAILED` callbacks.

HTTP timeouts, disabled buttons, and application-level “find existing” checks are **not** exactly-once across two systems. Safety comes from unique constraints + idempotency keys + provider lookup.

## Timeout-after-success

Scenario `TIMEOUT_AFTER_ISSUE`:

1. Mock insurer **does** persist `ISSUED` + policy number.
2. HTTP mapping returns 504 without those fields.
3. Application moves to `PENDING_CONFIRMATION`.
4. Next `submit` or `php artisan policyflow:reconcile` looks up by idempotency key and attaches the existing policy. `mock_insurer_policies` still has one row.

## Reliability scenarios implemented

| Case | Behaviour | Tests |
| --- | --- | --- |
| A Duplicate intake | Unique constraint + 200 replay | `PolicyRequestIntakeTest` |
| B Timeout after issue | Pending then recover, one mock policy | `IssuanceReliabilityTest`, `ReconcileCommandTest` |
| C Duplicate callbacks | Unique event id; ISSUED does not regress | `IssuanceReliabilityTest` |
| D Retry after temporary failure | Two attempts, same premium and key, one policy | `IssuanceReliabilityTest` |

## Reconciliation
 
### CLI Command
 
```bash
php artisan policyflow:reconcile
```

Prints checked / resolved (ISSUED) / still pending / failed counts. Safe to rerun repeatedly.
 
### HTTP Endpoint

```bash
curl -sS -X POST http://127.0.0.1:8000/api/reconcile -H 'Accept: application/json'
```

Triggers an immediate reconciliation sweep across all requests in `PENDING_CONFIRMATION` and `NEEDS_RECONCILIATION`, returning structured resolution metrics.

## Web UI & Interactive Routes

An interactive web interface is available out of the box (zero npm/vite build steps, pure responsive styling):

- **Dashboard Overview (`/`)**: High-level metrics (total, issued, pending, failed), recent requests, and real-time state transition audit stream.
- **New Policy Intake (`/intake`)**:
  - Live interactive premium calculation engine that dynamically recalculates on every field change with step-by-step formula breakdown.
  - One-click preset scenario triggers:
    - ⚡ **Benchmark ₹225 Example** (PLUS, 5 days, age 28, INTERNATIONAL, ₹300,000)
    - 🔄 **Case A: Duplicate Intake** (replay HTTP 200)
    - ⏳ **Case B: Timeout After Issue** (504 gateway timeout)
    - ⚠️ **Case D: Retriable Failure** (503 temporary unavailable)
  - AJAX submission with instant links to view the created record or issue it immediately.
- **Policy Requests (`/requests`)**: Searchable, filterable list by state, agent, customer, or policy number with pagination and inline submit triggers.
- **Policy Request Details (`/requests/{id}`)**:
  - Deep inspection of customer data, immutable pricing snapshot, and provider status.
  - Chronological visual timeline of `policy_state_history`.
  - Full attempt history (`issuance_attempts`) with request/response payloads.
  - Interactive **Simulate Provider Webhook** modal that auto-calculates the required HMAC SHA-256 signature and posts to `/api/mock-insurer/callback`.

## Mock insurer HTTP

Default `in_process` uses Laravel’s HTTP client with a **dedicated Guzzle client** whose handler is `MockInsurerHttpGateway` (no sockets). Driver `http` would call `MOCK_INSURER_BASE_URL` (not required for this assignment).

## Known limitations

- Default local DB is SQLite. `lockForUpdate()` is weaker than MySQL/PostgreSQL row locks; unique indexes still prevent duplicate logical requests and mock policies. PHPUnit uses in-memory SQLite, so tests do **not** claim a true multi-process lock race.
- Scenario selection via `X-Mock-Scenario` is disabled in `production`.
- Callback HMAC is a local stand-in, not mTLS or a public insurer webhook.
- `TEMPORARY_FAILURE` on the mock is “fail once, then issue on the same key” so retries remain idempotent.
- No agent authentication, no real PII store, no payment capture.

## Example payloads

See `examples/`.
