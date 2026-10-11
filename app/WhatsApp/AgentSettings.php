<?php

namespace App\WhatsApp;

use App\Contracts\AssistantModel;
use App\Contracts\WhatsAppSender;

/**
 * Réglages de l'agent WhatsApp (admin > Réglages du site > Agent WhatsApp, clé « site.whatsapp_agent ») et
 * réglages techniques lus dans .env (config/services.php, clé « whatsapp »).
 */
class AgentSettings
{
    /**
     * Limites modifiables dans l'admin : valeur par défaut, minimum, maximum.
     *
     * @var array<string, array{0: int, 1: int, 2: int}>
     */
    public const array LIMITS = [
        'contact_daily_limit' => [30, 5, 200],
        'pause_hours' => [24, 1, 168],
    ];

    /**
     * Interrupteur de l'admin (désactivé par défaut).
     */
    public static function switchedOn(): bool
    {
        return (bool) config('site.whatsapp_agent.enabled', false);
    }

    /**
     * L'agent répond-il ? Interrupteur allumé, Meta et Gemini configurés.
     */
    public static function enabled(): bool
    {
        return self::switchedOn() && app(WhatsAppSender::class)->isConfigured() && app(AssistantModel::class)->isConfigured();
    }

    public static function limit(string $name): int
    {
        [$default, $min, $max] = self::LIMITS[$name];
        $value = config('site.whatsapp_agent.'.$name);

        return is_numeric($value) ? max($min, min($max, (int) $value)) : $default;
    }

    public static function contactDailyLimit(): int
    {
        return self::limit('contact_daily_limit');
    }

    public static function pauseHours(): int
    {
        return self::limit('pause_hours');
    }

    /**
     * Clé secrète de l'application Meta (vérifie la signature des messages reçus).
     */
    public static function appSecret(): string
    {
        return trim((string) config('services.whatsapp.app_secret'));
    }

    /**
     * Mot de contrôle du webhook, recopié dans l'écran « Webhook » de Meta.
     */
    public static function verifyToken(): string
    {
        return trim((string) config('services.whatsapp.verify_token'));
    }

    /**
     * Identifiant du numéro de l'API (les messages destinés à un autre numéro sont ignorés).
     */
    public static function phoneNumberId(): string
    {
        return trim((string) config('services.whatsapp.phone_number_id'));
    }

    /**
     * État des réglages techniques pour l'écran de l'admin (jamais les valeurs elles-mêmes).
     *
     * @return array<string, array{label: string, present: bool}>
     */
    public static function checklist(): array
    {
        return [
            'token' => ['label' => 'Jeton d\'accès (WHATSAPP_ACCESS_TOKEN)', 'present' => trim((string) config('services.whatsapp.token')) !== ''],
            'phone' => ['label' => 'Identifiant du numéro (WHATSAPP_PHONE_NUMBER_ID)', 'present' => self::phoneNumberId() !== ''],
            'secret' => ['label' => 'Clé secrète de l\'application (WHATSAPP_APP_SECRET)', 'present' => self::appSecret() !== ''],
            'verify' => ['label' => 'Mot de contrôle du webhook (WHATSAPP_VERIFY_TOKEN)', 'present' => self::verifyToken() !== ''],
            'gemini' => ['label' => 'Clé Gemini (GEMINI_API_KEY, la même qu\'Ama)', 'present' => app(AssistantModel::class)->isConfigured()],
        ];
    }
}
