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
    ],

    // Agent WhatsApp : API officielle WhatsApp Business Cloud de Meta (App\WhatsApp\CloudApiSender), appelée
    // uniquement par le serveur. Jeton, identifiant du numéro, clé secrète de l'application et mot de contrôle du
    // webhook dans .env (jamais dans le code). Sans eux, l'agent reste inactif et rien n'est envoyé.
    'whatsapp' => [
        // Jeton permanent d'un « utilisateur système » du portefeuille d'entreprise Meta.
        'token' => env('WHATSAPP_ACCESS_TOKEN'),
        // « Identifiant du numéro de téléphone » (WhatsApp > Configuration de l'API), pas le numéro lui-même.
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        // « Clé secrète de l'application » (Paramètres de l'app > Général) : vérifie que les messages reçus viennent de Meta.
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        // Mot de contrôle choisi par nous, recopié dans l'écran « Webhook » de Meta.
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v24.0'),
        'endpoint' => env('WHATSAPP_GRAPH_ENDPOINT', 'https://graph.facebook.com'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 10),
    ],

];
