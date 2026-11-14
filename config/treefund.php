<?php

return [
    'demo_accounts_file' => env('TREEFUND_DEMO_ACCOUNTS_FILE'),
    'reservation_minutes' => (int) env('TREEFUND_RESERVATION_MINUTES', 60),
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY', ''),
        'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID', ''),
        'is_production' => false,
        'snap_url' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        'api_url' => 'https://api.sandbox.midtrans.com/v2',
        'script_url' => 'https://app.sandbox.midtrans.com/snap/snap.js',
    ],
];
