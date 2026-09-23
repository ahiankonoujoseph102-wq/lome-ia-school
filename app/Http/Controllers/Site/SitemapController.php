<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Formation;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([
            route('home'),
            route('formations.index'),
            route('consultation'),
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
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /dashboard', 'Disallow: /settings', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
