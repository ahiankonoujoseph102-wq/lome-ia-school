<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Robots qui construisent l'aperçu d'un lien partagé (image, titre, description).
     */
    public const array PREVIEW_BOTS = ['facebookexternalhit', 'Facebot', 'meta-externalagent', 'WhatsApp', 'Twitterbot', 'LinkedInBot', 'TelegramBot'];

    public function sitemap(): Response
    {
        $urls = collect([
            route('home'),
            route('formations.index'),
            route('consultation'),
            route('affiliate.program'),
            route('business'),
            route('about'),
            route('contact'),
        ])->map(fn (string $url): array => ['loc' => $url, 'lastmod' => null]);

        $formations = Formation::published()->get()->map(fn (Formation $formation): array => [
            'loc' => route('formations.show', $formation),
            'lastmod' => $formation->updated_at?->toAtomString(),
        ]);

        return response()
            ->view('site.sitemap', ['urls' => $urls->concat($formations)])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        // NOINDEX=true (adresse provisoire, par exemple) : jamais référencé, même en production.
        // Les robots d'aperçu des partages (WhatsApp, Facebook…) restent autorisés : ils ne référencent
        // rien et affichent l'image, le titre et la description d'un lien partagé.
        $lines = app()->isProduction() && ! config('app.noindex')
            ? ['User-agent: *', 'Disallow: /dashboard', 'Disallow: /settings', '', 'Sitemap: '.route('sitemap')]
            : [...array_map(fn (string $bot): string => 'User-agent: '.$bot, self::PREVIEW_BOTS), 'Allow: /', '', 'User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
