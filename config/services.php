<?php

return [
    'vehicle_debts' => [
        'provider_a_url' => env('PROVIDER_A_URL'),
        'provider_b_url' => env('PROVIDER_B_URL'),
        'timeout' => (int) env('PROVIDER_TIMEOUT_SECONDS', 3),
        'max_request_bytes' => (int) env('MAX_REQUEST_BYTES', 16384),
    ],
];
