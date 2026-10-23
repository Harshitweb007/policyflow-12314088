# AI usage

## Tools

- Cursor (agent) with the Grok 4.6 model, working in this repository.
- Laravel Boost (`laravel/boost`) guidelines and skills after `php artisan boost:install`.

No other AI products were used for this implementation pass.

## How AI assisted

1. **Inspection and gap analysis.** The workspace already contained a PolicyFlow skeleton (migrations, services, tests). The agent mapped those files against the assignment instead of generating a second Laravel app.
2. **Failure diagnosis.** `php artisan test` showed Guzzle rejecting `handler` as a per-request option, and PHPUnit 12 ignoring `@dataProvider` annotations. Those defects were fixed in `MockInsurerClient` and `PremiumCalculatorTest`.
3. **Documentation.** README, this file, `.env.example`, and `examples/` were written to match the code that actually runs.

## Incorrect or incomplete suggestion

The original in-process mock wired Laravel HTTP via `Http::withOptions(['handler' => ...])`. That pattern used to work on older Guzzle stacks. On the installed Guzzle, it throws: *The "handler" request option is not supported; configure the handler when creating the client*.

**Correction:** build a dedicated `GuzzleHttp\Client` with `HandlerStack::create(...)` and attach it with `PendingRequest::setClient()`. That keeps Laravel’s HTTP client as the application-facing API while satisfying Guzzle’s client-level handler rule.

## Independent verification

- Ran `php artisan test` and `vendor/bin/phpunit --testdox`: **58 tests, 221 assertions, all passed** (0 failures, 0 errors).
- Tested the exact documented ₹225.00 example (PLUS plan, 5 days, age 28, INTERNATIONAL, ₹300,000 coverage).
- Checked uniqueness handling against SQLite’s `UNIQUE constraint failed` messages (`UniqueConstraint` treats SQLSTATE `23000` driver code `19` as unique, in addition to MySQL `1062`).
- Ran code formatter `vendor/bin/pint --format agent` to enforce PSR-12 and Laravel style guidelines.

Human review of the premium formula, transition table, and transaction boundaries is still expected for the interview.
