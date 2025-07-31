<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Slack Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Slack API integration
    |
    */

    'webhook_url' => env('SLACK_WEBHOOK_URL'),
    'bot_token' => env('SLACK_BOT_TOKEN'),
    'app_token' => env('SLACK_APP_TOKEN'),
    'signing_secret' => env('SLACK_SIGNING_SECRET'),

    'channels' => [
        'notifications' => env('SLACK_NOTIFICATIONS_CHANNEL', '#finances-notifications'),
        'alerts' => env('SLACK_ALERTS_CHANNEL', '#finances-alerts'),
        'reports' => env('SLACK_REPORTS_CHANNEL', '#finances-reports'),
    ],

    'notifications' => [
        'budget_exceeded' => env('SLACK_BUDGET_EXCEEDED', true),
        'large_transactions' => env('SLACK_LARGE_TRANSACTIONS', true),
        'sync_completed' => env('SLACK_SYNC_COMPLETED', true),
        'report_generated' => env('SLACK_REPORT_GENERATED', true),
        'error_alerts' => env('SLACK_ERROR_ALERTS', true),
    ],

    'thresholds' => [
        'large_transaction_amount' => env('SLACK_LARGE_TRANSACTION_AMOUNT', 1000),
        'budget_exceeded_percentage' => env('SLACK_BUDGET_EXCEEDED_PERCENTAGE', 90),
    ],

    'message_templates' => [
        'budget_exceeded' => [
            'text' => '⚠️ Budget exceeded for category: :category',
            'color' => '#ff0000',
        ],
        'large_transaction' => [
            'text' => '💰 Large transaction detected: :amount for :description',
            'color' => '#ffa500',
        ],
        'sync_completed' => [
            'text' => '✅ Bank data sync completed. :count new transactions imported.',
            'color' => '#00ff00',
        ],
        'report_generated' => [
            'text' => '📊 Monthly report generated and saved to Google Drive.',
            'color' => '#0000ff',
        ],
    ],
]; 