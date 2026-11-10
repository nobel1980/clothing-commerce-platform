<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Courier Provider
    |--------------------------------------------------------------------------
    |
    | Primary Bangladesh courier provider ('pathao', 'steadfast', 'redx').
    |
    */
    'default_courier' => env('SHIPPING_DEFAULT_COURIER', 'pathao'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Retry Policy for Courier APIs
    |--------------------------------------------------------------------------
    */
    'http_retry_times'    => (int) env('SHIPPING_HTTP_RETRY_TIMES', 3),
    'http_retry_sleep_ms' => (int) env('SHIPPING_HTTP_RETRY_SLEEP_MS', 100),
    'http_timeout_sec'    => (int) env('SHIPPING_HTTP_TIMEOUT_SEC', 15),

    /*
    |--------------------------------------------------------------------------
    | Courier Provider Configurations
    |--------------------------------------------------------------------------
    */
    'couriers' => [
        'pathao' => [
            'sandbox'        => (bool) env('PATHAO_SANDBOX', true),
            'base_url'       => env('PATHAO_BASE_URL', 'https://courier-api-sandbox.pathao.com'),
            'client_id'      => env('PATHAO_CLIENT_ID', ''),
            'client_secret'  => env('PATHAO_CLIENT_SECRET', ''),
            'username'       => env('PATHAO_USERNAME', ''),
            'password'       => env('PATHAO_PASSWORD', ''),
            'store_id'       => (int) env('PATHAO_STORE_ID', 1),
            'webhook_secret' => env('PATHAO_WEBHOOK_SECRET', ''),
            'tracking_base'  => env('PATHAO_TRACKING_URL', 'https://merchant.pathao.com/tracking?consignment_id='),
        ],

        'steadfast' => [
            'sandbox'        => (bool) env('STEADFAST_SANDBOX', true),
            'base_url'       => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),
            'api_key'        => env('STEADFAST_API_KEY', ''),
            'secret_key'     => env('STEADFAST_SECRET_KEY', ''),
            'webhook_secret' => env('STEADFAST_WEBHOOK_SECRET', ''),
            'tracking_base'  => env('STEADFAST_TRACKING_URL', 'https://steadfast.com.bd/t/'),
        ],

        'redx' => [
            'sandbox'        => (bool) env('REDX_SANDBOX', true),
            'base_url'       => env('REDX_BASE_URL', 'https://sandbox.redx.com.bd/v1.0.0-beta'),
            'access_token'   => env('REDX_ACCESS_TOKEN', ''),
            'webhook_secret' => env('REDX_WEBHOOK_SECRET', ''),
            'tracking_base'  => env('REDX_TRACKING_URL', 'https://redx.com.bd/track-global-parcel/?trackingId='),
        ],
    ],
];

