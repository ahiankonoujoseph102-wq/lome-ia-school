<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règle de Joseph : jamais « 100 % » ni « sans engagement », « garanti » seulement dans une précaution (novembre 2026 :
 * la page de la consultation gratuite et l'inscription en contenaient encore).
 */
class ForbiddenWordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_never_use_forbidden_marketing_words(): void
    {
        $formation = Formation::factory()->create(['price' => 10000]);

        $urls = [
            route('home'), route('formations.index'), route('formations.show', $formation), route('about'),
            route('contact'), route('consultation'), route('business'), route('affiliate.program'), route('register'),
        ];

        foreach ($urls as $url) {
            $text = mb_strtolower(html_entity_decode(strip_tags((string) $this->get($url)->assertOk()->getContent())));
            $text = str_replace(["\u{00A0}", "\u{202F}"], ' ', $text);

            foreach (['100 %', '100%', 'sans engagement'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $text, $url.' : '.$forbidden);
            }
            // « garanti » seulement dans une précaution (« aucun montant n'est garanti »), jamais comme promesse.
            $this->assertDoesNotMatchRegularExpression('/(?<!est )garanti/u', $text, $url.' : garanti');
        }
    }
}
