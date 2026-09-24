<?php

namespace Tests\Feature\Site;

use App\Models\Formation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    private string $screenshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->screenshot = public_path('images/temoignages/test-capture-whatsapp.jpg');
    }

    protected function tearDown(): void
    {
        File::delete($this->screenshot);

        parent::tearDown();
    }

    public function test_home_page_highlights_key_figures_lifetime_access_and_the_trainer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('1 000+')
            ->assertSee('2023')
            ->assertSee('Accès à vie')
            ->assertSee('Accompagnement à vie')
            ->assertSee(config('site.trainer.short_bio'))
            ->assertSee('images/formateur.jpg')
            ->assertSee('alt="'.e(config('site.trainer.photo_alt')).'"', false)
            ->assertDontSee('placeholder-text');
    }

    public function test_formation_page_highlights_lifetime_access_and_support(): void
    {
        $formation = Formation::factory()->create();

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('Accès à vie')
            ->assertSee('Accompagnement à vie')
            ->assertSee(config('site.trainer.photo_alt'));
    }

    public function test_about_page_shows_the_trainer_biography(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Plus de 1 000 personnes formées depuis 2023')
            ->assertSee('Formé auprès d&#039;experts en France et aux États-Unis', false)
            ->assertSee('images/formateur.jpg')
            ->assertDontSee('placeholder-text');
    }

    public function test_testimonials_section_is_hidden_when_the_list_is_empty(): void
    {
        config(['site.testimonials' => []]);
        $formation = Formation::factory()->create();

        $this->get(route('home'))->assertOk()->assertDontSee('Ils se sont formés avec nous');
        $this->get(route('formations.show', $formation))->assertOk()->assertDontSee('Ce qu&#039;en disent les élèves', false);
    }

    public function test_the_three_testimonial_types_are_displayed(): void
    {
        File::put($this->screenshot, 'image');

        config(['site.testimonials' => [
            ['type' => 'text', 'name' => 'Afi K.', 'formation' => 'Formation ChatGPT & Claude', 'text' => 'Une formation très concrète.', 'photo' => null],
            ['type' => 'screenshot', 'image' => 'test-capture-whatsapp.jpg', 'name' => 'Kossi A.'],
            ['type' => 'video', 'youtube' => 'https://youtu.be/dQw4w9WgXcQ', 'name' => 'Ama D.'],
            ['type' => 'screenshot', 'image' => 'fichier-absent.jpg', 'name' => 'Capture introuvable'],
        ]]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Ils se sont formés avec nous')
            ->assertSee('Une formation très concrète.')
            ->assertSee('Afi K.')
            ->assertSee('images/temoignages/test-capture-whatsapp.jpg')
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('Capture introuvable');
    }

    public function test_social_links_are_hidden_when_empty(): void
    {
        config(['site.social' => ['facebook' => 'https://www.facebook.com/lomeiaschool', 'tiktok' => '', 'youtube' => '']]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('https://www.facebook.com/lomeiaschool')
            ->assertDontSee('>tiktok<', false)
            ->assertDontSee('>youtube<', false);
    }

    public function test_recorded_format_is_the_rule_and_live_sessions_are_occasional(): void
    {
        $formation = Formation::factory()->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Accès immédiat après paiement')
            ->assertSee('Sessions ponctuelles en direct ou à Lomé')
            ->assertDontSee('En ligne, en direct ou en présentiel');

        $this->get(route('formations.index'))
            ->assertOk()
            ->assertSee(config('site.formats.recorded.catalogue'))
            ->assertDontSee('Filtrer par format');

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('Enregistrée · à suivre à votre rythme');
    }

    public function test_bonus_section_is_hidden_when_the_formation_has_no_bonus(): void
    {
        $formation = Formation::factory()->create();
        config(['site.bonuses.'.$formation->slug => []]);

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertDontSee('Bonus inclus')
            ->assertSee('Inclus avec la formation')
            ->assertSee('L&#039;accès au groupe privé de discussion de la formation', false);
    }

    public function test_bonuses_from_the_config_are_displayed(): void
    {
        $formation = Formation::factory()->create();
        config(['site.bonuses.'.$formation->slug => ['Un vrai bonus fourni aux élèves', '  ']]);

        $this->get(route('formations.show', $formation))
            ->assertOk()
            ->assertSee('Bonus inclus')
            ->assertSee('Un vrai bonus fourni aux élèves');
    }

    public function test_no_formation_has_a_bonus_by_default(): void
    {
        $this->assertSame([], array_filter(config('site.bonuses')));
    }

    public function test_site_is_always_dark_whatever_the_phone_setting(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="color-scheme" content="dark">', false);

        $css = (string) file_get_contents(resource_path('css/site.css'));
        $this->assertStringContainsString('color-scheme: dark;', $css);
        $this->assertStringNotContainsString('.js [data-reveal]', $css, 'Le contenu ne doit jamais être masqué en attendant une animation.');
    }

    public function test_whatsapp_is_in_the_header_on_phones_and_floating_only_on_large_screens(): void
    {
        $content = (string) $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($content, 'aria-label="Nous écrire sur WhatsApp"'));
        $this->assertMatchesRegularExpression('/class="[^"]*\bfixed\b[^"]*\bhidden\b[^"]*\blg:flex\b[^"]*"\s+aria-label="Nous écrire sur WhatsApp"/', $content);
    }

    public function test_consultation_takes_place_online_by_video_call(): void
    {
        $this->get(route('consultation'))
            ->assertOk()
            ->assertSee('En direct en ligne, par appel vidéo')
            ->assertDontSee('sur place');
    }

    public function test_legal_pages_show_the_site_owner_refund_and_retention_rules(): void
    {
        $this->get(route('legal.notice'))
            ->assertOk()
            ->assertSee('Responsable du site')
            ->assertSee('Joseph Ahiankonou')
            ->assertSee('Adidogomé, Lycée moderne 2, Lomé, Togo')
            ->assertSee('+228 72 24 02 31')
            ->assertDontSee('RCCM')
            ->assertDontSee('placeholder-text');

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('48 heures')
            ->assertSee('tant que la plateforme Lomé IA School est en activité')
            ->assertDontSee('placeholder-text');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('3 ans après votre dernier achat')
            ->assertDontSee('placeholder-text');
    }
}
