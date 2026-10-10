<?php

namespace App\Packs;

use App\Enums\FormationKind;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Fin des inscriptions des offres d'accompagnement (colonne formations.registration_ends_at, heure de Lomé).
 *
 * Une vraie date limite, pas un effet d'annonce : après elle, le serveur refuse toute nouvelle commande de
 * l'offre (App\Actions\Payments\SubmitManualPayment). Une capture déjà envoyée reste à valider ou refuser par
 * l'équipe, et les élèves qui ont l'offre gardent tous leurs accès. Les formations habituelles ne sont pas
 * concernées. Le compte à rebours est calculé à partir de cette date fixe (le même pour tout le monde).
 */
class PackDeadline
{
    /**
     * Date réglée au départ (demande de Joseph) : lundi 12 octobre 2026 à 16 h 00, heure de Lomé.
     */
    public const string INITIAL = '2026-10-12 16:00:00';

    public const string CLOSED_MESSAGE = 'Les inscriptions à cette offre sont closes.';

    /**
     * Format du champ « date et heure » de l'admin (datetime-local).
     */
    public const string INPUT_FORMAT = 'Y-m-d\TH:i';

    /**
     * Règle la fin des inscriptions des trois offres d'accompagnement à la date de départ, seulement pour celles
     * qui n'ont encore aucune date (une date réglée dans l'admin n'est jamais remplacée). Seule cette colonne
     * est écrite : aucun prix, texte, achat ni accès n'est touché. Relançable sans effet.
     *
     * @return int nombre d'offres mises à jour
     */
    public static function applyInitial(): int
    {
        $slugs = array_column(PackSetup::PACKS, 'slug');

        return DB::transaction(fn (): int => DB::table('formations')
            ->where('kind', FormationKind::Pack->value)
            ->whereIn('slug', $slugs)
            ->whereNull('registration_ends_at')
            ->update(['registration_ends_at' => self::INITIAL]));
    }

    /**
     * Valeur saisie dans l'admin (« 2026-10-12T16:00 », heure de Lomé), ou null si le champ est vide.
     */
    public static function parse(?string $value): ?CarbonImmutable
    {
        // Certains navigateurs ajoutent les secondes (« 2026-10-12T16:00:00 ») : seules les minutes comptent.
        $value = substr(trim((string) $value), 0, 16);

        if ($value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat(self::INPUT_FORMAT, $value, config('app.timezone'));

        return $date === null ? null : $date->startOfMinute();
    }

    /**
     * Règles de vérification du champ « Fin des inscriptions » de l'admin (vide = aucune limite).
     *
     * @return array{0: array<string, list<string>>, 1: array<string, string>}
     */
    public static function rules(string $field = 'registration_ends_at'): array
    {
        return [
            [$field => ['nullable', 'string', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s', 'after:2000-01-01', 'before:2100-01-01']],
            [
                $field.'.date_format' => 'Indiquez une date et une heure valides (ex. 12/10/2026 16:00), ou laissez vide pour aucune limite.',
                $field.'.after' => 'Indiquez une date et une heure valides, ou laissez vide pour aucune limite.',
                $field.'.before' => 'Indiquez une date et une heure valides, ou laissez vide pour aucune limite.',
            ],
        ];
    }

    /**
     * Texte du journal : « lundi 12 octobre 2026 à 16 h » ou « aucune limite ».
     */
    public static function describe(?CarbonInterface $deadline): string
    {
        return $deadline ? self::label($deadline) : 'aucune limite';
    }

    /**
     * Valeur du champ de l'admin pour une date enregistrée.
     */
    public static function input(?CarbonInterface $deadline): string
    {
        return $deadline?->copy()->setTimezone(config('app.timezone'))->format(self::INPUT_FORMAT) ?? '';
    }

    /**
     * « lundi 12 octobre 2026 à 16 h » (« à 16 h 30 » si les minutes ne sont pas nulles).
     */
    public static function label(CarbonInterface $deadline): string
    {
        $local = $deadline->copy()->setTimezone(config('app.timezone'));
        $time = $local->minute === 0 ? $local->format('G').' h' : $local->format('G').' h '.$local->format('i');

        return $local->translatedFormat('l j F Y').' à '.$time;
    }

    /**
     * Temps restant jusqu'à la date (minutes arrondies au-dessus : « 0 minute » n'apparaît jamais tant que
     * les inscriptions sont ouvertes). Même calcul que countdownParts() de resources/js/countdown.js.
     *
     * @return array{days: int, hours: int, minutes: int, closed: bool}
     */
    public static function remaining(CarbonInterface $deadline, ?CarbonInterface $now = null): array
    {
        $milliseconds = $deadline->getTimestampMs() - ($now ?? now())->getTimestampMs();

        if ($milliseconds <= 0) {
            return ['days' => 0, 'hours' => 0, 'minutes' => 0, 'closed' => true];
        }

        $minutes = (int) ceil($milliseconds / 60000);

        return [
            'days' => intdiv($minutes, 1440),
            'hours' => intdiv($minutes % 1440, 60),
            'minutes' => $minutes % 60,
            'closed' => false,
        ];
    }
}
