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

    'dadata' => [
        'api_key' => env('DADATA_API_KEY'),
        'secret_key' => env('DADATA_SECRET_KEY'),
        'suggestions_url' => env('DADATA_SUGGESTIONS_URL', 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address'),
        'cleaner_url' => env('DADATA_CLEANER_URL', 'https://cleaner.dadata.ru/api/v1/clean/address'),
        'cleaner_name_url' => env('DADATA_CLEANER_NAME_URL', 'https://cleaner.dadata.ru/api/v1/clean/name'),
        'timeout' => (float) env('DADATA_TIMEOUT', 7),
        'verify_ssl' => filter_var(env('DADATA_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    ],

];
