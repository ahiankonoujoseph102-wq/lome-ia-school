<?php

namespace Tests\Feature\Packs;

use App\Models\Formation;
use App\Packs\PackSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Description d'une offre sur /accompagnement : première ligne = accroche, lignes à tiret = points forts (quatre
 * au plus), le reste replié sous « Voir tout ce qui est inclus » ; retours à la ligne gardés, texte toujours échappé.
 */
class PackDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private Formation $pack;

    protected function setUp(): void
    {
        parent::setUp();

        Formation::query()->where('kind', 'pack')->update(['is_active' => true]);
        $this->pack = Formation::query()->where('slug', 'accompagnement-ia-niveau-1')->sole();
    }

    /**
     * HTML de la carte de l'offre (jusqu'à la carte suivante).
     */
    private function cardHtml(): string
    {
        $html = (string) $this->get(route('packs'))->assertOk()->getContent();
        $start = strpos($html, 'id="'.$this->pack->slug.'"');
        $this->assertNotFalse($start);
        $own = (int) strpos($html, 'data-test="pack-card"', $start);
        $end = strpos($html, 'data-test="pack-card"', $own + 1);

        return substr($html, $start, ($end === false ? strlen($html) : $end) - $start);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = (int) strpos($html, $from);

        return substr($html, $start, (int) strpos($html, $to, $start) - $start);
    }

    public function test_the_first_line_is_the_hook_and_dashes_are_the_highlights(): void
    {
        $this->pack->update(['description' => "Pour débuter avec l'IA.\n- Des affiches professionnelles\n- Des vidéos publicitaires\nFormations enregistrées.\nÀ votre rythme."]);

        $card = $this->cardHtml();
        $summary = $this->between($card, 'data-test="pack-description"', 'data-test="pack-choose"');

        $this->assertMatchesRegularExpression('#<p[^>]*>\s*Pour débuter avec l&\#039;IA.\s*</p>#u', $summary);
        $this->assertMatchesRegularExpression('#<ul[^>]*data-test="pack-points"[^>]*>\s*<li[^>]*>.*Des affiches professionnelles</span></li>\s*<li[^>]*>.*Des vidéos publicitaires</span></li>\s*</ul>#su', $summary);
        $this->assertStringNotContainsString('- Des affiches', $card);
        $this->assertStringNotContainsString('Formations enregistrées', $summary);

        // Le reste de la description n'est pas perdu : replié sous « Voir tout ce qui est inclus », retours à la ligne gardés.
        $more = $this->between($card, 'data-test="pack-more"', '</details>');
        $this->assertMatchesRegularExpression('#Formations enregistrées.<br>\s*À votre rythme.#u', $more);
    }

    public function test_at_most_four_highlights_are_shown_and_the_others_are_folded(): void
    {
        $this->pack->update(['description' => "Accroche.\n- Un\n- Deux\n- Trois\n- Quatre\n- Cinq"]);

        $card = $this->cardHtml();
        $points = $this->between($card, 'data-test="pack-points"', '</ul>');

        $this->assertSame(4, substr_count($points, '<li'));
        $this->assertStringNotContainsString('Cinq', $points);
        $this->assertStringContainsString('Cinq', $this->between($card, 'data-test="pack-more"', '</details>'));
    }

    public function test_html_typed_in_the_description_is_never_interpreted(): void
    {
        $this->pack->update(['description' => "<script>alert('x')</script>\n- <b>gras</b>\n<img src=x onerror=alert(1)>"]);

        $html = $this->cardHtml();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<b>gras</b>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;gras&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_a_description_in_one_block_still_shows_in_full(): void
    {
        $this->pack->update(['description' => PackSetup::PACKS[0]['description']]);

        $summary = $this->between($this->cardHtml(), 'data-test="pack-description"', 'data-test="pack-choose"');

        $this->assertStringContainsString(e($this->pack->description), $summary);
        $this->assertStringNotContainsString('<ul', $summary);
    }
}
