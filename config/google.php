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
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID') ?: env('GOOGLE_DRIVE_CLIENT'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
        'shared_drive_id' => env('GOOGLE_DRIVE_SHARED_DRIVE_ID'),
        'project_id' => env('GOOGLE_DRIVE_project_id'),
        'auth_uri' => env('GOOGLE_DRIVE_auth_uri'),
        'token_uri' => env('GOOGLE_DRIVE_token_uri'),
        'auth_provider_x509_cert_url' => env('GOOGLE_DRIVE_auth_provider_x509_cert_url'),
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
        'auth_provider_x509_cert_url' => env('GOOGLE_AUTH_PROVIDER_X509_CERT_URL'),
        'client_x509_cert_url' => env('GOOGLE_CLIENT_X509_CERT_URL'),
        'universe_domain' => env('GOOGLE_UNIVERSE_DOMAIN'),
    ],
]; 