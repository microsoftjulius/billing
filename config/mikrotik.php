<?php

return [
    'default_connection' => [
        'host' => env('MIKROTIK_HOST', '192.168.1.1'),
        'port' => env('MIKROTIK_PORT', 8728),
        'username' => env('MIKROTIK_USERNAME', 'admin'),
        'password' => env('MIKROTIK_PASSWORD', ''),
        'timeout' => env('MIKROTIK_TIMEOUT', 5),
    ],

    'api' => [
        'timeout' => 30,
        'attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],

    'cache' => [
        'enabled' => env('MIKROTIK_CACHE_ENABLED', true),
        'ttl' => env('MIKROTIK_CACHE_TTL', 300), // 5 minutes
        'prefix' => 'mikrotik:',
    ],

    'monitoring' => [
        'enabled' => env('MIKROTIK_MONITORING_ENABLED', true),
        'interval' => env('MIKROTIK_MONITORING_INTERVAL', 30), // seconds
        'offline_threshold' => env('MIKROTIK_OFFLINE_THRESHOLD', 120), // seconds
    ],

    'voucher_profiles' => [
        '1GB-DAILY' => [
            'name' => '1GB Daily',
            'data_limit' => '1GB',
            'time_limit' => '24h',
            'rate_limit' => '10M/10M',
        ],
        '5GB-WEEKLY' => [
            'name' => '5GB Weekly',
            'data_limit' => '5GB',
            'time_limit' => '7d',
            'rate_limit' => '20M/20M',
        ],
        '20GB-MONTHLY' => [
            'name' => '20GB Monthly',
            'data_limit' => '20GB',
            'time_limit' => '30d',
            'rate_limit' => '50M/50M',
        ],
        'UNLIMITED-DAILY' => [
            'name' => 'Unlimited Daily',
            'data_limit' => null,
            'time_limit' => '24h',
            'rate_limit' => '100M/100M',
        ],
        'UNLIMITED-WEEKLY' => [
            'name' => 'Unlimited Weekly',
            'data_limit' => null,
            'time_limit' => '7d',
            'rate_limit' => '100M/100M',
        ],
        'UNLIMITED-MONTHLY' => [
            'name' => 'Unlimited Monthly',
            'data_limit' => null,
            'time_limit' => '30d',
            'rate_limit' => '100M/100M',
        ],
    ],
];