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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    // Accesos de demostración (botones demo y demo_token). Debe ser false en producción:
    // la rúbrica exige acceso exclusivamente con Google.
    'demo_login' => (bool) env('DEMO_LOGIN', false),

    'inference' => [
        'url'   => env('INFERENCE_URL', ''),
        'token' => env('INFERENCE_API_TOKEN', ''),
    ],

    // Agente conversacional con LLM propio (Qwen vía Ollama en RunPod, a través de INFERENCE_URL/llm/chat).
    // Si está desactivado o no responde, el agente usa las reglas deterministas.
    'llm' => [
        'habilitado' => (bool) env('AGENTE_LLM', false),
        'url'        => env('LLM_URL', ''),     // p. ej. https://<POD_ID>-8000.proxy.runpod.net
        'token'      => env('LLM_API_TOKEN', ''),
    ],

    // Dispositivo de alerta físico (ESP32): token propio, solo lectura del estado.
    'dispositivo' => [
        'token' => env('DEVICE_TOKEN', ''),
    ],

    'pose_colab' => [
        'url'   => env('POSE_INFERENCE_URL', ''),
        'token' => env('POSE_INFERENCE_API_TOKEN', ''),
    ],

];
