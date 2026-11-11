<?php

namespace App\Scholarships;

use App\Models\Formation;
use App\Models\ScholarshipApplication;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Bourse « Créer des agents IA » : réglages (config site.scholarship, modifiables dans l'admin), fenêtre de
 * candidature et places restantes. Premiers payés, premiers servis : chaque capture envoyée réserve une place
 * (« seats_per_gender » femmes et autant d'hommes) ; une candidature refusée la libère. Plus aucune place, ou
 * heure de clôture passée : candidatures closes automatiquement.
 */
class ScholarshipCampaign
{
    public const string OFF = 'off';

    public const string SOON = 'soon';

    public const string OPEN = 'open';

    public const string FULL = 'full';

    public const string CLOSED = 'closed';

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return (array) config('site.scholarship', []);
    }

    public static function enabled(): bool
    {
        return (bool) (self::settings()['enabled'] ?? false);
    }

    public static function campaign(): string
    {
        return (string) (self::settings()['campaign'] ?? 'agents-ia');
    }

    public static function fee(): int
    {
        return max(0, (int) (self::settings()['fee'] ?? 5500));
    }

    public static function value(): int
    {
        return max(0, (int) (self::settings()['value'] ?? 0));
    }

    public static function seatsPerGender(): int
    {
        return max(1, (int) (self::settings()['seats_per_gender'] ?? 5));
    }

    public static function totalSeats(): int
    {
        return self::seatsPerGender() * count(ScholarshipApplication::GENDERS);
    }

    public static function opensAt(): ?CarbonImmutable
    {
        return self::date(self::settings()['opens_at'] ?? null);
    }

    public static function closesAt(): ?CarbonImmutable
    {
        return self::date(self::settings()['closes_at'] ?? null);
    }

    public static function formation(): ?Formation
    {
        $slug = (string) (self::settings()['formation'] ?? '');

        return $slug !== '' ? Formation::query()->where('slug', $slug)->first() : null;
    }

    /**
     * Places occupées (réservées ou validées) par genre.
     *
     * @return array<string, int>
     */
    public static function taken(): array
    {
        $counts = ScholarshipApplication::query()
            ->where('campaign', self::campaign())
            ->holdingSeat()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        $taken = [];
        foreach (array_keys(ScholarshipApplication::GENDERS) as $gender) {
            $taken[$gender] = (int) ($counts[$gender] ?? 0);
        }

        return $taken;
    }

    /**
     * Places restantes par genre.
     *
     * @return array<string, int>
     */
    public static function remaining(): array
    {
        return array_map(fn (int $taken): int => max(0, self::seatsPerGender() - $taken), self::taken());
    }

    /**
     * État de la bourse : éteinte, bientôt ouverte, ouverte, complète ou close.
     */
    public static function state(): string
    {
        if (! self::enabled()) {
            return self::OFF;
        }

        $now = CarbonImmutable::now();
        $opens = self::opensAt();
        $closes = self::closesAt();

        if ($opens === null || $closes === null || $now->lt($opens)) {
            return self::SOON;
        }

        if ($now->gte($closes)) {
            return self::CLOSED;
        }

        return array_sum(self::remaining()) === 0 ? self::FULL : self::OPEN;
    }

    public static function isOpen(): bool
    {
        return self::state() === self::OPEN;
    }

    /**
     * Données du compteur (page publique, mise à jour régulière par le navigateur).
     *
     * @return array{state: string, remaining: array<string, int>, total: int, seats: int, closes_at: string|null}
     */
    public static function counter(): array
    {
        $remaining = self::remaining();

        return [
            'state' => self::state(),
            'remaining' => $remaining,
            'total' => array_sum($remaining),
            'seats' => self::seatsPerGender(),
            'closes_at' => self::closesAt()?->toIso8601String(),
        ];
    }

    /**
     * Fiche envoyée à Ama (Gemini) à chaque message, lue en direct dans les réglages et la base : état, places
     * restantes, dates, frais, conditions et parcours. Vide quand la bourse est éteinte.
     */
    public static function assistantText(): string
    {
        $state = self::state();

        if ($state === self::OFF) {
            return '';
        }

        $remaining = self::remaining();
        $closes = self::closesAt()?->translatedFormat('l j F Y à H \\h i');
        $opens = self::opensAt()?->translatedFormat('l j F Y à H \\h i');
        $fee = Money::fcfa(self::fee());
        $title = self::formation()->title ?? 'Créer des agents IA';

        $lines = [
            'Page : '.route('scholarship').' (candidature sans compte, sur la même page que le paiement).',
            'État en ce moment : '.match ($state) {
                self::SOON => 'pas encore ouverte'.($opens ? ', ouverture le '.$opens.' (heure de Lomé)' : ''),
                self::OPEN => 'candidatures ouvertes jusqu\'au '.$closes.' (heure de Lomé), ou jusqu\'à ce que toutes les places soient prises',
                self::FULL => 'toutes les places sont prises : candidatures closes',
                default => 'candidatures closes',
            }.'.',
            'Places restantes en ce moment : '.$remaining['female'].' pour les femmes et '.$remaining['male'].' pour les hommes (sur '.self::seatsPerGender().' + '.self::seatsPerGender().'). Ces chiffres changent : renvoie au compteur de la page.',
            'Offre (faits fournis par Joseph) : la formation « '.$title.' »'.(self::value() > 0 ? ', d\'une valeur de '.Money::fcfa(self::value()) : '').', offerte à 100 % ; un mois en ligne avec vidéos enregistrées, documents de formation et directs explicatifs et démonstratifs ; pendant le mois, chaque personne crée 3 agents IA de A à Z, jusqu\'à l\'hébergement ; abonnements des outils pris en charge pendant la formation ; accès au groupe de la formation ; vidéo de bilan honnête à la fin. CONDITION PRINCIPALE (contrepartie de la bourse) : à la fin de la formation, chaque personne retenue crée gratuitement 3 agents IA pour Coach Joseph, correctement et de A à Z ; c\'est Coach Joseph qui dit quels agents créer.',
            'Conditions : '.self::totalSeats().' places ('.self::seatsPerGender().' femmes, '.self::seatsPerGender().' hommes), de 20 à 40 ans, un minimum de connaissances en digital et en IA, motivation pour l\'IA et un « pourquoi » fort. Seuls frais : '.$fee.' d\'inscription par Mobile Money (frais de transfert à la charge de la personne).',
            'Parcours : remplir le formulaire, payer '.$fee.' par Mobile Money (mêmes numéros que les autres paiements), envoyer la capture, le tout sans compte. Premiers payés, premiers servis : la place est réservée dès l\'envoi, puis confirmée quand l\'équipe a vérifié le paiement sur son compte Mobile Money. Une capture qui ne correspond à aucun paiement reçu est refusée et la place libérée. Si la personne n\'est pas retenue, ses '.$fee.' lui sont remboursés. Personne retenue : compte créé, accès et groupe ouverts, e-mail avec un lien pour choisir son mot de passe.',
            'Règles : pas de date de début ni de nombre de directs par semaine annoncés pour l\'instant (ne les invente pas) ; aucune promesse de revenus ou d\'emploi ; tu ne réserves ni ne valides jamais une place ni un paiement ; pas de compte à créer pour candidater.',
        ];

        return implode("\n", $lines);
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'Africa/Lome');
        } catch (Throwable) {
            return null;
        }
    }
}
