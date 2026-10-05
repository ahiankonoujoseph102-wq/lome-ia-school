<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Page personnelle de Joseph Ahiankonou (/joseph-ahiankonou) : textes, photo, liens et données structurées.
 *
 * Tout vient des réglages (config site.founder, modifiables dans l'admin). Un lien vide ou d'un autre site
 * que celui attendu est ignoré : jamais affiché, jamais envoyé à Google (sameAs).
 */
class Founder
{
    public const string PHOTO_DIRECTORY = 'site/founder';

    public const int PHOTO_SIZE = 800;

    /**
     * Réseaux proposés dans l'admin : libellé et exemple d'adresse.
     *
     * @var array<string, array{label: string, example: string}>
     */
    public const array NETWORKS = [
        'youtube' => ['label' => 'YouTube', 'example' => 'https://www.youtube.com/@votre-chaine'],
        'tiktok' => ['label' => 'TikTok', 'example' => 'https://www.tiktok.com/@votre-compte'],
        'facebook' => ['label' => 'Facebook', 'example' => 'https://www.facebook.com/votre-page'],
    ];

    public const string CHANNEL_EXAMPLE = 'https://whatsapp.com/channel/…';

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return (array) config('site.founder');
    }

    /**
     * Texte prêt à afficher : espace insécable dans les nombres (« 1 000 ») et devant « : ; ? ! » et « », pour qu'une
     * ligne ne se coupe jamais au milieu sur téléphone.
     */
    public static function display(string $text): string
    {
        $text = (string) preg_replace('/(\d) (\d{3})(?!\d)/u', "$1\u{00A0}$2", $text);
        $text = (string) preg_replace('/ ([:;?!»])/u', "\u{00A0}$1", $text);

        return (string) preg_replace('/« /u', "«\u{00A0}", $text);
    }

    public static function text(string $key): string
    {
        return trim((string) (self::settings()[$key] ?? ''));
    }

    /**
     * @return list<string>
     */
    public static function lines(string $key): array
    {
        return array_values(array_filter(array_map(fn ($line): string => trim((string) $line), (array) (self::settings()[$key] ?? [])), fn (string $line): bool => $line !== ''));
    }

    /**
     * @return list<array{title: string, text: string}>
     */
    public static function services(): array
    {
        $services = [];

        foreach ((array) (self::settings()['services'] ?? []) as $service) {
            $title = trim((string) ($service['title'] ?? ''));

            if ($title !== '') {
                $services[] = ['title' => $title, 'text' => trim((string) ($service['text'] ?? ''))];
            }
        }

        return $services;
    }

    public static function url(): string
    {
        return AppUrl::to(route('founder'));
    }

    /**
     * Photo envoyée depuis l'admin, sinon celle du formateur déjà sur le site (À propos), sinon null (initiales).
     */
    public static function photoUrl(): ?string
    {
        $uploaded = FileStorage::publicUrl(self::text('photo'));

        if ($uploaded !== null) {
            return $uploaded;
        }

        $trainer = (string) config('site.trainer.photo');

        return $trainer !== '' && is_file(public_path($trainer)) ? asset($trainer) : null;
    }

    /**
     * Contenu de la photo (pour l'image de partage), ou null.
     */
    public static function photoBytes(): ?string
    {
        $uploaded = self::text('photo');

        if ($uploaded !== '') {
            try {
                $bytes = Storage::disk('public')->get($uploaded);

                if (is_string($bytes) && $bytes !== '') {
                    return $bytes;
                }
            } catch (Throwable) {
                // Fichier introuvable : photo du formateur.
            }
        }

        $trainer = public_path((string) config('site.trainer.photo'));

        return is_file($trainer) ? ((string) file_get_contents($trainer) ?: null) : null;
    }

    public static function initials(): string
    {
        return collect(explode(' ', self::text('name')))->map(fn (string $part): string => mb_substr($part, 0, 1))->take(2)->implode('');
    }

    /**
     * Liens valides saisis dans l'admin : réseaux puis chaînes WhatsApp.
     *
     * @return list<array{network: string, label: string, url: string}>
     */
    public static function links(): array
    {
        $links = [];

        foreach (self::NETWORKS as $network => $meta) {
            $url = PastedLink::clean((string) (((array) (self::settings()['links'] ?? []))[$network] ?? ''));

            if (self::isValidLink($network, $url)) {
                $links[] = ['network' => $network, 'label' => $meta['label'], 'url' => $url];
            }
        }

        $channels = array_values(array_filter(
            array_map(fn ($url): string => PastedLink::clean((string) $url), (array) (self::settings()['whatsapp_channels'] ?? [])),
            fn (string $url): bool => self::isValidLink('whatsapp_channel', $url),
        ));

        foreach ($channels as $index => $url) {
            $links[] = ['network' => 'whatsapp_channel', 'label' => count($channels) > 1 ? 'Chaîne WhatsApp '.($index + 1) : 'Chaîne WhatsApp', 'url' => $url];
        }

        return $links;
    }

    /**
     * Adresse https du bon site pour ce réseau (YouTube, TikTok, Facebook, chaîne WhatsApp).
     */
    public static function isValidLink(string $network, ?string $url): bool
    {
        if (! is_string($url) || ! str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $on = fn (string ...$domains): bool => collect($domains)->contains(fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain));

        return match ($network) {
            'youtube' => $on('youtube.com', 'youtu.be'),
            'tiktok' => $on('tiktok.com'),
            'facebook' => $on('facebook.com', 'fb.com', 'fb.me'),
            'whatsapp_channel' => $on('whatsapp.com') && preg_match('#^/channel/[A-Za-z0-9_-]+#', $path) === 1,
            default => false,
        };
    }

    public static function invalidLinkMessage(string $network): string
    {
        return $network === 'whatsapp_channel'
            ? 'Le lien d\'une chaîne WhatsApp doit ressembler à '.self::CHANNEL_EXAMPLE.' (lien « Copier le lien » de la chaîne).'
            : 'Le lien '.self::NETWORKS[$network]['label'].' doit ressembler à '.self::NETWORKS[$network]['example'].'.';
    }

    /**
     * Lien wa.me vers le numéro WhatsApp de la page (message des « autres pages »).
     */
    public static function whatsappUrl(): ?string
    {
        $digits = (string) preg_replace('/\D/', '', self::contact('whatsapp'));

        return strlen($digits) >= 8 ? 'https://wa.me/'.$digits.'?text='.rawurlencode(WhatsAppMessages::general()) : null;
    }

    public static function contact(string $key): string
    {
        return trim((string) (((array) (self::settings()['contact'] ?? []))[$key] ?? ''));
    }

    public static function organizationId(): string
    {
        return AppUrl::to(route('home')).'#organization';
    }

    public static function personId(): string
    {
        return self::url().'#person';
    }

    /**
     * Fiche « Person » réduite, pour relier l'organisation à son fondateur (accueil).
     *
     * @return array<string, string>
     */
    public static function personReference(): array
    {
        return ['@type' => 'Person', '@id' => self::personId(), 'name' => self::text('name'), 'url' => self::url()];
    }

    /**
     * Données structurées de la page : ProfilePage dont l'entité principale est la fiche Person de Joseph,
     * reliée à l'organisation Lomé IA School comme fondateur (founder) et employeur (worksFor).
     *
     * @return array<string, mixed>
     */
    public static function jsonLd(): array
    {
        $person = array_filter([
            '@type' => 'Person',
            '@id' => self::personId(),
            'name' => self::text('name'),
            'alternateName' => self::text('nickname') ?: null,
            'jobTitle' => self::text('job_title') ?: null,
            'description' => self::text('tagline') ?: null,
            'url' => self::url(),
            'image' => ($photo = self::photoUrl()) ? AppUrl::to($photo) : null,
            'email' => self::contact('email') ?: null,
            'telephone' => self::contact('whatsapp') ?: null,
            'homeLocation' => ['@type' => 'Place', 'name' => 'Lomé, Togo', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Lomé', 'addressCountry' => 'TG']],
            'workLocation' => ['@type' => 'Place', 'name' => self::contact('office') ?: 'Lomé, Togo', 'address' => ['@type' => 'PostalAddress', 'streetAddress' => self::contact('office') ?: null, 'addressLocality' => 'Lomé', 'addressCountry' => 'TG']],
            'knowsAbout' => ['Intelligence artificielle', 'Création de sites web', 'Création d\'applications', 'Écriture de livres'],
            'worksFor' => ['@id' => self::organizationId()],
            'sameAs' => array_column(self::links(), 'url') ?: null,
        ], fn ($value): bool => $value !== null);

        $person['workLocation']['address'] = array_filter($person['workLocation']['address'], fn ($value): bool => $value !== null);

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'ProfilePage',
                    '@id' => self::url(),
                    'url' => self::url(),
                    'name' => self::text('title'),
                    'inLanguage' => 'fr',
                    'mainEntity' => ['@id' => self::personId()],
                    'isPartOf' => ['@type' => 'WebSite', 'name' => config('app.name'), 'url' => AppUrl::to(route('home'))],
                ],
                $person,
                [
                    '@type' => 'EducationalOrganization',
                    '@id' => self::organizationId(),
                    'name' => config('app.name'),
                    'url' => AppUrl::to(route('home')),
                    'founder' => ['@id' => self::personId()],
                ],
            ],
        ];
    }
}
