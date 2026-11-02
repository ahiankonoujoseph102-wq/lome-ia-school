<?php

namespace Tests\Feature\Site;

use App\Models\AdminActivity;
use App\Models\User;
use App\Support\Founder;
use App\Support\SearchEngines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Page personnelle de Joseph Ahiankonou (/joseph-ahiankonou) : faits fournis par Joseph, titre exact, données
 * structurées Person reliées à Lomé IA School, image de partage, signature en bas des pages, admin.
 */
class FounderPageTest extends TestCase
{
    use RefreshDatabase;

    private const string TITLE = 'Joseph Ahiankonou (Coach Joseph) : formateur et consultant en IA à Lomé';

    private const string DOMAIN = 'https://lomeiaschool.laravel.cloud';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        File::deleteDirectory(storage_path('framework/cache/og'));
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        $founder = (array) config('site.founder');

        return array_replace([
            'name' => $founder['name'],
            'nickname' => $founder['nickname'],
            'title' => $founder['title'],
            'description' => $founder['description'],
            'job_title' => $founder['job_title'],
            'tagline' => $founder['tagline'],
            'intro' => $founder['intro'],
            'journey' => $founder['journey'],
            'services' => array_map(fn (array $service): array => ['question' => $service['title'], 'answer' => $service['text']], $founder['services']),
            'photo_alt' => $founder['photo_alt'],
            'links' => ['youtube' => '', 'tiktok' => '', 'facebook' => ''],
            'whatsapp_channels' => [],
            'contact' => $founder['contact'],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $this->assertCount(1, $matches[1]);

        return json_decode($matches[1][0], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_the_page_shows_the_facts_given_by_joseph_with_its_sections(): void
    {
        $response = $this->get('/joseph-ahiankonou')->assertOk();
        $html = (string) $response->getContent();

        $response->assertSee('<title>'.e(self::TITLE).'</title>', false)
            ->assertSee('<h1', false)
            ->assertSeeInOrder(['data-test="founder-hero"', 'data-test="founder-intro"', 'data-test="founder-journey"', 'data-test="founder-services"', 'data-test="founder-contact"'], false)
            ->assertSee('Coach Joseph')
            ->assertSee('Agoè-Nyivé')
            ->assertSee('formateur depuis 2023')
            ->assertSee("plus de 1\u{00A0}000 personnes")
            ->assertSee('Joseph Academy')
            ->assertSee('écriture de livres pour les autres')
            ->assertSee('sites web et des applications pour des entreprises')
            ->assertSee('>Son parcours</h2>', false)
            ->assertDontSee('Parti de zéro')
            ->assertDontSee('data-test="founder-books"', false)
            ->assertSee('+228 72 24 02 31')
            ->assertSee('academyjoseph71@gmail.com')
            ->assertSee('Adidogomé, Lycée moderne 2, Lomé')
            ->assertSee('https://wa.me/22872240231?text=', false);

        // Parcours strictement professionnel : uniquement les cinq faits donnés par Joseph, dans cet ordre.
        $response->assertSeeInOrder([
            'Formateur en intelligence artificielle depuis 2023.',
            "Plus de 1\u{00A0}000 personnes formées.",
            'Auteur de 6 livres.',
            'Fondateur de Lomé IA School et de Joseph Academy.',
            'Créateur de sites web et d&#039;applications pour des entreprises.',
        ], false);

        // Services : formations en IA, sites web et applications, écriture de livres pour les autres (Joseph Academy).
        $response->assertSeeInOrder(['Formations en intelligence artificielle', 'Création de sites web et d&#039;applications', 'Écriture de livres pour les autres'], false);

        // Aucun lien inventé : sans lien saisi, la rubrique « Le suivre » est absente.
        $response->assertDontSee('data-test="founder-links"', false);

        // Règles : rien de certifié, aucun diplôme, aucune école agréée, aucun témoignage ; l'école n'est pas présentée comme créée en 2023.
        $text = mb_strtolower(strip_tags($html));
        // Ni enfance, ni redoublement, ni aide-maçon, ni premier téléphone ; aucune liste de livres, aucune boutique.
        foreach (['certifi', 'diplôme', 'agréé', 'témoignage', 'créée en 2023', 'fondée en 2023', 'ancien nom', 'redoubl', 'cp.', 'ce1', 'aide-maçon', 'premier téléphone', 'parti de zéro', 'svt', 'importation', 'ses livres', 'prompt power', 'créer ton propre emploi', '70 idées', 'gagner de l\'argent', 'licenciez votre patron', 'pays béni', 'acheter le livre', 'boutique'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text, $forbidden);
        }
    }

    public function test_the_title_is_used_exactly_without_the_site_suffix(): void
    {
        $html = (string) $this->get(route('founder'))->getContent();

        preg_match('#<title>(.*?)</title>#s', $html, $title);
        $this->assertSame(e(self::TITLE), $title[1]);
        $this->assertStringContainsString('<meta property="og:title" content="'.e(self::TITLE).'">', $html);
    }

    public function test_structured_data_describe_joseph_as_a_person_founder_of_the_school(): void
    {
        config(['app.url' => self::DOMAIN]);
        $data = $this->jsonLd((string) $this->get(route('founder'))->getContent());

        $this->assertSame('https://schema.org', $data['@context']);
        $types = array_column($data['@graph'], null, '@type');
        $this->assertSame(['ProfilePage', 'Person', 'EducationalOrganization'], array_keys($types));

        $page = $types['ProfilePage'];
        $person = $types['Person'];
        $school = $types['EducationalOrganization'];

        $this->assertSame(self::DOMAIN.'/joseph-ahiankonou', $page['url']);
        $this->assertSame(['@id' => self::DOMAIN.'/joseph-ahiankonou#person'], $page['mainEntity']);
        $this->assertSame(self::DOMAIN.'/joseph-ahiankonou#person', $person['@id']);
        $this->assertSame('Joseph Ahiankonou', $person['name']);
        $this->assertSame('Coach Joseph', $person['alternateName']);
        $this->assertSame('Formateur et consultant en intelligence artificielle', $person['jobTitle']);
        $this->assertSame('Lomé', $person['homeLocation']['address']['addressLocality']);
        $this->assertSame('TG', $person['homeLocation']['address']['addressCountry']);
        $this->assertSame(self::DOMAIN.'/images/formateur.jpg', $person['image']);
        $this->assertSame(['@id' => self::DOMAIN.'#organization'], $person['worksFor']);
        $this->assertArrayNotHasKey('sameAs', $person, 'Aucun lien saisi : aucun lien inventé.');
        $this->assertSame(self::DOMAIN.'#organization', $school['@id']);
        $this->assertSame('Lomé IA School', $school['name']);
        $this->assertSame(['@id' => $person['@id']], $school['founder']);

        $this->assertOnlySchemaOrgProperties($data['@graph']);
    }

    public function test_the_home_page_organization_names_joseph_as_founder_with_the_same_identifier(): void
    {
        config(['app.url' => self::DOMAIN]);
        $organization = $this->jsonLd((string) $this->get(route('home'))->getContent());

        $this->assertSame(self::DOMAIN.'#organization', $organization['@id']);
        $this->assertSame(['@type' => 'Person', '@id' => self::DOMAIN.'/joseph-ahiankonou#person', 'name' => 'Joseph Ahiankonou', 'url' => self::DOMAIN.'/joseph-ahiankonou'], $organization['founder']);
        $this->assertOnlySchemaOrgProperties([$organization]);
    }

    public function test_links_entered_in_the_admin_are_shown_and_given_to_google(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'page-joseph'), $this->form([
            // Collés tels quels depuis un téléphone : espace insécable, http manquant, texte autour.
            'links' => ['youtube' => "\u{00A0}youtube.com/@coachjoseph ", 'tiktok' => 'Mon TikTok : https://www.tiktok.com/@coachjoseph', 'facebook' => ''],
            'whatsapp_channels' => ['https://whatsapp.com/channel/0029VaAbCdEf', '', 'https://www.whatsapp.com/channel/0029VbGhIjKl'],
        ]))->assertSessionHasNoErrors();
        auth()->logout();

        $this->assertSame([
            ['network' => 'youtube', 'label' => 'YouTube', 'url' => 'https://youtube.com/@coachjoseph'],
            ['network' => 'tiktok', 'label' => 'TikTok', 'url' => 'https://www.tiktok.com/@coachjoseph'],
            ['network' => 'whatsapp_channel', 'label' => 'Chaîne WhatsApp 1', 'url' => 'https://whatsapp.com/channel/0029VaAbCdEf'],
            ['network' => 'whatsapp_channel', 'label' => 'Chaîne WhatsApp 2', 'url' => 'https://www.whatsapp.com/channel/0029VbGhIjKl'],
        ], Founder::links());

        $html = (string) $this->get(route('founder'))->assertOk()
            ->assertSee('data-test="founder-links"', false)
            ->assertSee('href="https://www.tiktok.com/@coachjoseph"', false)
            ->assertDontSee('Facebook')
            ->getContent();

        $person = array_column($this->jsonLd($html)['@graph'], null, '@type')['Person'];
        $this->assertSame(array_column(Founder::links(), 'url'), $person['sameAs']);
        $this->assertSame(1, AdminActivity::query()->where('description', 'like', 'Page « Joseph Ahiankonou » modifiée (4 liens)%')->count());
    }

    public function test_a_link_to_the_wrong_site_is_refused_with_the_expected_form(): void
    {
        $this->actingAs($this->admin)->from(route('admin.settings.edit', 'page-joseph'))
            ->put(route('admin.settings.update', 'page-joseph'), $this->form(['links' => ['youtube' => 'https://www.tiktok.com/@x', 'tiktok' => '', 'facebook' => '']]))
            ->assertSessionHasErrors(['links.youtube' => Founder::invalidLinkMessage('youtube')]);

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'page-joseph'), $this->form(['whatsapp_channels' => ['https://chat.whatsapp.com/AbCdEf']]))
            ->assertSessionHasErrors('whatsapp_channels');

        $this->assertFalse(Founder::isValidLink('facebook', 'http://facebook.com/page'));
        $this->assertFalse(Founder::isValidLink('facebook', 'https://facebook.com.evil.example/page'));
        $this->assertTrue(Founder::isValidLink('facebook', 'https://m.facebook.com/page'));
        $this->assertTrue(Founder::isValidLink('youtube', 'https://youtu.be/abc'));
        $this->assertFalse(Founder::isValidLink('whatsapp_channel', 'https://whatsapp.com/channel/'));
        $this->assertSame([], Founder::links());
    }

    public function test_every_text_is_editable_in_the_admin(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Page Joseph Ahiankonou')->assertSee('Référencement Google');
        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'page-joseph'))->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="photo"', false)
            ->assertSee('name="links[youtube]"', false)
            ->assertSee('name="links[tiktok]"', false)
            ->assertSee('name="links[facebook]"', false)
            ->assertSee('Chaînes WhatsApp')
            ->assertDontSee('type="url"', false);

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'page-joseph'), $this->form([
            'title' => 'Joseph Ahiankonou, formateur en IA',
            'intro' => ['Premier paragraphe modifié.'],
            'journey' => ['Étape modifiée.'],
            'services' => [['question' => 'Formations', 'answer' => 'Texte du service.'], ['question' => '', 'answer' => 'ignoré']],
        ]))->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('founder'))->assertOk()
            ->assertSee('<title>Joseph Ahiankonou, formateur en IA</title>', false)
            ->assertSee('Premier paragraphe modifié.')
            ->assertSee('Étape modifiée.')
            ->assertSee('Texte du service.')
            ->assertDontSee('Auteur de 6 livres.')
            ->assertDontSee('ignoré');
    }

    public function test_an_uploaded_photo_is_cropped_square_and_used_on_the_page_and_in_the_share_image(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'page-joseph'), $this->form([
            'photo' => UploadedFile::fake()->image('joseph.jpg', 1200, 1600),
        ]))->assertSessionHasNoErrors();

        $path = (string) config('site.founder.photo');
        $this->assertStringStartsWith('site/founder/', $path);
        $size = getimagesizefromstring((string) Storage::disk('public')->get($path));
        $this->assertSame([800, 800], [$size[0], $size[1]]);

        $this->get(route('founder'))->assertSee(Storage::disk('public')->url($path), false)->assertSee('data-test="founder-photo"', false);

        // Nouvelle photo : l'ancienne est effacée ; « Retirer » revient à la photo de la page À propos.
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'page-joseph'), $this->form(['photo' => UploadedFile::fake()->image('autre.png', 900, 900)]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'page-joseph'), $this->form(['remove_photo' => '1']))->assertSessionHasNoErrors();
        $this->assertSame('', config('site.founder.photo'));
        $this->assertSame(asset('images/formateur.jpg'), Founder::photoUrl());
    }

    public function test_without_any_photo_the_page_stays_clean_with_initials(): void
    {
        config(['site.trainer.photo' => 'images/absente.jpg']);

        $this->get(route('founder'))->assertOk()
            ->assertSee('data-test="founder-initials"', false)
            ->assertSee('>JA</span>', false)
            ->assertDontSee('data-test="founder-photo"', false);

        $this->get(route('og.founder'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_the_share_image_is_a_1200_by_630_jpeg_with_absolute_address(): void
    {
        config(['app.url' => self::DOMAIN]);
        $html = (string) $this->get(route('founder'))->getContent();

        $this->assertStringContainsString('<meta property="og:image" content="'.self::DOMAIN.'/og/joseph-ahiankonou.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:image:type" content="image/jpeg">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.self::DOMAIN.'/joseph-ahiankonou">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.self::DOMAIN.'/joseph-ahiankonou">', $html);

        $response = $this->get(route('og.founder'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $size = getimagesize($response->baseResponse->getFile()->getPathname());
        $this->assertSame([1200, 630, 'image/jpeg'], [$size[0], $size[1], $size['mime']]);
        $this->assertLessThan(200 * 1024, filesize($response->baseResponse->getFile()->getPathname()));
    }

    public function test_the_signature_is_at_the_bottom_of_every_page_and_links_to_the_page(): void
    {
        $student = User::factory()->create();
        $signature = '/<a href="'.preg_quote(route('founder'), '/').'" class="[^"]*hover:text-white[^"]*" data-test="footer-signature">Dev by Joseph Ahiankonou<\/a>/';

        foreach (['home', 'about', 'founder', 'formations.index', 'contact', 'business', 'consultation', 'legal.privacy', 'login', 'register'] as $name) {
            $this->assertMatchesRegularExpression($signature, (string) $this->get(route($name))->assertOk()->getContent(), $name);
        }

        $this->assertMatchesRegularExpression($signature, (string) $this->actingAs($student)->get(route('dashboard'))->assertOk()->getContent());
        $this->assertMatchesRegularExpression($signature, (string) $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()->getContent());
    }

    public function test_the_about_and_home_pages_link_to_the_page_and_it_is_in_the_sitemap(): void
    {
        config(['app.url' => self::DOMAIN]);

        $this->get(route('about'))->assertSee('data-test="about-founder-link"', false)->assertSee('href="'.route('founder').'"', false);
        $this->get(route('home'))->assertSee('data-test="home-founder-link"', false);

        $this->assertContains('founder', SearchEngines::INDEXABLE_ROUTES);
        $this->get(route('sitemap'))->assertOk()->assertSee('<loc>'.self::DOMAIN.'/joseph-ahiankonou</loc>', false);
    }

    public function test_the_page_can_be_indexed_by_google_in_production(): void
    {
        config(['app.noindex' => false, 'app.url' => self::DOMAIN]);
        $this->app['env'] = 'production';

        $this->get(route('founder'))->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertDontSee('<meta name="robots"', false);
    }

    /**
     * Données structurées valides : chaque type et chaque propriété existent dans le vocabulaire schema.org
     * (liste vérifiée sur schema.org pour les types utilisés).
     *
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function assertOnlySchemaOrgProperties(array $nodes): void
    {
        $allowed = [
            'ProfilePage' => ['url', 'name', 'inLanguage', 'mainEntity', 'isPartOf'],
            'WebSite' => ['name', 'url'],
            'Person' => ['name', 'alternateName', 'jobTitle', 'description', 'url', 'image', 'email', 'telephone', 'homeLocation', 'workLocation', 'knowsAbout', 'worksFor', 'sameAs'],
            'EducationalOrganization' => ['name', 'url', 'email', 'telephone', 'address', 'founder'],
            'Place' => ['name', 'address'],
            'PostalAddress' => ['streetAddress', 'addressLocality', 'addressCountry'],
        ];

        $walk = function (array $node) use (&$walk, $allowed): void {
            if (isset($node['@type'])) {
                $this->assertArrayHasKey($node['@type'], $allowed, 'Type inconnu : '.$node['@type']);

                foreach (array_keys($node) as $property) {
                    if (! str_starts_with((string) $property, '@')) {
                        $this->assertContains($property, $allowed[$node['@type']], $node['@type'].'.'.$property);
                    }
                }
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }

                if (is_string($value)) {
                    $this->assertNotSame('', trim($value), 'Valeur vide');
                }
            }
        };

        foreach ($nodes as $node) {
            $walk($node);
        }
    }
}
