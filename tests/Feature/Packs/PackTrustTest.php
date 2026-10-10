<?php

namespace Tests\Feature\Packs;

use App\Models\Formation;
use App\Models\Testimonial;
use App\Packs\PackTrust;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bloc « Pourquoi nous faire confiance » de /accompagnement : après les témoignages, uniquement des faits lus dans
 * les réglages du site (parcours de Coach Joseph, bureau et horaires, WhatsApp, remboursement, attestation de
 * participation) et un lien vers /joseph-ahiankonou. Une information absente n'est pas affichée ; aucune promesse,
 * aucune note, jamais « certificat » ni « diplôme ». Heures fixées : rien ne dépend du jour.
 */
class PackTrustTest extends TestCase
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

    private function block(): string
    {
        $html = (string) $this->get(route('packs'))->assertOk()->getContent();
        $start = strpos($html, 'data-test="packs-trust"');

        if ($start === false) {
            return '';
        }

        return substr($html, $start, (int) strpos($html, '</section>', $start) - $start);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function settings(array $values): void
    {
        SiteSettings::save($values);
        SiteSettings::apply();
    }

    public function test_the_block_shows_the_facts_from_the_site_settings_with_a_link_to_the_founder_page(): void
    {
        $block = $this->block();

        $this->assertNotSame('', $block);
        $this->assertStringContainsString('Pourquoi nous faire confiance', $block);
        $this->assertStringContainsString('Coach Joseph', $block);
        $this->assertStringContainsString('Formateur en intelligence artificielle depuis 2023', $block);
        $this->assertStringContainsString("Plus de 1\u{00A0}000 personnes formées", $block);
        $this->assertStringContainsString(e(config('site.contact.address')), $block);
        $this->assertStringContainsString(e(config('site.contact.hours')), $block);
        $this->assertStringContainsString('+228 72 24 02 31', $block);
        $this->assertStringContainsString('https://wa.me/22872240231', $block);
        $this->assertStringContainsString('dans les 48 heures qui suivent son achat', $block);
        $this->assertStringContainsString(route('legal.terms'), $block);
        $this->assertStringContainsString('Une attestation de participation à la fin', $block);
        $this->assertStringContainsString('href="'.route('founder').'"', $block);
        $this->assertStringContainsString('data-test="packs-trust-founder"', $block);

        foreach (['journey', 'office', 'whatsapp', 'refund', 'attestation'] as $key) {
            $this->assertStringContainsString('data-test="packs-trust-'.$key.'"', $block);
        }
        $this->assertSame([], PackTrust::missing());
    }

    public function test_the_block_never_promises_anything_nor_mentions_a_certificate(): void
    {
        $text = mb_strtolower(strip_tags($this->block()));

        foreach (['certificat', 'diplôme', 'diplome', 'certifié', 'agréé', 'étoile', '★', 'revenu', 'gagner', 'garanti', 'résultat', 'partenaire', '/5'] as $word) {
            $this->assertStringNotContainsString($word, $text, 'Mot interdit : '.$word);
        }
        $this->assertStringNotContainsString('<img', $this->block());

        // Une attestation décrite autrement (« certificat ») dans l'admin n'est jamais affichée.
        $page = (array) config('site.packs_page');
        $page['common'] = [['icon' => 'academic-cap', 'title' => 'Un certificat d\'attestation de participation reconnu', 'text' => '']];
        $this->settings(['site.packs_page' => $page]);

        $this->assertStringNotContainsString('data-test="packs-trust-attestation"', $this->block());
        $this->assertContains('attestation', PackTrust::missing());
    }

    public function test_the_block_follows_the_settings_changed_in_the_admin(): void
    {
        $contact = (array) config('site.contact');
        $contact['address'] = 'Rue des essais, Lomé';
        $contact['hours'] = 'Du lundi au vendredi, de 9 h à 17 h';
        $this->settings(['site.contact' => $contact]);

        $block = $this->block();
        $this->assertStringContainsString('Rue des essais, Lomé', $block);
        $this->assertStringContainsString('Du lundi au vendredi, de 9 h à 17 h', $block);
        $this->assertStringNotContainsString('Adidogomé', $block);
    }

    public function test_missing_information_is_not_displayed(): void
    {
        $founder = (array) config('site.founder');
        $founder['journey'] = ['Auteur de 6 livres.'];
        $contact = (array) config('site.contact');
        $contact['hours'] = '';
        $legal = (array) config('site.legal');
        $legal['refund_policy'] = '';
        $page = (array) config('site.packs_page');
        $page['common'] = [['icon' => 'heart', 'title' => 'Un accompagnement à vie', 'text' => '']];
        $whatsapp = (array) config('site.whatsapp');
        $whatsapp['number'] = '';

        $this->settings([
            'site.founder' => $founder,
            'site.contact' => $contact,
            'site.legal' => $legal,
            'site.packs_page' => $page,
            'site.whatsapp' => $whatsapp,
        ]);

        $this->assertSame([], PackTrust::facts());
        $this->assertSame(['journey', 'office', 'whatsapp', 'refund', 'attestation'], PackTrust::missing());
        // Plus aucun fait : le bloc entier disparaît (le reste de la page ne change pas).
        $this->assertSame('', $this->block());
        $this->get(route('packs'))->assertOk()->assertSee('data-test="packs-steps"', false);
    }

    public function test_each_missing_fact_only_hides_its_own_line(): void
    {
        $founder = (array) config('site.founder');
        // Une des deux lignes du parcours manque : la carte du parcours n'est pas affichée à moitié.
        $founder['journey'] = ['Formateur en intelligence artificielle depuis 2023.', 'Auteur de 6 livres.'];
        $legal = (array) config('site.legal');
        $legal['refund_policy'] = '[Règle de remboursement à compléter]';
        $this->settings(['site.founder' => $founder, 'site.legal' => $legal]);

        $block = $this->block();
        $this->assertStringNotContainsString('data-test="packs-trust-journey"', $block);
        $this->assertStringNotContainsString('data-test="packs-trust-refund"', $block);
        $this->assertStringNotContainsString('depuis 2023', $block);
        $this->assertStringNotContainsString('Règle de remboursement', $block);
        foreach (['office', 'whatsapp', 'attestation'] as $key) {
            $this->assertStringContainsString('data-test="packs-trust-'.$key.'"', $block);
        }
        $this->assertSame(['journey', 'refund'], PackTrust::missing());
        // Le lien vers la page de Joseph Ahiankonou reste.
        $this->assertStringContainsString('data-test="packs-trust-founder"', $block);
    }

    public function test_the_block_comes_after_the_testimonials(): void
    {
        Testimonial::query()->create(['type' => 'video', 'youtube_url' => 'https://youtu.be/AAAAAAAAAAA', 'is_active' => true, 'position' => 1]);

        $this->get(route('packs'))->assertOk()->assertSeeInOrder([
            'id="offres"',
            'data-test="packs-testimonials"',
            'data-test="packs-trust"',
            'data-test="packs-steps"',
            'data-test="packs-final"',
        ], false);

        // Sans témoignage : juste après les offres.
        Testimonial::query()->delete();
        $this->get(route('packs'))->assertSeeInOrder(['data-test="pack-card"', 'data-test="packs-trust"', 'data-test="packs-steps"'], false);
    }
}
