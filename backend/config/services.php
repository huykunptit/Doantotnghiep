<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'payos' => [
        'client_id' => env('PAYOS_CLIENT_ID'),
        'api_key' => env('PAYOS_API_KEY'),
        'checksum_key' => env('PAYOS_CHECKSUM_KEY'),
        'return_url' => env('PAYOS_RETURN_URL', 'http://localhost/payment/payos/callback'),
        'cancel_url' => env('PAYOS_CANCEL_URL', 'http://localhost/payment/payos/callback?cancelled=1'),
        'webhook_url' => env('PAYOS_WEBHOOK_URL', 'http://localhost/api/payos/webhook'),
    ],

    'ai_service' => [
        'url' => env('AI_SERVICE_URL', 'http://ai-service:8001'),
        'openai_api_key' => env('OPENAI_API_KEY'),
        'gemini_api_key' => env('GEMINI_API_KEY'),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'openrouter_api_key' => env('OPENROUTER_API_KEY'),
        'openrouter_model' => env('OPENROUTER_MODEL', 'deepseek/deepseek-chat:free'),
        'claude_api_key' => env('CLAUDE_API_KEY'),
        'claude_model' => env('CLAUDE_MODEL', 'nghi/claude-haiku-4.5'),
        'ollama_model' => env('OLLAMA_MODEL', 'qwen2.5:latest'),
    ],

];
