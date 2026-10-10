<?php

namespace Tests\Feature\Packs;

use App\Models\Formation;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Témoignages sur la page /accompagnement (juste après les offres), sur la page de chaque formation (payante ou
 * gratuite) et sur la page de chaque offre (récapitulatif de commande) : les mêmes que sur l'accueil (table
 * testimonials, gérés dans l'admin : affichés ou non, ordre), le même composant (x-site.testimonials), vidéos
 * chargées seulement au toucher. Aucun témoignage : aucune section. Heures fixées : rien ne dépend du jour.
 */
class PackTestimonialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-10 12:00:00', 'Africa/Lome'));
        Formation::query()->where('kind', 'pack')->update([
            'is_active' => true,
            'registration_ends_at' => Carbon::parse('2026-10-12 16:00:00', 'Africa/Lome'),
        ]);
    }

    private function video(string $id, int $position, bool $active = true, ?string $name = null): Testimonial
    {
        return Testimonial::query()->create([
            'type' => 'video',
            'youtube_url' => 'https://www.youtube.com/watch?v='.$id,
            'name' => $name,
            'is_active' => $active,
            'position' => $position,
        ]);
    }

    private function packsHtml(): string
    {
        return (string) $this->get(route('packs'))->assertOk()->getContent();
    }

    public function test_without_any_testimonial_there_is_no_section(): void
    {
        $html = $this->packsHtml();

        $this->assertStringNotContainsString('data-test="packs-testimonials"', $html);
        $this->assertStringNotContainsString('Ce qu\'en disent les élèves', $html);
        $this->assertStringNotContainsString('Témoignage vidéo', $html);

        // Un témoignage masqué dans l'admin ou incomplet (lien YouTube invalide) ne compte pas.
        $this->video('AAAAAAAAAAA', 1, active: false);
        Testimonial::query()->create(['type' => 'video', 'youtube_url' => 'https://exemple.com/video', 'is_active' => true, 'position' => 2]);

        $this->assertStringNotContainsString('data-test="packs-testimonials"', $this->packsHtml());
    }

    public function test_the_existing_testimonials_are_shown_right_after_the_offers_in_the_admin_order(): void
    {
        $this->video('BBBBBBBBBBB', 2, name: 'Élève B');
        $this->video('AAAAAAAAAAA', 1, name: 'Élève A');
        $this->video('CCCCCCCCCCC', 3, active: false, name: 'Élève masqué');

        $this->get(route('packs'))->assertOk()->assertSeeInOrder([
            'id="offres"',
            'data-test="pack-card"',
            'data-test="packs-testimonials"',
            'Ce qu&#039;en disent les élèves',
            'AAAAAAAAAAA',
            'Élève A',
            'BBBBBBBBBBB',
            'Élève B',
            'data-test="packs-steps"',
        ], false);

        $html = $this->packsHtml();
        $this->assertStringNotContainsString('CCCCCCCCCCC', $html);
        $this->assertStringNotContainsString('Élève masqué', $html);
    }

    public function test_videos_load_only_on_demand(): void
    {
        $this->video('AAAAAAAAAAA', 1);

        $html = $this->packsHtml();
        $section = substr($html, (int) strpos($html, 'data-test="packs-testimonials"'));
        $section = substr($section, 0, (int) strpos($section, 'data-test="packs-steps"'));

        // Seule une image d'aperçu, chargée quand elle approche de l'écran ; le lecteur n'est ajouté qu'au toucher.
        $this->assertStringContainsString('i.ytimg.com/vi/AAAAAAAAAAA/', $section);
        $this->assertStringContainsString('loading="lazy"', $section);
        $this->assertMatchesRegularExpression('#<template x-if="playing">\s*<iframe#', $section);
        // Chaque lecteur est dans un modèle Alpine (x-if) : rien n'est chargé depuis YouTube avant le toucher.
        $this->assertSame(substr_count($section, '<iframe'), preg_match_all('#<template x-if="playing">\s*<iframe#', $section));
        $this->assertStringContainsString('x-on:click="playing = true"', $section);
        $this->assertStringContainsString('aria-label="Lire le témoignage vidéo"', $section);
    }

    public function test_every_formation_page_and_every_offer_page_shows_the_same_testimonials(): void
    {
        $this->video('AAAAAAAAAAA', 1, name: 'Élève A');

        // Formation payante (section déjà en place), formation gratuite et page de l'offre (récapitulatif de commande).
        $paid = Formation::factory()->create(['price' => 5500]);
        $free = Formation::factory()->create(['price' => 0, 'event_date' => '2026-10-24']);
        $pack = Formation::query()->where('slug', 'accompagnement-ia-niveau-1')->sole();

        $this->get(route('formations.show', $paid))->assertOk()->assertSee('AAAAAAAAAAA', false)->assertSee('Élève A');
        $this->get(route('formations.show', $free))->assertOk()
            ->assertSee('data-test="free-testimonials"', false)
            ->assertSee('AAAAAAAAAAA', false);
        $this->get(route('checkout.show', $pack))->assertOk()
            ->assertSee('data-test="checkout-testimonials"', false)
            ->assertSee('AAAAAAAAAAA', false);
        $this->actingAs(User::factory()->create())->get(route('checkout.show', $pack))->assertOk()
            ->assertSee('data-test="checkout-testimonials"', false);

        // Le récapitulatif d'une formation habituelle reste exactement comme avant.
        $this->get(route('checkout.show', $paid))->assertOk()->assertDontSee('data-test="checkout-testimonials"', false);
    }

    public function test_without_testimonial_the_formation_and_offer_pages_have_no_section(): void
    {
        $free = Formation::factory()->create(['price' => 0, 'event_date' => '2026-10-24']);
        $pack = Formation::query()->where('slug', 'accompagnement-ia-niveau-1')->sole();

        $this->get(route('formations.show', $free))->assertOk()->assertDontSee('data-test="free-testimonials"', false);
        $this->get(route('checkout.show', $pack))->assertOk()->assertDontSee('data-test="checkout-testimonials"', false);
    }

    public function test_the_admin_still_chooses_which_testimonials_are_shown_and_in_which_order(): void
    {
        $first = $this->video('AAAAAAAAAAA', 1);
        $second = $this->video('BBBBBBBBBBB', 2);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.testimonials.move', [$second, 'up']))->assertRedirect();
        $this->get(route('packs'))->assertSeeInOrder(['BBBBBBBBBBB', 'AAAAAAAAAAA'], false);

        $this->actingAs($admin)->post(route('admin.testimonials.toggle', $second))->assertRedirect();
        $this->get(route('packs'))->assertSee('AAAAAAAAAAA', false)->assertDontSee('BBBBBBBBBBB', false);
        $this->assertTrue($first->fresh()->is_active);
    }
}
