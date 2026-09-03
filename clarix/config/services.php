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

    /*
    |--------------------------------------------------------------------------
    | Hermes (Telegram bot)
    |--------------------------------------------------------------------------
    |
    | The bot that links Telegram accounts to Clarix users. Unlike the task API,
    | Hermes does not authenticate as a user: it presents a static key and signs
    | each request, and the link code inside the body is what identifies the
    | person. See EnsureHermesRequest for why a user-authenticated token would
    | break the cross-organization lookup.
    |
    | Both key and secret are required in any environment where the endpoints
    | are reachable; the middleware refuses every request while either is unset,
    | so a half-configured deploy is closed rather than open.
    |
    */
    'hermes' => [
        'key'    => env('HERMES_API_KEY'),
        'secret' => env('HERMES_SIGNING_SECRET'),

        // Only used to build the t.me deep link shown in the connect card.
        'bot_username' => env('TELEGRAM_BOT_USERNAME', 'Jarvis_clarix_assistant_bot'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Task bot (n8n Telegram pipeline)
    |--------------------------------------------------------------------------
    |
    | A second, separate Telegram bot: the one PMs file tasks through. It shares
    | nothing with Hermes above — its own token, its own table, its own service
    | and its own key — because it serves a different pipeline and a different
    | set of users, and one shared credential would mean rotating one bot's key
    | silently breaks the other.
    |
    | Authentication is a single static shared key rather than Hermes's signed
    | request. That is a deliberate step down, taken because the caller is an
    | n8n workflow assembled in a visual editor; see EnsureN8nRequest for what
    | the trade costs and what it therefore demands of key handling.
    |
    | The key is required in any environment where the endpoints are reachable:
    | the middleware refuses every request while it is unset, so a
    | half-configured deploy is closed rather than open.
    |
    */
    'n8n' => [
        'key' => env('N8N_API_KEY'),

        // Only used to build the t.me deep link shown in the connect card. A
        // different handle from the AXOKAI bot's on purpose — they are two
        // separate bots, registered separately in BotFather.
        'bot_username' => env('N8N_TELEGRAM_BOT_USERNAME', 'clarix_task_bot'),
    ],

    /*
    |--------------------------------------------------------------------------
    | NewsData.io
    |--------------------------------------------------------------------------
    |
    | The curated tech news on the public /blog page. These are other people's
    | articles, linked out to — Clarix stores nothing but a short-lived cache
    | of headlines and the snippets the API itself returns.
    |
    | The free tier allows 200 credits a day and 30 credits per 15 minutes, and
    | one call is one credit. A 30 minute cache is 48 calls a day, roughly a
    | quarter of the daily allowance, which leaves room for the key to be used
    | elsewhere without the blog being what exhausts it.
    |
    | An unset key is a supported state, not a broken one: the feed returns
    | nothing and the page renders its "couldn't load" panel.
    |
    */
    'newsdata' => [
        'key' => env('NEWSDATA_API_KEY'),

        'endpoint' => env('NEWSDATA_ENDPOINT', 'https://newsdata.io/api/1/latest'),

        // Minutes. Tunable without a deploy if the allowance ever gets tight.
        'cache_minutes' => (int) env('NEWSDATA_CACHE_MINUTES', 30),

        // 10 is the free tier's ceiling per call, and one call is one credit —
        // so asking for fewer would cost exactly the same.
        'size' => (int) env('NEWSDATA_SIZE', 10),
    ],

];
