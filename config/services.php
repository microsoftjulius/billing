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

    'collectug' => [
        'api_key' => env('COLLECTUG_API_KEY'),
        'base_url' => env('COLLECTUG_BASE_URL', 'https://api.collect.ug'),
        'callback_url' => env('COLLECTUG_CALLBACK_URL'),
        'webhook_secret' => env('COLLECTUG_WEBHOOK_SECRET'),
    ],

    'ugsms' => [
        'api_key' => env('UGSMS_API_KEY'),
        'base_url' => env('UGSMS_BASE_URL', 'https://api.ugsms.com'),
        'sender_id' => env('UGSMS_SENDER_ID', 'BILLING'),
        'default_prefix' => '256', // Uganda country code
    ],

];
