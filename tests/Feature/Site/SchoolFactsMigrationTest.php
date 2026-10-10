<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaKnowledge;
use App\Packs\PackSalesTexts;
use App\Settings\SiteSettings;
use App\Support\FormationIllustrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Réglages déjà enregistrés sur le site en ligne (migration 2026_10_06_100000_fix_school_founding_facts) : seuls les
 * textes d'origine exacts sont corrigés, jamais un texte modifié à la main, et rien n'est effacé.
 */
class SchoolFactsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const string OLD_DEFAULT_SCHOOL = "Lomé IA School est une école de formations en intelligence artificielle basée à Lomé, au Togo. Elle a été créée en 2023. Son ancien nom était Joseph Academy.\nFondateur et formateur principal : Joseph Ahiankonou.";

    /**
     * La base de test n'est migrée qu'une fois par série de tests, par le premier test qui tourne, à la vraie date du
     * jour. Depuis le 8 octobre 2026, la migration 2026_10_11_100000_add_agents_ia_illustration y enregistre alors
     * system.agents_illustration_done. Ce réglage est retiré au début de chaque test (retrait annulé à la fin du test
     * avec le reste) : ces tests ne dépendent ainsi ni de la date ni de l'ordre des tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('site_settings')->where('key', FormationIllustrations::AGENTS_DONE_KEY)->delete();
        SiteSettings::flush();
    }

    private function migrate(): void
    {
        (require database_path('migrations/2026_10_06_100000_fix_school_founding_facts.php'))->up();
        SiteSettings::apply();
    }

    /**
     * @return array<string, string>
     */
    private function rows(): array
    {
        return DB::table('site_settings')->orderBy('key')->get(['key', 'value', 'updated_at'])
            ->mapWithKeys(fn (object $row): array => [$row->key => $row->value.'|'.$row->updated_at])->all();
    }

    /**
     * Cas de Joseph : base de connaissances déjà corrigée à la main dans l'admin (phrases retirées, « Autres
     * informations » ajoutées) : la ligne n'est pas réécrite, son texte reste exactement le même.
     */
    public function test_a_knowledge_base_corrected_by_hand_is_left_exactly_as_it_is(): void
    {
        SiteSettings::save([
            'site.assistant_knowledge' => [
                'school' => "Lomé IA School est une école de formations en intelligence artificielle basée à Lomé, au Togo.\nTexte corrigé par Joseph : l'école a été créée récemment.",
                'faq' => [['question' => 'Question de Joseph ?', 'answer' => 'Réponse de Joseph.']],
                'other' => 'Informations ajoutées par Joseph.',
            ],
            // Texte modifié à la main, qui contient encore une partie seulement de l'ancienne phrase : pas touché non plus.
            'site.stats' => [['value' => '1 000+', 'label' => 'personnes formées'], ['value' => '2023', 'label' => 'année de création (modifiée)']],
            'site.about' => ['mission' => 'Mission de Joseph.', 'founded' => '2023'],
        ]);
        $this->travel(5)->minutes();
        $before = $this->rows();

        $this->migrate();

        $this->assertSame($before, $this->rows());
        $this->assertSame('Informations ajoutées par Joseph.', config('site.assistant_knowledge.other'));
        $this->assertSame([['question' => 'Question de Joseph ?', 'answer' => 'Réponse de Joseph.']], AmaKnowledge::faq());
    }

    public function test_only_the_exact_original_sentence_is_replaced_in_place(): void
    {
        SiteSettings::save([
            'site.assistant_knowledge' => ['school' => self::OLD_DEFAULT_SCHOOL."\nAjout de Joseph.", 'faq' => [], 'other' => 'Autre information.'],
            'site.stats' => [['value' => '1 200+', 'label' => 'personnes formées'], ['value' => '2023', 'label' => 'année de création de l\'école'], ['value' => 'À vie', 'label' => 'accès et accompagnement']],
            'site.about' => ['mission' => 'Mission de Joseph.', 'founded' => '2023'],
        ]);

        $this->migrate();

        $this->assertSame(
            "Lomé IA School est une école de formations en intelligence artificielle basée à Lomé, au Togo. C'est une école récente. Joseph Academy est une autre activité de Joseph (écriture de livres pour des clients), distincte de Lomé IA School : ce n'est pas l'ancien nom de l'école.\nFondateur et formateur principal : Joseph Ahiankonou.\nAjout de Joseph.",
            config('site.assistant_knowledge.school'),
        );
        $this->assertSame('Autre information.', config('site.assistant_knowledge.other'));
        $this->assertSame([['value' => '1 200+', 'label' => 'personnes formées'], ['value' => '2023', 'label' => 'année où Joseph a commencé à former'], ['value' => 'À vie', 'label' => 'accès et accompagnement']], config('site.stats'));
        // Rien n'est effacé ailleurs.
        $this->assertSame(['mission' => 'Mission de Joseph.', 'founded' => '2023'], json_decode((string) DB::table('site_settings')->where('key', 'site.about')->value('value'), true));

        // Relancée : rien ne change.
        $before = $this->rows();
        $this->travel(5)->minutes();
        $this->migrate();
        $this->assertSame($before, $this->rows());
    }

    public function test_a_key_figure_with_another_year_is_left_untouched(): void
    {
        SiteSettings::save(['site.stats' => [['value' => '1 000+', 'label' => 'personnes formées'], ['value' => '2026', 'label' => 'année de création de l\'école']]]);

        $this->migrate();

        $this->assertSame('année de création de l\'école', config('site.stats.1.label'));
    }

    public function test_nothing_happens_without_saved_settings(): void
    {
        $this->migrate();

        // Seul réglage écrit par les migrations : les anciens textes des offres, gardés par PackSalesTexts.
        $this->assertSame(0, DB::table('site_settings')->where('key', '!=', PackSalesTexts::SAVED_KEY)->count());
        $this->assertSame('année où Joseph a commencé à former', config('site.stats.1.label'));
        $this->assertStringNotContainsString('Son ancien nom était Joseph Academy', AmaKnowledge::DEFAULT_SCHOOL);
    }
}
