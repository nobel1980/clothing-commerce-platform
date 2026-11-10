<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default & Allowed Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Gateway credentials are injected strictly via runtime environment
    | variables and never hardcoded or exposed in API responses.
    |
    */

    'default_currency' => 'BDT',

    'allow_mock_gateway' => (bool) env('PAYMENT_ALLOW_MOCK_GATEWAY', env('APP_ENV') !== 'production'),

    'gateways' => [
        'bkash' => [
            'sandbox'      => (bool) env('BKASH_SANDBOX', true),
            'base_url'     => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
            'app_key'      => env('BKASH_APP_KEY', ''),
            'app_secret'   => env('BKASH_APP_SECRET', ''),
            'username'     => env('BKASH_USERNAME', ''),
            'password'     => env('BKASH_PASSWORD', ''),
            'callback_url' => env('BKASH_CALLBACK_URL', env('APP_URL', 'http://localhost:8000') . '/api/payments/callback/bkash'),
            'webhook_secret' => env('BKASH_WEBHOOK_SECRET', ''),
        ],

        'nagad' => [
            'sandbox'         => (bool) env('NAGAD_SANDBOX', true),
            'base_url'        => env('NAGAD_BASE_URL', 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0'),
            'merchant_id'     => env('NAGAD_MERCHANT_ID', ''),
            'merchant_number' => env('NAGAD_MERCHANT_NUMBER', ''),
            'public_key'      => env('NAGAD_PUBLIC_KEY', ''),
            'private_key'     => env('NAGAD_PRIVATE_KEY', ''),
            'callback_url'    => env('NAGAD_CALLBACK_URL', env('APP_URL', 'http://localhost:8000') . '/api/payments/callback/nagad'),
            'webhook_secret'  => env('NAGAD_WEBHOOK_SECRET', ''),
        ],

        'sslcommerz' => [
            'sandbox'        => (bool) env('SSLCOMMERZ_SANDBOX', true),
            'base_url'       => env('SSLCOMMERZ_BASE_URL', 'https://sandbox.sslcommerz.com'),
            'store_id'       => env('SSLCOMMERZ_STORE_ID', ''),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD', ''),
            'success_url'    => env('SSLCOMMERZ_SUCCESS_URL', env('APP_URL', 'http://localhost:8000') . '/api/payments/callback/sslcommerz'),
            'fail_url'       => env('SSLCOMMERZ_FAIL_URL', env('APP_URL', 'http://localhost:8000') . '/api/payments/callback/sslcommerz'),
            'cancel_url'     => env('SSLCOMMERZ_CANCEL_URL', env('APP_URL', 'http://localhost:8000') . '/api/payments/callback/sslcommerz'),
            'ipn_url'        => env('SSLCOMMERZ_IPN_URL', env('APP_URL', 'http://localhost:8000') . '/api/webhooks/sslcommerz'),
            'webhook_secret' => env('SSLCOMMERZ_WEBHOOK_SECRET', ''),
        ],

        'cod' => [
            'enabled' => true,
        ],

        'mock' => [
            'webhook_secret' => env('MOCK_GATEWAY_WEBHOOK_SECRET', 'test_mock_webhook_secret'),
            'merchant_id'    => env('MOCK_GATEWAY_MERCHANT_ID', 'MERCHANT_BD_TEST'),
        ],
    ],
];

