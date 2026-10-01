<?php

namespace Tests\Feature\Site;

use App\Models\CourseModule;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\Purchase;
use App\Models\User;
use App\Support\SearchEngines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Référencement par Google : NOINDEX, pages privées toujours bloquées, plan du site, robots.txt,
 * balises de chaque page publique et adresses fondées sur APP_URL.
 */
class SearchEnginesTest extends TestCase
{
    use RefreshDatabase;

    private const string DOMAIN = 'https://lomeiaschool.com';

    private Formation $agents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agents = Formation::factory()->create(['title' => 'Agents IA', 'slug' => 'creation-agents-ia']);
    }

    /**
     * Site définitif : production, NOINDEX=false, APP_URL sur le nom de domaine (la requête arrive par une autre adresse).
     */
    private function finalSite(): void
    {
        config(['app.noindex' => false, 'app.test_banner' => false, 'app.url' => self::DOMAIN]);
        $this->app['env'] = 'production';
    }

    /**
     * @return array<string, string> Adresse de chaque page publique (hors formation) => titre attendu dans <title>.
     */
    private function publicPages(): array
    {
        return [
            route('home') => 'Lomé IA School | Formations en intelligence artificielle',
            route('formations.index') => 'Toutes nos formations en intelligence artificielle | Lomé IA School',
            route('formations.show', $this->agents) => 'Agents IA | Lomé IA School',
            route('about') => 'À propos de Lomé IA School',
            route('business') => 'Entreprises et centres de formation | Lomé IA School',
            route('contact') => 'Contact | Lomé IA School',
            route('consultation') => 'Consultation gratuite | Lomé IA School',
            route('affiliate.program') => 'Devenez affilié | Lomé IA School',
            route('legal.notice') => 'Mentions légales | Lomé IA School',
            route('legal.terms') => 'Conditions générales de vente | Lomé IA School',
            route('legal.privacy') => 'Politique de confidentialité | Lomé IA School',
        ];
    }

    public function test_noindex_false_lets_google_index_public_pages_with_complete_tags(): void
    {
        $this->finalSite();
        $titles = [];
        $descriptions = [];

        foreach ($this->publicPages() as $url => $expectedTitle) {
            $response = $this->get($url)->assertOk()->assertHeaderMissing('X-Robots-Tag');
            $html = (string) $response->getContent();
            $path = (string) parse_url($url, PHP_URL_PATH);
            $canonical = rtrim(self::DOMAIN.$path, '/');
            $canonical = $canonical === self::DOMAIN ? self::DOMAIN : $canonical;

            $this->assertStringNotContainsString('<meta name="robots"', $html, $url);
            $this->assertMatchesRegularExpression('#<title>[^<]*'.preg_quote(e($expectedTitle), '#').'#', $html, $url);
            $this->assertStringContainsString('<link rel="canonical" href="'.$canonical.'">', $html, $url);
            $this->assertStringContainsString('<meta property="og:url" content="'.$canonical.'">', $html, $url);
            $this->assertStringContainsString('<meta property="og:image" content="'.self::DOMAIN.'/', $html, $url);
            $this->assertStringContainsString('<meta property="og:title"', $html, $url);
            $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html, $url);

            preg_match('#<title>(.*?)</title>#s', $html, $title);
            preg_match('#<meta name="description" content="([^"]+)">#', $html, $description);
            $this->assertNotEmpty($description[1] ?? null, $url);
            $titles[] = $title[1];
            $descriptions[] = $description[1];
        }

        $this->assertSame($titles, array_values(array_unique($titles)), 'Chaque page a son propre titre.');
        $this->assertSame($descriptions, array_values(array_unique($descriptions)), 'Chaque page a sa propre description.');
    }

    public function test_private_pages_stay_blocked_even_when_noindex_is_false(): void
    {
        $this->finalSite();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->create();
        $lesson = Lesson::factory()->for(CourseModule::factory()->for($this->agents), 'module')->create();

        $asVisitor = [
            route('login'),
            route('register'),
            route('password.request'),
            route('password.reset', 'jeton'),
            route('two-factor.login'),
            route('checkout.show', $this->agents),
            route('lessons.show', [$this->agents, $lesson]),
            route('consultation.manage', Str::random(48)),
            route('email-confirmation.link', 'jeton'),
            route('dashboard'),
            route('admin.index'),
            route('chat.show', $this->agents),
        ];

        foreach ($asVisitor as $url) {
            $this->assertSame('noindex, nofollow', $this->get($url)->headers->get('X-Robots-Tag'), $url);
        }

        // Pages ouvertes avec un compte : espace élève, achat (page de remerciement), groupe, lecteur, admin.
        $asStudent = [route('dashboard'), route('student.purchases.index'), route('student.purchases.show', $purchase), route('student.affiliate.index'), route('student.profile')];
        foreach ($asStudent as $url) {
            $html = (string) $this->actingAs($student)->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->getContent();
            $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html, $url);
        }

        foreach ([route('admin.index'), route('admin.payments.index'), route('chat.show', $this->agents), route('lessons.show', [$this->agents, $lesson]), route('moderation.reports.index')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_affiliate_links_and_filtered_pages_are_not_indexed(): void
    {
        $this->finalSite();

        // Lien d'affiliation : redirigé vers la page sans le code, et jamais référencé.
        $this->get(route('formations.show', [$this->agents, 'ref' => 'ABC123']))->assertHeader('X-Robots-Tag', 'noindex, follow');

        // Catalogue filtré (recherche interne) : pas référencé, liens suivis, adresse canonique = page sans paramètre.
        $html = (string) $this->get(route('formations.index', ['categorie' => 'agents']))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, follow')
            ->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.self::DOMAIN.'/formations">', $html);
    }

    public function test_robots_txt_allows_public_pages_blocks_private_ones_and_announces_the_sitemap(): void
    {
        $this->finalSite();

        $robots = (string) $this->get(route('robots'))->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();

        $this->assertStringNotContainsString("Disallow: /\n", $robots);
        $this->assertStringContainsString("User-agent: *\nAllow: /\n", $robots);
        foreach (['/admin', '/mon-espace', '/moderation', '/formations/*/lecons/', '/consultation-gratuite/rendez-vous/', '/confirmer-email/', '/*?ref='] as $path) {
            $this->assertStringContainsString("Disallow: {$path}\n", $robots);
        }
        // Google doit pouvoir lire le « noindex » de la connexion, de l'inscription et de la commande.
        $this->assertStringNotContainsString('Disallow: /login', $robots);
        $this->assertStringNotContainsString('Disallow: /commande', $robots);
        // Aperçus WhatsApp et Facebook toujours permis.
        $this->assertStringContainsString("User-agent: WhatsApp\n", $robots);
        $this->assertStringContainsString('Sitemap: '.self::DOMAIN."/sitemap.xml\n", $robots);
    }

    public function test_noindex_true_blocks_the_whole_site(): void
    {
        $this->finalSite();
        config(['app.noindex' => true]);

        foreach (array_keys($this->publicPages()) as $url) {
            $this->get($url)->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }

        $robots = (string) $this->get(route('robots'))->getContent();
        $this->assertStringContainsString("User-agent: *\nDisallow: /\n", $robots);
        $this->assertStringNotContainsString('Sitemap:', $robots);
    }

    public function test_sitemap_lists_only_public_pages_with_app_url_addresses(): void
    {
        $this->finalSite();
        $hidden = Formation::factory()->inactive()->create();

        $xml = (string) $this->get(route('sitemap'))->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();
        preg_match_all('#<loc>(.*?)</loc>#', $xml, $matches);
        $locs = $matches[1];

        $expected = ['', '/formations', '/a-propos', '/entreprises', '/contact', '/consultation-gratuite', '/devenir-affilie', '/mentions-legales', '/conditions-generales-de-vente', '/politique-de-confidentialite', '/formations/creation-agents-ia'];
        foreach ($expected as $path) {
            $this->assertContains(self::DOMAIN.$path, $locs, $path ?: '/');
        }

        $this->assertCount(count($expected), $locs);
        $this->assertNotContains(self::DOMAIN.'/formations/'.$hidden->slug, $locs);
        foreach ($locs as $loc) {
            $this->assertStringStartsWith(self::DOMAIN, $loc);
        }
    }

    public function test_every_indexable_page_is_in_the_sitemap(): void
    {
        $sitemap = collect(SearchEngines::sitemapUrls())->pluck('loc');

        foreach (SearchEngines::INDEXABLE_ROUTES as $name) {
            $url = $name === 'formations.show' ? route($name, $this->agents) : route($name);
            $this->assertContains($url, $sitemap, $name);
        }
    }

    public function test_outside_production_nothing_is_indexed(): void
    {
        config(['app.noindex' => false]);

        $this->get(route('robots'))->assertSee("User-agent: *\nDisallow: /", false);
    }

    public function test_noindex_values(): void
    {
        foreach (['false' => false, 'true' => true, '' => false, '0' => false, '1' => true] as $value => $expected) {
            putenv("NOINDEX={$value}");
            $_ENV['NOINDEX'] = $_SERVER['NOINDEX'] = (string) $value;
            $this->assertSame($expected, (require config_path('app.php'))['noindex'], "NOINDEX={$value}");
        }

        putenv('NOINDEX');
        unset($_ENV['NOINDEX'], $_SERVER['NOINDEX']);
        $this->assertFalse((require config_path('app.php'))['noindex'], 'NOINDEX absent');
    }
}
