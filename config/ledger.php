<?php

return [
    'rate_limits' => [
        'auth_per_minute' => (int) env('LEDGER_AUTH_RATE_LIMIT_PER_MINUTE', 10),
        'api_per_minute' => (int) env('LEDGER_API_RATE_LIMIT_PER_MINUTE', 120),
    ],
];
