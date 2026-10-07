<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
        'redirect' => env('GOOGLE_REDIRECT_URI', '/cliente/auth/google/callback'),
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
        'waba_id' => env('WHATSAPP_WABA_ID', ''),
        'token' => env('WHATSAPP_ACCESS_TOKEN', ''),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN', 'restomaster_crm_webhook'),
        'app_secret' => env('WHATSAPP_APP_SECRET', ''),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    'dian' => [
        'proveedor' => env('DIAN_PROVEEDOR', 'factus'), // factus, dataico, siigo, simulador
        'api_url' => env('DIAN_API_URL', 'https://api-sandbox.factus.com.co'),
        'token' => env('DIAN_API_TOKEN', ''),
        'nit_emisor' => env('DIAN_NIT_EMISOR', '901234567-8'),
        'prefijo' => env('DIAN_PREFIJO_POS', 'POS'),
        'resolucion_numero' => env('DIAN_RESOLUCION_NUMERO', '18764000001'),
        'resolucion_fecha_desde' => env('DIAN_RESOLUCION_DESDE', '2026-01-01'),
        'resolucion_fecha_hasta' => env('DIAN_RESOLUCION_HASTA', '2027-01-01'),
        'rango_desde' => (int) env('DIAN_RANGO_DESDE', 1),
        'rango_hasta' => (int) env('DIAN_RANGO_HASTA', 500000),
        'clave_tecnica' => env('DIAN_CLAVE_TECNICA', 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c'),
        'ambiente' => env('DIAN_AMBIENTE', '2'), // 1: Producción, 2: Habilitación/Sandbox
    ],

    'wompi' => [
        'public_key' => env('WOMPI_PUBLIC_KEY', 'pub_test_Q5yDA9xoKdePzhSGeVe9KStXOmIOfoTr'),
        'private_key' => env('WOMPI_PRIVATE_KEY', 'prv_test_X2Z1bZJmQOloD8rB4p6G'),
        'integrity_secret' => env('WOMPI_INTEGRITY_SECRET', 'test_integrity_c64K1Y9fGqWz7Xy0A'),
        'events_secret' => env('WOMPI_EVENTS_SECRET', 'test_events_u87V3bX1yZ'),
        'api_url' => env('WOMPI_API_URL', 'https://sandbox.wompi.co/v1'),
    ],

    'bold' => [
        'api_key' => env('BOLD_API_KEY', 'bold_test_key_abc123'),
        'secret_key' => env('BOLD_SECRET_KEY', 'bold_secret_xyz789'),
        'api_url' => env('BOLD_API_URL', 'https://api.bold.co'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY', ''),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY', ''),
    ],

    'sentry' => [
        'dsn' => env('SENTRY_LARAVEL_DSN', ''),
        'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 1.0),
        'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.5),
    ],

];
