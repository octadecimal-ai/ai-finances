<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Claude AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Anthropic Claude API integration
    |
    */

    'api_key' => env('CLAUDE_API_KEY'),
    'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com'),
    'model' => env('CLAUDE_MODEL', 'claude-3-sonnet-20240229'),
    'max_tokens' => env('CLAUDE_MAX_TOKENS', 4000),
    'temperature' => env('CLAUDE_TEMPERATURE', 0.7),

    'features' => [
        'transaction_analysis' => env('CLAUDE_TRANSACTION_ANALYSIS', true),
        'category_suggestion' => env('CLAUDE_CATEGORY_SUGGESTION', true),
        'budget_recommendations' => env('CLAUDE_BUDGET_RECOMMENDATIONS', true),
        'financial_insights' => env('CLAUDE_FINANCIAL_INSIGHTS', true),
    ],

    'prompts' => [
        'transaction_analysis' => [
            'system' => 'You are a financial analyst assistant. Analyze the given transaction and provide insights about spending patterns, category suggestions, and financial recommendations.',
            'max_tokens' => 1000,
        ],
        'category_suggestion' => [
            'system' => 'You are a financial categorization expert. Based on the transaction description and amount, suggest the most appropriate category for this transaction.',
            'max_tokens' => 500,
        ],
        'budget_analysis' => [
            'system' => 'You are a personal finance advisor. Analyze the user\'s spending patterns and provide budget recommendations and financial insights.',
            'max_tokens' => 1500,
        ],
    ],

    'rate_limiting' => [
        'requests_per_minute' => env('CLAUDE_RATE_LIMIT', 60),
        'max_concurrent_requests' => env('CLAUDE_MAX_CONCURRENT', 10),
    ],
]; 