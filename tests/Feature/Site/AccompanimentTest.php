<?php

namespace Tests\Feature\Site;

use App\Catalogue\Accompaniment;
use App\Models\Formation;
use App\Models\SiteSetting;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Novembre 2026 : accompagnement de 3 mois après la formation (au lieu de « à vie »), l'accès restant à vie ; textes
 * déjà enregistrés dans l'admin remplacés une seule fois, anciennes valeurs gardées.
 */
class AccompanimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_never_promises_a_lifetime_accompaniment_anymore(): void
    {
        $formation = Formation::factory()->create(['price' => 10000]);

        foreach ([route('home'), route('formations.show', $formation), route('legal.terms'), route('about')] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsStringIgnoringCase('accompagnement à vie', $html, $url);
            $this->assertStringNotContainsString('100 %', strip_tags($html), $url);
        }

        $this->get(route('home'))->assertSee('Accompagnement de 3 mois');
        $this->get(route('formations.show', $formation))->assertSee('Accompagnement de 3 mois')->assertSee('Accès à vie');
        $this->get(route('legal.terms'))->assertSee('Les élèves inscrits avant le 2 novembre 2026 gardent', false);
    }

    public function test_texts_saved_in_the_admin_are_rewritten_once_and_kept(): void
    {
        // La migration est déjà passée sur la base vide des tests : on repart comme un site déjà en ligne.
        SiteSetting::query()->where('key', Accompaniment::SAVED_KEY)->delete();

        SiteSettings::save([
            'site.faq' => ['home' => [['question' => 'Combien de temps ?', 'answer' => 'À vie, avec un accompagnement à vie dans le groupe privé de la formation (tant que la plateforme est en activité).']], 'formation' => []],
            'site.assistant_knowledge' => ['school' => 'Texte de Joseph : accès et accompagnement à vie.', 'faq' => [], 'other' => ''],
            'site.packs_page' => ['subtitle' => 'Avec un accompagnement à vie.'],
        ]);

        Accompaniment::applyToStoredSettings();

        $this->assertSame('À vie pour la formation (tant que la plateforme est en activité), avec un accompagnement de 3 mois après la formation dans le groupe privé ou sur WhatsApp.', SiteSettings::get('site.faq')['home'][0]['answer']);
        $this->assertSame('Texte de Joseph : accès à vie et accompagnement de 3 mois.', SiteSettings::get('site.assistant_knowledge')['school']);
        // Offres retirées : jamais touchées.
        $this->assertSame('Avec un accompagnement à vie.', SiteSettings::get('site.packs_page')['subtitle']);

        $saved = SiteSetting::query()->where('key', Accompaniment::SAVED_KEY)->sole()->value;
        $this->assertArrayHasKey('site.faq', $saved);

        // Une seule fois : un texte écrit plus tard n'est plus jamais réécrit.
        SiteSettings::save(['site.assistant_knowledge' => ['school' => 'Nouvel accompagnement à vie.', 'faq' => [], 'other' => '']]);
        Accompaniment::applyToStoredSettings();
        $this->assertSame('Nouvel accompagnement à vie.', SiteSettings::get('site.assistant_knowledge')['school']);
    }
}
