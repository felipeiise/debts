<?php

return [
    'vehicle_debts' => [
        'provider_a_url' => env('PROVIDER_A_URL'),
        'provider_b_url' => env('PROVIDER_B_URL'),
        'timeout' => (int) env('PROVIDER_TIMEOUT_SECONDS', 3),
        'max_request_bytes' => (int) env('MAX_REQUEST_BYTES', 16384),
        'as_of' => env('VEHICLE_DEBTS_AS_OF'),
        'circuit_breaker' => [
            'enabled' => (bool) env('CIRCUIT_BREAKER_ENABLED', false),
            'failure_threshold' => (int) env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
            'failure_window_seconds' => (int) env('CIRCUIT_BREAKER_FAILURE_WINDOW_SECONDS', 30),
            'open_seconds' => (int) env('CIRCUIT_BREAKER_OPEN_SECONDS', 30),
            'probe_lease_seconds' => (int) env('CIRCUIT_BREAKER_PROBE_LEASE_SECONDS', 15),
        ],
    ],
];
