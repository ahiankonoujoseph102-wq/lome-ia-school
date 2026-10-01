<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\AppUrl;
use App\Support\SearchEngines;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Robots qui construisent l'aperçu d'un lien partagé (image, titre, description).
     */
    public const array PREVIEW_BOTS = ['facebookexternalhit', 'Facebot', 'meta-externalagent', 'WhatsApp', 'Twitterbot', 'LinkedInBot', 'TelegramBot'];

    /**
     * Plan du site : uniquement les pages publiques (SearchEngines::sitemapUrls), adresses fondées sur APP_URL.
     */
    public function sitemap(): Response
    {
        return response()
            ->view('site.sitemap', ['urls' => SearchEngines::sitemapUrls()])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        // Les robots d'aperçu des partages (WhatsApp, Facebook…) sont toujours autorisés : ils ne référencent
        // rien et affichent l'image, le titre et la description d'un lien partagé.
        $previewBots = [...array_map(fn (string $bot): string => 'User-agent: '.$bot, self::PREVIEW_BOTS), 'Allow: /', ''];

        // Site référençable (production, NOINDEX désactivé) : tout est permis sauf les pages privées, et le
        // plan du site est annoncé. Sinon (NOINDEX=true, adresse provisoire, ou hors production) : tout est bloqué.
        $lines = SearchEngines::siteIndexable()
            ? [
                ...$previewBots,
                'User-agent: *',
                'Allow: /',
                ...array_map(fn (string $path): string => 'Disallow: '.$path, SearchEngines::ROBOTS_DISALLOW),
                '',
                'Sitemap: '.AppUrl::to(route('sitemap')),
            ]
            : [...$previewBots, 'User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
