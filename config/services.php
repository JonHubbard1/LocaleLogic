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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'democracy_club' => [
        'api_key' => env('DEMOCRACY_CLUB_API_KEY'),
        'base_url' => env('DEMOCRACY_CLUB_BASE_URL', 'https://candidates.democracyclub.org.uk/api/v0.9'),
    ],

    'llm' => [
        'api_key' => env('LLM_API_KEY'),
        'base_url' => env('LLM_BASE_URL', 'http://localhost:11434'),
        'model' => env('LLM_MODEL', 'minimax-m2.7:cloud'),
        'driver' => env('LLM_DRIVER', 'ollama'), // 'ollama' or 'openai'
    ],

];
