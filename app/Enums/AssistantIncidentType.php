<?php

namespace App\Enums;

/**
 * Incidents de l'assistante « Ama » (Gemini), affichés à l'admin sur « Conversations d'Ama ».
 * Le visiteur ne voit jamais ces détails : seulement un message clair et le bouton WhatsApp.
 */
enum AssistantIncidentType: string
{
    case MissingKey = 'missing_key';
    case VisitorDailyLimit = 'visitor_daily_limit';
    case VisitorMinuteLimit = 'visitor_minute_limit';
    case GlobalMinuteLimit = 'global_minute_limit';
    case GlobalDailyLimit = 'global_daily_limit';
    case GeminiQuota = 'gemini_quota';
    case GeminiError = 'gemini_error';
    case GeminiTimeout = 'gemini_timeout';
    case EmptyReply = 'empty_reply';
    // Pas un problème : durées mesurées d'une réponse (affichées à part, « Durées de réponse »).
    case ResponseTiming = 'response_timing';

    /**
     * Types qui sont de vrais incidents (limites, erreurs) ; la mesure des durées est affichée à part.
     *
     * @return list<self>
     */
    public static function problems(): array
    {
        $types = [];

        foreach (self::cases() as $type) {
            if ($type !== self::ResponseTiming) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function label(): string
    {
        return match ($this) {
            self::MissingKey => 'Clé GEMINI_API_KEY absente',
            self::VisitorDailyLimit => 'Limite de messages par jour d\'un visiteur atteinte',
            self::VisitorMinuteLimit => 'Trop de messages en une minute (protection contre les abus)',
            self::GlobalMinuteLimit => 'Limite de demandes par minute du site atteinte',
            self::GlobalDailyLimit => 'Limite de demandes par jour du site atteinte',
            self::GeminiQuota => 'Quota de Google atteint (erreur 429)',
            self::GeminiError => 'Erreur de Gemini',
            self::GeminiTimeout => 'Gemini n\'a pas répondu à temps',
            self::EmptyReply => 'Réponse vide ou bloquée par Gemini',
            self::ResponseTiming => 'Mesure des durées de réponse',
        };
    }

    /**
     * Message montré au visiteur (toujours accompagné du bouton « Écrivez-nous sur WhatsApp »).
     */
    public function visitorMessage(): string
    {
        return match ($this) {
            self::VisitorDailyLimit => 'Vous avez posé beaucoup de questions aujourd\'hui. Pour continuer, notre équipe vous répond sur WhatsApp.',
            self::VisitorMinuteLimit => 'Vous écrivez très vite ! Patientez une minute avant votre prochain message, ou écrivez-nous sur WhatsApp.',
            self::GlobalMinuteLimit, self::GlobalDailyLimit, self::GeminiQuota => 'Ama reçoit beaucoup de demandes en ce moment. Notre équipe vous répond sur WhatsApp.',
            default => 'Ama ne peut pas répondre pour le moment. Notre équipe vous répond sur WhatsApp.',
        };
    }
}
