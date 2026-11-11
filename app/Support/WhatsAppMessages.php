<?php

namespace App\Support;

use App\LiveSessions\SessionTime;
use App\Models\Consultation;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\Purchase;
use App\Models\User;
use App\Payments\PaymentMethods;
use App\Settings\SiteSettings;

/**
 * Messages préremplis des boutons WhatsApp, adaptés à la page (formation, paiement, formation gratuite…).
 *
 * Chaque message se termine par « Ma question : » (ou « Mon problème : ») suivi d'une espace : la personne
 * écrit sa question juste après, sans rien à effacer. Modèles modifiables dans l'admin (clé site.whatsapp_messages),
 * valeurs de départ dans config/site.php ; un modèle vidé reprend le texte d'origine.
 */
class WhatsAppMessages
{
    /**
     * Libellé dans l'admin, endroits où le message est utilisé et repères autorisés.
     *
     * @var array<string, array{label: string, where: string, placeholders: list<string>}>
     */
    public const array TEMPLATES = [
        'general' => ['label' => 'Toutes les autres pages', 'where' => 'Accueil, catalogue, À propos, Contact, Entreprises, Mon espace, Ama…', 'placeholders' => []],
        'formation' => ['label' => 'Page d\'une formation', 'where' => 'Page d\'une formation payante', 'placeholders' => ['{formation}']],
        'payment' => ['label' => 'Paiement d\'une formation', 'where' => 'Récapitulatif de commande et « Besoin d\'aide pour payer ? »', 'placeholders' => ['{formation}', '{etape}']],
        'session_payment' => ['label' => 'Paiement du supplément d\'une session', 'where' => 'Paiement du supplément d\'une session en présentiel', 'placeholders' => ['{formation}', '{date}', '{etape}']],
        'free' => ['label' => 'Formation gratuite', 'where' => 'Page d\'une formation gratuite et choix de l\'option', 'placeholders' => ['{formation}', '{date}']],
        'free_full' => ['label' => 'Formation gratuite complète', 'where' => 'Écran « Complet » d\'une formation gratuite', 'placeholders' => ['{formation}', '{date}']],
        'consultation' => ['label' => 'Consultation gratuite', 'where' => 'Page de réservation de la consultation gratuite', 'placeholders' => []],
        'consultation_booked' => ['label' => 'Consultation déjà réservée', 'where' => 'Page de gestion d\'un rendez-vous de consultation', 'placeholders' => ['{date}']],
        'purchase_refused' => ['label' => 'Paiement refusé', 'where' => 'Suivi d\'un achat dont le paiement a été refusé', 'placeholders' => ['{formation}', '{recu}']],
    ];

    /**
     * Nom des étapes du paiement (repère {etape}), dans l'ordre des 4 étapes de la page de paiement.
     *
     * @var list<string>
     */
    public const array PAYMENT_STEPS = ['choix du pays', 'choix de l\'opérateur', 'envoi de l\'argent', 'envoi de la capture'];

    /**
     * Étapes avant le paiement lui-même (compte, e-mail, paiement déjà envoyé).
     */
    public const string STEP_SUMMARY = 'récapitulatif de la commande';

    public const string STEP_EMAIL = 'confirmation de l\'adresse e-mail';

    public const string STEP_PENDING = 'paiement en cours de vérification';

    /**
     * Modèle enregistré dans l'admin, sinon texte d'origine.
     */
    public static function template(string $key): string
    {
        $stored = config('site.whatsapp_messages.'.$key);
        $text = is_string($stored) ? self::squish($stored) : '';

        return $text !== '' ? $text : self::squish((string) SiteSettings::default('site.whatsapp_messages.'.$key, ''));
    }

    /**
     * Message prêt à envoyer : repères remplacés, terminé par une espace après les deux-points.
     *
     * @param  array<string, string>  $values  ex. ['{formation}' => 'Agents IA']
     */
    public static function render(string $key, array $values = []): string
    {
        $text = self::template($key);

        // Formation gratuite sans date : « du {date} » devient le nom de la formation.
        if (array_key_exists('{date}', $values) && $values['{date}'] === '' && isset($values['{formation}'])) {
            $text = (string) preg_replace('/\s+du\s+\{date\}/u', ' « {formation} »', $text);
        }

        $text = strtr($text, $values);

        return str_ends_with($text, ':') ? $text.' ' : $text;
    }

    public static function general(): string
    {
        return self::render('general');
    }

    public static function formation(Formation $formation): string
    {
        return self::render('formation', ['{formation}' => $formation->title]);
    }

    /**
     * Paiement d'une formation ou du supplément d'une session, à une étape donnée.
     */
    public static function payment(Formation $formation, ?LiveSession $session, string $step): string
    {
        return $session !== null
            ? self::render('session_payment', ['{formation}' => $formation->title, '{date}' => SessionTime::day($session), '{etape}' => $step])
            : self::render('payment', ['{formation}' => $formation->title, '{etape}' => $step]);
    }

    /**
     * Les 4 liens du paiement, un par étape (le bouton suit l'étape affichée).
     *
     * @return list<string>
     */
    public static function paymentUrls(Formation $formation, ?LiveSession $session): array
    {
        return array_map(fn (string $step): string => WhatsApp::url(self::payment($formation, $session, $step)), self::PAYMENT_STEPS);
    }

    /**
     * Étape affichée au chargement de la page de paiement (même règle que le partiel du paiement) :
     * compte, e-mail à confirmer, paiement déjà envoyé, ou l'une des 4 étapes.
     */
    public static function paymentStep(?User $user, ?Purchase $unverified, bool $alreadyEnrolled = false): string
    {
        if ($user === null || $alreadyEnrolled) {
            return self::STEP_SUMMARY;
        }

        if (! $user->hasConfirmedEmail()) {
            return self::STEP_EMAIL;
        }

        if ($unverified !== null) {
            return self::STEP_PENDING;
        }

        $method = old('payment_method');

        if (is_string($method) && $method !== '' && PaymentMethods::enabled()->has($method)) {
            return self::PAYMENT_STEPS[session('errors')?->has('proof') ? 3 : 2];
        }

        return self::PAYMENT_STEPS[0];
    }

    public static function free(Formation $formation): string
    {
        return self::render('free', ['{formation}' => $formation->title, '{date}' => self::eventDate($formation)]);
    }

    public static function freeFull(Formation $formation): string
    {
        return self::render('free_full', ['{formation}' => $formation->title, '{date}' => self::eventDate($formation)]);
    }

    public static function consultation(): string
    {
        return self::render('consultation');
    }

    public static function consultationBooked(Consultation $consultation): string
    {
        return self::render('consultation_booked', ['{date}' => $consultation->starts_at->translatedFormat('l j F').' à '.SessionTime::hour($consultation->starts_at)]);
    }

    public static function purchaseRefused(Purchase $purchase): string
    {
        return self::render('purchase_refused', ['{formation}' => $purchase->item_label, '{recu}' => $purchase->receipt_number]);
    }

    /**
     * Repères écrits dans un modèle et non autorisés pour ce message (vérification de l'admin).
     *
     * @return list<string>
     */
    public static function unknownPlaceholders(string $key, string $text): array
    {
        preg_match_all('/\{[^}]*\}/u', $text, $matches);

        return array_values(array_unique(array_diff($matches[0], self::TEMPLATES[$key]['placeholders'] ?? [])));
    }

    /**
     * « 10 octobre » (date de l'événement d'une formation gratuite), vide sans date.
     */
    private static function eventDate(Formation $formation): string
    {
        return $formation->event_date?->translatedFormat('j F') ?? '';
    }

    private static function squish(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
