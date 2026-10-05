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

    // Notifications sur téléphone (Web Push) : clés VAPID créées avec « php artisan app:webpush-keys ».
    'webpush' => [
        'public_key' => env('WEBPUSH_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_PRIVATE_KEY'),
        'subject' => env('WEBPUSH_SUBJECT', 'mailto:academyjoseph71@gmail.com'),
    ],

    // Assistante « Ama » : API Gemini de Google, appelée uniquement par le serveur (App\Assistant\GeminiModel).
    // Clé dans .env (jamais dans le code). Modèle modifiable sans toucher au code (voir CLAUDE.md).
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
        // Secondes d'attente au maximum pour une réponse (le visiteur voit ensuite le bouton WhatsApp).
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),
        // Réflexion du modèle avant de répondre, au plus bas pour répondre vite : « minimal » (Gemini 3 et suivants :
        // niveau le plus bas, la réflexion ne peut pas être coupée ; Gemini 2.5 Flash : budget 0). Vide = rien envoyé.
        'thinking' => env('GEMINI_THINKING', 'minimal'),
        // Voix naturelle d'Ama (modèles « TTS ») : secondes d'attente au maximum côté serveur. Le navigateur, lui,
        // reprend la voix du téléphone après 4 secondes ; l'audio d'une phrase écrite à l'avance est quand même gardé.
        'tts_timeout' => (int) env('GEMINI_TTS_TIMEOUT', 8),
    ],

    // Voix naturelle d'Ama avec Cloudflare Workers AI (modèle MeloTTS, en français), appelé uniquement par le serveur
    // (App\Assistant\CloudflareSpeech). Numéro de compte et jeton d'API (droits « Workers AI - Read » et « Edit ») :
    // seulement dans les variables d'environnement, jamais dans le dépôt ni dans le navigateur.
    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'endpoint' => env('CLOUDFLARE_AI_ENDPOINT', 'https://api.cloudflare.com/client/v4'),
        // Secondes d'attente au maximum côté serveur (toujours au moins 2 de plus que le délai du téléphone de l'admin).
        'tts_timeout' => (int) env('CLOUDFLARE_TTS_TIMEOUT', 8),
    ],

];
