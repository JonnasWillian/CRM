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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    // Cobrança das assinaturas (ver docs/comercial/plano-de-cobranca-e-assinaturas.md).
    // Sem chave, a integração fica desligada e a fatura é paga pelo comando fatura:pagar.
    'asaas' => [
        'ambiente' => env('ASAAS_AMBIENTE', 'sandbox'), // producao | sandbox
        'chave' => env('ASAAS_API_KEY'),                // começa com $: no .env, entre aspas simples
        'token_webhook' => env('ASAAS_WEBHOOK_TOKEN'),  // 32 a 255 caracteres, sem espaços; nunca a chave de API
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
