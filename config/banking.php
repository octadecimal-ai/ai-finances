<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Banking API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for various banking APIs including Nordigen and Revolut
    |
    */

    'nordigen' => [
        'base_url' => env('NORDIGEN_BASE_URL', 'https://ob.nordigen.com/api/v2'),
        'secret_id' => env('NORDIGEN_SECRET_ID'),
        'secret_key' => env('NORDIGEN_SECRET_KEY'),
        'timeout' => env('NORDIGEN_TIMEOUT', 30),
        'retry_attempts' => env('NORDIGEN_RETRY_ATTEMPTS', 3),
    ],

    'revolut' => [
        'base_url' => env('REVOLUT_BASE_URL', 'https://api.revolut.com'),
        'client_id' => env('REVOLUT_CLIENT_ID'),
        'client_secret' => env('REVOLUT_CLIENT_SECRET'),
        'redirect_uri' => env('REVOLUT_REDIRECT_URI'),
        'timeout' => env('REVOLUT_TIMEOUT', 30),
        'retry_attempts' => env('REVOLUT_RETRY_ATTEMPTS', 3),
    ],

    'wfirma' => [
        'base_url' => env('WFIRMA_BASE_URL', 'https://api2.wfirma.pl'),
        'access_token' => env('WFIRMA_ACCESS_TOKEN'),
        'company_id' => env('WFIRMA_COMPANY_ID'),
        'timeout' => env('WFIRMA_TIMEOUT', 30),
        'retry_attempts' => env('WFIRMA_RETRY_ATTEMPTS', 3),
    ],

    'sync' => [
        'interval' => env('BANK_SYNC_INTERVAL', 3600), // 1 hour
        'max_transactions_per_sync' => env('MAX_TRANSACTIONS_PER_SYNC', 1000),
        'enable_auto_sync' => env('ENABLE_AUTO_SYNC', true),
    ],

    'webhooks' => [
        'enabled' => env('BANKING_WEBHOOKS_ENABLED', false),
        'secret' => env('BANKING_WEBHOOK_SECRET'),
    ],
]; 