<?php

namespace App\Support;

/**
 * Vidéo de la page d'accueil (réglages « Vidéo d'accueil » de l'admin, clé `site.home_video`).
 *
 * Aucun lien n'est écrit dans le code : sans lien YouTube valide, ou interrupteur éteint, la section
 * n'est pas affichée et rien ne casse. La vidéo est verticale (9:16).
 */
class HomeVideo
{
    public const string DEFAULT_TITLE = 'L\'intelligence artificielle expliquée simplement';

    public const string DEFAULT_SUBTITLE = 'Une courte vidéo de Coach Joseph';

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return (array) config('site.home_video');
    }

    public static function enabled(): bool
    {
        return (bool) (self::settings()['enabled'] ?? false);
    }

    /**
     * Identifiant YouTube, seulement si l'interrupteur est allumé et que le lien est reconnu.
     */
    public static function videoId(): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        return YouTube::id(self::settings()['video_url'] ?? null);
    }

    public static function visible(): bool
    {
        return self::videoId() !== null;
    }

    public static function title(): string
    {
        $title = trim((string) (self::settings()['title'] ?? ''));

        return $title !== '' ? $title : self::DEFAULT_TITLE;
    }

    public static function subtitle(): string
    {
        $subtitle = trim((string) (self::settings()['subtitle'] ?? ''));

        return $subtitle !== '' ? $subtitle : self::DEFAULT_SUBTITLE;
    }

    /**
     * Lien TikTok (facultatif) : adresse https vers tiktok.com, sinon rien (le bouton est alors masqué).
     */
    public static function tiktokUrl(): ?string
    {
        $url = PastedLink::clean(self::settings()['tiktok_url'] ?? null);

        return self::isTikTokUrl($url) ? $url : null;
    }

    public static function isTikTokUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '' || ! str_starts_with($url, 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'tiktok.com' || str_ends_with($host, '.tiktok.com');
    }

    public static function tiktokInvalidMessage(): string
    {
        return 'Le lien TikTok doit ressembler à https://www.tiktok.com/@votre-compte (ou un lien vm.tiktok.com copié depuis TikTok).';
    }

    /**
     * Lien de secours « Regarder sur YouTube », ouvert dans un nouvel onglet.
     */
    public static function watchUrl(string $id): string
    {
        return 'https://www.youtube.com/watch?v='.$id;
    }

    /**
     * Lecteur intégré, chargé seulement au toucher : domaine youtube-nocookie.com, lecture lancée par ce toucher
     * (jamais avant), pas de vidéos suggérées d'autres chaînes, et API de lecteur activée pour repérer une vidéo illisible.
     */
    public static function embedUrl(string $id): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0&playsinline=1&enablejsapi=1';
    }
}
