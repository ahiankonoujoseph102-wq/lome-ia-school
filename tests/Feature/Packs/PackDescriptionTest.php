<?php

namespace Tests\Feature\Packs;

use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Description d'une offre sur /accompagnement : retours à la ligne gardés, lignes à tiret en puces,
 * texte toujours échappé.
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

    private function descriptionHtml(): string
    {
        $html = (string) $this->get(route('packs'))->assertOk()->getContent();
        $start = strpos($html, 'id="'.$this->pack->slug.'"');
        $this->assertNotFalse($start);
        $from = (int) strpos($html, 'data-test="pack-description"', $start);

        return substr($html, $from, (int) strpos($html, 'Ce qui est inclus', $from) - $from);
    }

    public function test_line_breaks_and_dashes_are_kept(): void
    {
        $this->pack->update(['description' => "Pour débuter avec l'IA.\n- Des affiches professionnelles\n- Des vidéos publicitaires\nFormations enregistrées.\nÀ votre rythme."]);

        $html = $this->descriptionHtml();

        $this->assertMatchesRegularExpression('#<p>\s*Pour débuter avec l&\#039;IA.\s*</p>#u', $html);
        $this->assertMatchesRegularExpression('#<ul[^>]*>\s*<li[^>]*>.*Des affiches professionnelles</span></li>\s*<li[^>]*>.*Des vidéos publicitaires</span></li>\s*</ul>#su', $html);
        $this->assertMatchesRegularExpression('#Formations enregistrées.<br>\s*À votre rythme.#u', $html);
        $this->assertStringNotContainsString('- Des affiches', $html);
    }

    public function test_html_typed_in_the_description_is_never_interpreted(): void
    {
        $this->pack->update(['description' => "<script>alert('x')</script>\n- <b>gras</b>\n<img src=x onerror=alert(1)>"]);

        $html = $this->descriptionHtml();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<b>gras</b>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;gras&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_a_description_in_one_block_still_shows_as_before(): void
    {
        $html = $this->descriptionHtml();

        $this->assertStringContainsString(e($this->pack->description), $html);
        $this->assertStringNotContainsString('<ul', $html);
    }
}
