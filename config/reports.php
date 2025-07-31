<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reports Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for financial reports generation
    |
    */

    'formats' => [
        'excel' => [
            'enabled' => env('REPORTS_EXCEL_ENABLED', true),
            'template_path' => env('REPORTS_EXCEL_TEMPLATE', 'templates/reports/excel'),
        ],
        'pdf' => [
            'enabled' => env('REPORTS_PDF_ENABLED', true),
            'template_path' => env('REPORTS_PDF_TEMPLATE', 'templates/reports/pdf'),
        ],
        'csv' => [
            'enabled' => env('REPORTS_CSV_ENABLED', true),
        ],
    ],

    'types' => [
        'monthly_summary' => [
            'enabled' => true,
            'schedule' => 'monthly',
            'auto_generate' => true,
        ],
        'budget_analysis' => [
            'enabled' => true,
            'schedule' => 'weekly',
            'auto_generate' => true,
        ],
        'spending_patterns' => [
            'enabled' => true,
            'schedule' => 'monthly',
            'auto_generate' => false,
        ],
        'tax_summary' => [
            'enabled' => true,
            'schedule' => 'yearly',
            'auto_generate' => true,
        ],
    ],

    'storage' => [
        'local_path' => storage_path('app/reports'),
        'google_drive_folder' => env('REPORTS_GOOGLE_DRIVE_FOLDER'),
        'retention_days' => env('REPORTS_RETENTION_DAYS', 365),
    ],

    'charts' => [
        'spending_by_category' => true,
        'monthly_trends' => true,
        'budget_vs_actual' => true,
        'income_vs_expenses' => true,
    ],

    'notifications' => [
        'email_on_completion' => env('REPORTS_EMAIL_NOTIFICATION', true),
        'slack_on_completion' => env('REPORTS_SLACK_NOTIFICATION', true),
    ],
]; 