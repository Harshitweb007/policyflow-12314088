<?php

return [
    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    'currency' => 'INR',

    'rounding_mode' => 'HALF_UP', // Brick\Math\RoundingMode::HalfUp (ties away from zero)

    /*
    | Stale in-progress issuance attempts are treated as an unknown outcome.
    | The next submit or reconcile looks up the mock insurer instead of issuing again.
    */
    'stale_attempt_seconds' => (int) env('POLICYFLOW_STALE_ATTEMPT_SECONDS', 30),

    'mock_insurer' => [
        /*
        | in_process: Laravel HTTP client with an in-process Guzzle handler (no network,
        | no self-HTTP deadlock on php artisan serve).
        | http: real HTTP to MOCK_INSURER_BASE_URL (use only if the mock runs separately).
        */
        'driver' => env('MOCK_INSURER_DRIVER', 'in_process'),
        'base_url' => env('MOCK_INSURER_BASE_URL', 'http://mock-insurer.test'),
        'timeout_seconds' => (int) env('MOCK_INSURER_TIMEOUT_SECONDS', 5),
        'shared_secret' => env('MOCK_INSURER_SHARED_SECRET', 'local-dev-mock-secret'),
        'allow_scenario_header' => env('MOCK_INSURER_ALLOW_SCENARIO_HEADER', true),
    ],
];
