<?php

namespace App\Support;

use App\Models\Formation;
use Illuminate\Http\Request;

/**
 * Référencement par Google et les autres moteurs de recherche : quelles pages peuvent l'être.
 *
 * Liste fermée : seules les pages publiques de INDEXABLE_ROUTES, ouvertes sans paramètre d'adresse,
 * peuvent être référencées. Tout le reste (compte, connexion, paiement, espace élève, lecteur de cours,
 * admin, groupes, liens d'affiliation, pages avec un jeton, toute nouvelle page) reçoit « noindex »,
 * même quand NOINDEX est désactivé. NOINDEX=true (adresse provisoire) bloque tout le site.
 */
class SearchEngines
{
    /**
     * Pages publiques référençables (noms des routes) ; ce sont aussi les pages du plan du site.
     */
    public const array INDEXABLE_ROUTES = [
        'home',
        'formations.index',
        'formations.show',
        'about',
        'business',
        'contact',
        'consultation',
        'affiliate.program',
        'legal.notice',
        'legal.terms',
        'legal.privacy',
    ];

    /**
     * Débuts d'adresses interdits aux robots dans robots.txt (pages réservées aux comptes ou à un lien secret).
     * Connexion, inscription, mot de passe oublié et récapitulatif de commande n'y sont volontairement pas :
     * ils sont liés depuis toutes les pages, et Google doit pouvoir les lire pour voir leur « noindex »
     * (une page interdite dans robots.txt peut quand même apparaître, sans description, dans les résultats).
     */
    public const array ROBOTS_DISALLOW = [
        '/admin',
        '/mon-espace',
        '/moderation',
        '/dashboard',
        '/settings',
        '/user/',
        '/two-factor-challenge',
        '/reset-password/',
        '/confirmer-email/',
        '/consultation-gratuite/rendez-vous/',
        '/formations/*/lecons/',
        '/assistante/',
        '/broadcasting/',
        '/livewire',
        '/*?ref=',
        '/*&ref=',
    ];

    /**
     * Le site peut-il être référencé ? Jamais hors production ni avec NOINDEX=true.
     */
    public static function siteIndexable(): bool
    {
        return app()->isProduction() && ! config('app.noindex');
    }

    /**
     * Directive « robots » de la réponse : null si la page peut être référencée.
     * Une page publique ouverte avec des paramètres (filtre du catalogue, lien d'affiliation…) n'est pas
     * référencée, mais ses liens restent suivis ; son adresse canonique est la page sans paramètre.
     */
    public static function directive(Request $request): ?string
    {
        // robots.txt et sitemap.xml sont lus par les robots, jamais affichés dans les résultats.
        if (! config('app.noindex') && in_array($request->route()?->getName(), ['robots', 'sitemap'], true)) {
            return null;
        }

        if (config('app.noindex') || ! self::isPublicRoute($request)) {
            return 'noindex, nofollow';
        }

        return $request->query() === [] ? null : 'noindex, follow';
    }

    public static function isPublicRoute(Request $request): bool
    {
        return in_array($request->route()?->getName(), self::INDEXABLE_ROUTES, true);
    }

    /**
     * Pages du plan du site (sitemap.xml) : pages publiques et formations visibles, adresses fondées sur APP_URL.
     *
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    public static function sitemapUrls(): array
    {
        $pages = collect(['home', 'formations.index', 'about', 'business', 'contact', 'consultation', 'affiliate.program', 'legal.notice', 'legal.terms', 'legal.privacy'])
            ->map(fn (string $name): array => ['loc' => AppUrl::to(route($name)), 'lastmod' => null]);

        $formations = Formation::published()->get()->map(fn (Formation $formation): array => [
            'loc' => AppUrl::to(route('formations.show', $formation)),
            'lastmod' => $formation->updated_at?->toAtomString(),
        ]);

        return $pages->concat($formations)->values()->all();
    }
}
