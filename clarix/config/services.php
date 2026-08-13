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

    /*
    |--------------------------------------------------------------------------
    | Groq (Clarix AI chatbot)
    |--------------------------------------------------------------------------
    |
    | 'models' maps the product-facing names shown in the Chatbot picker to the
    | real Groq model IDs. Groq rotates and retires models often, so keep the
    | mapping here — repointing a name is a config change, never a code one.
    |
    | Checked against Groq's model and deprecation docs on 2026-08-13. Both IDs
    | below are production models on the free tier (30 RPM / 1K RPD). Note that
    | llama-3.1-8b-instant and llama-3.3-70b-versatile retire on 2026-08-16 and
    | gemma2-9b-it was shut down on 2025-10-08, so none of them are used.
    |
    */
    'groq' => [
        'key'      => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'timeout'  => (int) env('GROQ_TIMEOUT', 30),

        'models' => [
            'Titan 3.2'   => env('GROQ_MODEL_TITAN', 'openai/gpt-oss-120b'),
            'Gaia 2.0'    => env('GROQ_MODEL_GAIA', 'openai/gpt-oss-20b'),
            'Kronos 1.5'  => env('GROQ_MODEL_KRONOS', 'openai/gpt-oss-20b'),
            'Helios 4.0'  => env('GROQ_MODEL_HELIOS', 'openai/gpt-oss-120b'),
            'Olympus Max' => env('GROQ_MODEL_OLYMPUS', 'openai/gpt-oss-120b'),
        ],

        // Used when a name somehow arrives without a mapping.
        'fallback_model' => env('GROQ_FALLBACK_MODEL', 'openai/gpt-oss-20b'),

        // Thinking effort => [temperature, max_completion_tokens].
        'efforts' => [
            'Fast'     => ['temperature' => 0.4, 'max_tokens' => 512],
            'Balanced' => ['temperature' => 0.7, 'max_tokens' => 1024],
            'Deep'     => ['temperature' => 0.9, 'max_tokens' => 2048],
        ],

        // Messages one user may send per calendar day.
        'daily_limit' => (int) env('GROQ_DAILY_LIMIT', 15),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
