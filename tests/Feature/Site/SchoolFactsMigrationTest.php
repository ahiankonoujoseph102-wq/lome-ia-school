<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaKnowledge;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Réglages déjà enregistrés sur le site en ligne : l'école n'est plus présentée comme créée en 2023 ni comme
 * l'ex-Joseph Academy (migration 2026_10_06_100000_fix_school_founding_facts), sans toucher au reste.
 */
class SchoolFactsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        (require database_path('migrations/2026_10_06_100000_fix_school_founding_facts.php'))->up();
        SiteSettings::apply();
    }

    public function test_saved_settings_are_corrected_without_touching_the_rest(): void
    {
        $oldSchool = "Lomé IA School est une école de formations en intelligence artificielle basée à Lomé, au Togo. Elle a été créée en 2023. Son ancien nom était Joseph Academy.\nTexte ajouté par Joseph.";

        SiteSettings::save([
            'site.stats' => [['value' => '1 200+', 'label' => 'personnes formées'], ['value' => '2023', 'label' => 'année de création de l\'école'], ['value' => 'À vie', 'label' => 'accès et accompagnement']],
            'site.assistant_knowledge' => ['school' => $oldSchool, 'faq' => [['question' => 'Q ?', 'answer' => 'R.']], 'other' => 'Autre.'],
            'site.about' => ['mission' => 'Mission de Joseph.', 'values' => [], 'founded' => '2023'],
        ]);

        $this->migrate();

        $this->assertSame([['value' => '1 200+', 'label' => 'personnes formées'], ['value' => '2023', 'label' => 'année où Joseph a commencé à former'], ['value' => 'À vie', 'label' => 'accès et accompagnement']], config('site.stats'));
        $school = (string) config('site.assistant_knowledge.school');
        $this->assertStringNotContainsString('créée en 2023', $school);
        $this->assertStringNotContainsString('ancien nom', mb_strtolower(str_replace('Joseph Academy est une autre activité de Joseph (écriture de livres pour des clients), distincte de Lomé IA School : ce n\'est pas l\'ancien nom de l\'école.', '', $school)));
        $this->assertStringContainsString('C\'est une école récente.', $school);
        $this->assertStringContainsString('Joseph Academy est une autre activité de Joseph', $school);
        $this->assertStringContainsString('Texte ajouté par Joseph.', $school);
        $this->assertSame([['question' => 'Q ?', 'answer' => 'R.']], AmaKnowledge::faq());
        $this->assertSame('Mission de Joseph.', config('site.about.mission'));
        $this->assertArrayNotHasKey('founded', (array) config('site.about'));

        // Relancée : rien ne change (pas de doublon).
        $before = DB::table('site_settings')->orderBy('key')->pluck('value', 'key')->all();
        $this->migrate();
        $this->assertSame($before, DB::table('site_settings')->orderBy('key')->pluck('value', 'key')->all());
    }

    public function test_nothing_happens_without_saved_settings(): void
    {
        $this->migrate();

        $this->assertSame(0, DB::table('site_settings')->count());
        $this->assertSame('année où Joseph a commencé à former', config('site.stats.1.label'));
        $this->assertStringNotContainsString('Joseph Academy.', AmaKnowledge::DEFAULT_SCHOOL);
    }
}
