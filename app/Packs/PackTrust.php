<?php

namespace App\Packs;

use App\Settings\SiteSettings;
use App\Support\Founder;
use App\Support\PhoneNumber;
use App\Support\WhatsApp;

/**
 * Bloc « Pourquoi nous faire confiance » de la page /accompagnement.
 *
 * Fait uniquement de faits déjà présents dans les réglages du site (modifiables dans l'admin), jamais écrits ici :
 * parcours de Coach Joseph (page Joseph Ahiankonou, « Son parcours »), adresse et horaires du bureau
 * (Coordonnées), WhatsApp de l'école, règle de remboursement (Pages légales) et attestation de participation
 * (« Dans toutes les offres » de la page Accompagnement). Une information absente, vide ou qui ne ressemble plus à
 * ce qu'elle doit dire est simplement laissée de côté (missing()) : rien n'est remplacé par un texte inventé.
 * Jamais de promesse de revenus ou de résultats, de note ni de logo ; jamais « certificat » ni « diplôme ».
 */
class PackTrust
{
    /**
     * Ligne du parcours : « Formateur en intelligence artificielle depuis 2023. »
     */
    public const string TRAINER_PATTERN = '/^formateur en (intelligence artificielle|IA) depuis \d{4}\b/iu';

    /**
     * Ligne du parcours : « Plus de 1 000 personnes formées. »
     */
    public const string PEOPLE_PATTERN = '/^plus de \d[\d\s\x{00A0}\x{202F}.]*\s+personnes formées\b/iu';

    /**
     * Mots jamais affichés à propos de l'attestation (règle du projet).
     */
    public const string FORBIDDEN_PATTERN = '/certifi|dipl[oô]m|agré|reconnu/iu';

    /**
     * Noms des informations attendues (pour la liste des informations manquantes).
     *
     * @var array<string, string>
     */
    public const array LABELS = [
        'journey' => 'Parcours de Coach Joseph (formateur en IA depuis…, plus de … personnes formées)',
        'office' => 'Adresse et horaires du bureau',
        'whatsapp' => 'Numéro WhatsApp de l\'école',
        'refund' => 'Règle de remboursement des conditions de vente',
        'attestation' => 'Attestation de participation',
    ];

    /**
     * Faits à afficher, dans l'ordre ; une information manquante n'a pas de ligne.
     *
     * @return list<array{key: string, icon: string, title: string, lines: list<string>, link: array{url: string, label: string, external: bool}|null}>
     */
    public static function facts(): array
    {
        return array_values(array_filter([
            self::journey(),
            self::office(),
            self::whatsapp(),
            self::refund(),
            self::attestation(),
        ]));
    }

    /**
     * Informations attendues qui ne figurent pas (ou plus) dans les réglages, donc non affichées.
     *
     * @return list<string>
     */
    public static function missing(): array
    {
        $shown = array_column(self::facts(), 'key');

        return array_values(array_diff(array_keys(self::LABELS), $shown));
    }

    public static function founderUrl(): string
    {
        return route('founder');
    }

    /**
     * @return array{key: string, icon: string, title: string, lines: list<string>, link: array{url: string, label: string, external: bool}|null}|null
     */
    private static function journey(): ?array
    {
        $journey = Founder::lines('journey');
        $trainer = self::firstMatching($journey, self::TRAINER_PATTERN);
        $people = self::firstMatching($journey, self::PEOPLE_PATTERN);

        if ($trainer === null || $people === null) {
            return null;
        }

        $nickname = Founder::text('nickname');

        return [
            'key' => 'journey',
            'icon' => 'academic-cap',
            'title' => $nickname !== '' ? $nickname : Founder::text('name'),
            'lines' => [self::sentence($trainer), self::sentence($people)],
            'link' => null,
        ];
    }

    /**
     * @return array{key: string, icon: string, title: string, lines: list<string>, link: array{url: string, label: string, external: bool}|null}|null
     */
    private static function office(): ?array
    {
        $address = self::string(config('site.contact.address'));
        $hours = self::string(config('site.contact.hours'));

        if ($address === null || $hours === null || self::isPlaceholder($address) || self::isPlaceholder($hours)) {
            return null;
        }

        return [
            'key' => 'office',
            'icon' => 'map-pin',
            'title' => 'Notre bureau',
            'lines' => [self::sentence($address), self::sentence($hours)],
            'link' => null,
        ];
    }

    /**
     * @return array{key: string, icon: string, title: string, lines: list<string>, link: array{url: string, label: string, external: bool}|null}|null
     */
    private static function whatsapp(): ?array
    {
        $number = preg_replace('/\D/', '', (string) config('site.whatsapp.number'));

        if ($number === null || strlen($number) < 8) {
            return null;
        }

        return [
            'key' => 'whatsapp',
            'icon' => 'whatsapp',
            'title' => 'WhatsApp',
            'lines' => [PhoneNumber::format('+'.$number)],
            'link' => ['url' => WhatsApp::url(), 'label' => 'Nous écrire sur WhatsApp', 'external' => true],
        ];
    }

    /**
     * Première phrase de la règle de remboursement des conditions de vente, telle qu'elle y est écrite.
     *
     * @return array{key: string, icon: string, title: string, lines: list<string>, link: array{url: string, label: string, external: bool}|null}|null
     */
    private static function refund(): ?array
    {
        $policy = self::string(config('site.legal.refund_policy'));

        if ($policy === null || self::isPlaceholder($policy) || ! preg_match('/rembours/iu', $policy)) {
            return null;
        }

        $sentences = preg_split('/(?<=[.!?])\s+/u', $policy, 2);
        $first = $sentences === false ? $policy : $sentences[0];

        return [
            'key' => 'refund',
            'icon' => 'arrow-uturn-left',
            'title' => 'Remboursement',
            // Phrase des conditions de vente, gardée telle quelle (avec son point final).
            'lines' => [Founder::display(trim($first))],
            'link' => ['url' => route('legal.terms'), 'label' => 'Lire les conditions de vente', 'external' => false],
        ];
    }

    /**
     * Ligne « Une attestation de participation à la fin » de « Dans toutes les offres » (page Accompagnement).
     *
     * @return array{key: string, icon: string, title: string, lines: list<string>, link: array{url: string, label: string, external: bool}|null}|null
     */
    private static function attestation(): ?array
    {
        $page = (array) config('site.packs_page') + (array) SiteSettings::default('site.packs_page');

        foreach ((array) ($page['common'] ?? []) as $item) {
            $title = self::string(is_array($item) ? ($item['title'] ?? null) : null);

            if ($title !== null && preg_match('/attestation de participation/iu', $title) && ! preg_match(self::FORBIDDEN_PATTERN, $title)) {
                return [
                    'key' => 'attestation',
                    'icon' => 'document-check',
                    'title' => 'Attestation de participation',
                    'lines' => [self::sentence($title)],
                    'link' => null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function firstMatching(array $lines, string $pattern): ?string
    {
        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match($pattern, $line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Texte prêt à afficher : sans point final (lignes courtes), espaces insécables (« 1 000 », « : »).
     */
    private static function sentence(string $text): string
    {
        return Founder::display(rtrim(trim($text), '.'));
    }

    private static function string(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * Emplacement encore à remplir (« [Adresse à compléter] ») : jamais affiché ici.
     */
    private static function isPlaceholder(string $text): bool
    {
        return str_contains($text, '[') && str_contains($text, ']');
    }
}
