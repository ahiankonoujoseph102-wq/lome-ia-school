<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Notifications envoyées par des services extérieurs. Hors du groupe « web » : ni session, ni cookie, ni jeton
| CSRF (Meta ne peut pas en fournir) ; chaque contrôleur vérifie lui-même l'origine (signature).
*/

// Agent WhatsApp : vérification de l'adresse par Meta (GET) et messages reçus (POST, signature vérifiée).
Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:60,1')->name('whatsapp.webhook');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:600,1');
