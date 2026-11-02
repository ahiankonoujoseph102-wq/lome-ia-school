<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaPacks;
use App\Assistant\AmaPrompt;
use App\Catalogue\CatalogueUpdate;
use App\Support\Money;
use Database\Seeders\FormationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Connaissances d'Ama après la refonte de novembre 2026 : formation complète et nouveaux prix, attestation, stage et
 * recrutement possibles, présentiel au bureau, Talkami, réalisations sur mesure et devis ; formation gratuite et
 * offres d'accompagnement retirées. Détail envoyé seulement quand la conversation s'y rapporte (poids contenu).
 */
class AmaNovember2026Test extends TestCase
{
    use RefreshDatabase;

    public function test_ama_knows_the_new_catalogue_and_prices_and_not_the_retired_offers(): void
    {
        $prompt = AmaPrompt::build([]);

        $this->assertStringContainsString('Formation complète en intelligence artificielle, de A à Z : '.Money::fcfa(250000), $prompt);
        $this->assertStringNotContainsString('formation-gratuite-ia', $prompt);
        $this->assertStringContainsString('ne sont plus proposées sur le site : ne les propose jamais', AmaPacks::summary());
        $this->assertStringContainsString('offres retirées du site : ne les propose pas', $prompt);
    }

    public function test_the_page_of_the_complete_formation_brings_its_skills_careers_and_after_training(): void
    {
        $prompt = AmaPrompt::build(['page' => 'page d\'une formation', 'formation' => CatalogueUpdate::complete()['title']]);

        $this->assertStringContainsString('Ce que vous allez apprendre (écrit sur la page) : Comprendre l\'intelligence artificielle', $prompt);
        $this->assertStringContainsString('Des métiers où ces compétences servent (écrit sur la page, jamais un emploi promis) : Spécialiste en intelligence artificielle', $prompt);
        $this->assertStringContainsString('Possibilité d\'être recruté par l\'école', $prompt);
    }

    public function test_stage_attestation_and_office_details_come_when_the_visitor_asks(): void
    {
        $short = AmaPrompt::build([]);
        $this->assertStringContainsString('### Après la formation', $short);
        $this->assertStringContainsString('Jamais une promesse d\'emploi', $short);

        $prompt = AmaPrompt::build([], recent: 'Est-ce que je peux faire un stage ou suivre la formation au bureau ?');
        $this->assertStringContainsString('Possibilité de stage de 3 mois', $prompt);
        $this->assertStringContainsString('seulement après : Formation complète en intelligence artificielle, de A à Z', $prompt);
        $this->assertStringContainsString('Réservez une consultation gratuite ou écrivez-nous', $prompt);
        $this->assertStringContainsString('Adidogomé, Lycée moderne 2, Lomé, Togo', $prompt);
        $this->assertStringNotContainsString('{adresse}', $prompt);
    }

    public function test_talkami_is_known_briefly_and_in_detail_when_english_comes_up(): void
    {
        $short = AmaPrompt::build([]);
        $this->assertStringContainsString('### Talkami', $short);
        $this->assertStringContainsString('https://talkami.net/?utm_source=lome-ia-school', $short);
        $this->assertStringNotContainsString('Teacher Rose', $short);

        $prompt = AmaPrompt::build([], recent: 'Je veux améliorer mon anglais');
        $this->assertStringContainsString('Talkami a été conçu et créé par Joseph Ahiankonou', $prompt);
        $this->assertStringContainsString('Teacher Rose', $prompt);
        $this->assertStringContainsString('Formule Essentiel : '.Money::fcfa(7500), $prompt);
        $this->assertStringContainsString('ne parle jamais de « formation gratuite en anglais »', $prompt);
    }

    public function test_quote_requests_are_explained_without_prices(): void
    {
        $prompt = AmaPrompt::build([], recent: 'Pouvez-vous me faire un devis pour un site vitrine ?');

        $this->assertStringContainsString('Demander un devis', $prompt);
        $this->assertStringContainsString('#devis', $prompt);
        $this->assertStringContainsString('ne donne jamais de prix ni de délai', $prompt);
        $this->assertStringContainsString('« Envoyer ma demande de devis »', $prompt);
    }

    public function test_the_request_stays_under_ten_thousand_tokens(): void
    {
        $this->seed(FormationSeeder::class);

        $this->assertLessThan(10000, AmaPrompt::stats()['perRequest']);
    }
}
