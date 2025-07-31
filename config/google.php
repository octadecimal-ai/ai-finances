<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Drive Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Drive API integration
    |
    */

    'drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
        'scopes' => [
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/spreadsheets',
        ],
        'application_name' => env('GOOGLE_APPLICATION_NAME', 'Finances Analyzer'),
    ],

    'sheets' => [
        'default_folder_id' => env('GOOGLE_SHEETS_FOLDER_ID'),
        'template_id' => env('GOOGLE_SHEETS_TEMPLATE_ID'),
        'enable_auto_backup' => env('GOOGLE_SHEETS_AUTO_BACKUP', true),
    ],

    'credentials' => [
        'type' => env('GOOGLE_CREDENTIALS_TYPE', 'service_account'),
        'project_id' => env('GOOGLE_PROJECT_ID'),
        'private_key_id' => env('GOOGLE_PRIVATE_KEY_ID'),
        'private_key' => env('GOOGLE_PRIVATE_KEY'),
        'client_email' => env('GOOGLE_CLIENT_EMAIL'),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'auth_uri' => env('GOOGLE_AUTH_URI', 'https://accounts.google.com/o/oauth2/auth'),
        'token_uri' => env('GOOGLE_TOKEN_URI', 'https://oauth2.googleapis.com/token'),
    ],
]; 