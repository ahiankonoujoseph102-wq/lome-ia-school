<?php

namespace Tests\Feature\Site;

use App\Assistant\AmaKnowledge;
use App\Enums\EnrollmentSource;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\User;
use App\Support\FormationShare;
use App\Support\LiveOffer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Formation « Agents IA » proposée en direct du 1er au 7 octobre 2026 (config/live_offers.php) :
 * encadré de la page, questions fréquentes, bandeau de l'accueil qui disparaît après la dernière séance,
 * aperçu de partage, espace élève et Ama. Uniquement les informations fournies par Joseph.
 */
class LiveOfferTest extends TestCase
{
    use RefreshDatabase;

    private const array PROGRAMME = [
        'Comprendre les agents IA et choisir son projet d\'agent',
        'Écrire les instructions d\'un agent (rôle, ton, règles, limites)',
        'Créer son premier agent avec Gemini (Gems) et lui donner des documents',
        'Créer un agent avec Claude (Projets), le tester et le corriger',
        'Un agent pour répondre aux clients sur WhatsApp Business',
        'Proposer ses services d\'agents IA (trouver des clients, fixer ses prix)',
        'Projet final, présentation des agents, remise des attestations',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Veille de la première séance, 9 h à Lomé.
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-30 09:00', 'Africa/Lome'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory(storage_path('framework/cache/og'));

        parent::tearDown();
    }

    private function formation(array $attributes = []): Formation
    {
        return Formation::factory()->create([
            'slug' => 'creation-agents-ia',
            'title' => 'Agents IA',
            'price' => 30000,
            ...$attributes,
        ]);
    }

    public function test_the_page_shows_the_live_box_with_dates_hours_and_the_full_programme(): void
    {
        $formation = $this->formation();

        $response = $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee('data-test="live-offer"', false)
            ->assertSee('data-test="live-offer-summary"', false)
            ->assertSee('Formation en direct')
            ->assertSee('Du jeudi 1er octobre au mercredi 7 octobre 2026')
            ->assertSee("Chaque soir de 20\u{00A0}h à 23\u{00A0}h (heure de Lomé, GMT)")
            ->assertSee('7 séances, une par soir')
            ->assertSee("de 21\u{00A0}h à minuit au Bénin (GMT+1)")
            ->assertSee('Première séance :')
            ->assertSee('Chaque séance a lieu en direct sur Google Meet et elle est enregistrée.')
            ->assertSee('disponibles à vie dans le groupe d\'accompagnement')
            ->assertSee('Places non limitées')
            ->assertSee('L\'un ou l\'autre suffit')
            ->assertSee('Accompagnement à vie')
            ->assertSee('Une attestation de participation délivrée par Lomé IA School')
            ->assertSee('Je m\'inscris · 30'."\u{00A0}".'000'."\u{00A0}".'FCFA', false)
            ->assertSee(route('checkout.show', $formation));

        $days = ['Jeudi 1er octobre', 'Vendredi 2 octobre', 'Samedi 3 octobre', 'Dimanche 4 octobre', 'Lundi 5 octobre', 'Mardi 6 octobre', 'Mercredi 7 octobre'];
        $expected = [];

        foreach (self::PROGRAMME as $index => $title) {
            $expected[] = $days[$index];
            $expected[] = $title;
        }

        $response->assertSeeInOrder($expected);

        // La formation n'est plus présentée comme « enregistrée, à suivre à votre rythme » pendant l'offre.
        $response->assertDontSee('Enregistrée · à suivre à votre rythme');
    }

    public function test_the_page_answers_the_six_questions_from_the_real_facts(): void
    {
        $formation = $this->formation();

        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSeeInOrder([
                'Comment je reçois le lien ?', 'vous êtes ajouté(e) automatiquement au groupe d\'accompagnement', 'rubrique « Sessions »', 'annonce épinglée du groupe',
                'J\'ai raté une séance ?', 'Chaque séance est enregistrée.',
                'Je n\'ai qu\'un téléphone ?', 'l\'un ou l\'autre suffit',
                'Comment payer ?', 'Par Mobile Money depuis le Togo, le Burkina Faso ou le Bénin', 'confirmez votre adresse e-mail', "30\u{00A0}000\u{00A0}FCFA", 'capture d\'écran', 'Les frais de transfert sont à votre charge.', 'Besoin d\'aide pour payer ?',
                'J\'ai payé, et après ?', 'Dès l\'envoi de votre capture, la formation s\'ouvre', 'accès provisoire',
                'Vais-je recevoir une attestation ?', 'attestation de participation délivrée par Lomé IA School', 'mercredi 7 octobre',
            ])
            // La question habituelle « La formation est-elle en direct ? Non… » est remplacée.
            ->assertDontSee('La formation est-elle en direct ?');
    }

    public function test_nothing_is_invented_and_no_forbidden_word_is_used(): void
    {
        $formation = $this->formation();
        $html = $this->get(route('formations.show', $formation))->assertOk()->getContent().$this->get(route('home'))->getContent();
        $offer = LiveOffer::for($formation);
        $this->assertNotNull($offer);
        $texts = mb_strtolower($html.json_encode(config('live_offers')).$offer->assistantText().json_encode($offer->faq()));

        foreach (['certificat reconnu', 'diplôme', 'certifié', 'certifiée'] as $word) {
            // Seule la consigne donnée à Ama cite ces mots, pour les interdire.
            $this->assertStringNotContainsString($word, str_replace(mb_strtolower('Ne parle jamais de « certificat », de « diplôme » ni de formation « certifiée »'), '', $texts), $word);
        }

        foreach (['revenu', 'gagner de l\'argent', 'garanti', 'témoignage'] as $word) {
            $this->assertStringNotContainsString($word, mb_strtolower($offer->assistantText().json_encode(config('live_offers'))), $word);
        }
    }

    public function test_the_dates_match_the_weekdays_given_by_joseph(): void
    {
        $offer = LiveOffer::for($this->formation());
        $this->assertNotNull($offer);

        $this->assertSame(
            ['Jeudi', 'Vendredi', 'Samedi', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi'],
            array_column($offer->sessions(), 'weekday'),
        );
        $this->assertSame(self::PROGRAMME, array_column($offer->sessions(), 'title'));
        $this->assertSame('2026-10-01 20:00', $offer->sessions()[0]['starts_at']->format('Y-m-d H:i'));
        $this->assertSame('2026-10-07 23:00', $offer->sessions()[6]['ends_at']->format('Y-m-d H:i'));
        $this->assertSame('Africa/Lome', $offer->sessions()[0]['starts_at']->timezoneName);
        $this->assertSame('Du 1er au 7 octobre 2026', $offer->shortPeriod());
    }

    public function test_the_next_session_follows_the_week_and_everything_disappears_after_the_last_one(): void
    {
        $formation = $this->formation();

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-03 21:00', 'Africa/Lome'));
        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee('Séance en cours :')
            ->assertSee('Terminée : enregistrement disponible dans le groupe d\'accompagnement', false)
            ->assertSee('Les séances passées se rattrapent avec les enregistrements.');

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-07 22:59', 'Africa/Lome'));
        $this->get(route('home'))->assertSee('data-test="home-live-offer"', false);
        $this->get(route('formations.show', $formation))->assertSee('data-test="live-offer"', false);

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-07 23:00', 'Africa/Lome'));
        $this->get(route('home'))->assertOk()->assertDontSee('data-test="home-live-offer"', false);
        $this->get(route('formations.show', $formation))->assertOk()
            ->assertDontSee('data-test="live-offer"', false)
            ->assertDontSee('data-test="live-offer-summary"', false)
            ->assertSee('La formation est-elle en direct ?');
        $this->assertNull(LiveOffer::for($formation));
    }

    public function test_the_home_banner_leads_to_the_live_box_of_the_formation(): void
    {
        $formation = $this->formation();

        $this->get(route('home'))->assertOk()
            ->assertSee('data-test="home-live-offer"', false)
            ->assertSee(route('formations.show', $formation).'#formation-en-direct', false)
            ->assertSee("Agents IA en direct sur Google Meet · Du 1er au 7 octobre 2026, de 20\u{00A0}h à 23\u{00A0}h (heure de Lomé)")
            ->assertSee('Voir le programme');
    }

    public function test_a_hidden_formation_has_no_banner_and_other_formations_are_untouched(): void
    {
        $this->formation(['is_active' => false]);
        $other = Formation::factory()->create(['slug' => 'automatisation-ia']);

        $this->get(route('home'))->assertOk()->assertDontSee('data-test="home-live-offer"', false);
        $this->get(route('formations.show', $other))->assertOk()
            ->assertDontSee('data-test="live-offer"', false)
            ->assertSee('La formation est-elle en direct ?');
        $this->assertNull(LiveOffer::for($other));
    }

    public function test_catalogue_cards_and_checkout_show_the_live_dates(): void
    {
        $formation = $this->formation(['is_featured' => true]);

        $this->get(route('formations.index'))->assertOk()
            ->assertSee('data-test="live-offer-badge"', false)
            ->assertSee('En direct · du 1er au 7 octobre 2026');

        $this->actingAs(User::factory()->create())->get(route('checkout.show', $formation))->assertOk()
            ->assertSee('data-test="checkout-live-offer"', false)
            ->assertSee('En direct sur Google Meet : du jeudi 1er octobre au mercredi 7 octobre 2026')
            ->assertSee('Les 7 séances en direct sur Google Meet');
    }

    public function test_share_preview_describes_the_live_formation_with_absolute_links(): void
    {
        $formation = $this->formation();
        $url = FormationShare::url($formation);

        $response = $this->get(route('formations.show', $formation))->assertOk();
        $response->assertSee('<meta property="og:title" content="Agents IA', false)
            ->assertSee('<meta property="og:url" content="'.$url.'"', false)
            ->assertSee("Formation en direct sur Google Meet, du 1er au 7 octobre 2026, chaque soir de 20\u{00A0}h à 23\u{00A0}h (heure de Lomé). 7 séances enregistrées, accompagnement à vie.", false)
            ->assertSee('"courseMode":"online"', false);

        $image = FormationShare::imageUrl($formation);
        $this->assertStringStartsWith(url('/og/formations/creation-agents-ia.png'), $image);
        $this->assertMatchesRegularExpression('/\?v=\d+-[0-9a-f]{8}$/', $image);
        $this->assertSame(FormationShare::imageType($formation), 'image/png');

        $this->get(route('og.formation', $formation))->assertOk()->assertHeader('Content-Type', 'image/png');
        $size = getimagesizefromstring((string) file_get_contents((string) collect(File::files(storage_path('framework/cache/og')))->first()?->getPathname()));
        $this->assertSame([1200, 630], [$size[0] ?? 0, $size[1] ?? 0]);

        // Après l'offre : l'image et sa version redeviennent celles d'avant.
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-08 08:00', 'Africa/Lome'));
        $this->assertMatchesRegularExpression('/\?v=\d+$/', FormationShare::imageUrl($formation));
    }

    public function test_buyers_find_where_the_meet_link_and_the_recordings_are(): void
    {
        $formation = $this->formation();
        $student = User::factory()->create();
        $student->enroll($formation, EnrollmentSource::Admin);

        $this->actingAs($student)->get(route('formations.show', $formation))->assertOk()
            ->assertSee('data-test="live-offer-sessions"', false)
            ->assertSee('Voir mes séances et le lien Google Meet')
            ->assertDontSee('data-test="live-offer-buy"', false);

        $this->actingAs($student)->get(route('student.formations.show', $formation))->assertOk()
            ->assertSee('data-test="student-live-offer"', false)
            ->assertSee(route('student.sessions.index'))
            ->assertSee('dans l\'annonce épinglée du groupe d\'accompagnement', false)
            ->assertSee('L\'enregistrement et le document de chaque séance sont déposés dans le groupe d\'accompagnement', false)
            ->assertDontSee('Les leçons arrivent bientôt');

        // Après la semaine, les acheteurs gardent l'explication.
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-10 08:00', 'Africa/Lome'));
        $this->actingAs($student)->get(route('student.formations.show', $formation))->assertOk()
            ->assertSee('data-test="student-live-offer"', false);
    }

    public function test_the_meet_link_is_never_on_the_public_page(): void
    {
        $formation = $this->formation();
        LiveSession::factory()->create([
            'formation_id' => $formation->id,
            'meet_url' => 'https://meet.google.com/abc-defg-hij',
            'starts_at' => CarbonImmutable::parse('2026-10-01 20:00', 'Africa/Lome'),
            'duration_minutes' => 180,
        ]);

        $this->get(route('formations.show', $formation))->assertOk()->assertDontSee('meet.google.com/abc-defg-hij');
        $this->get(route('home'))->assertOk()->assertDontSee('meet.google.com/abc-defg-hij');
    }

    public function test_ama_knows_the_dates_the_programme_and_the_answers(): void
    {
        $this->formation();

        $text = AmaKnowledge::formations();

        $this->assertStringContainsString('Formation EN DIRECT sur Google Meet : Du jeudi 1er octobre au mercredi 7 octobre 2026, 7 séances', $text);
        $this->assertStringContainsString("chaque soir de 20\u{00A0}h à 23\u{00A0}h (heure de Lomé, GMT)", $text);

        foreach (self::PROGRAMME as $index => $title) {
            $this->assertStringContainsString('- Séance '.($index + 1).', ', $text);
            $this->assertStringContainsString($title, $text);
        }

        foreach (['Comment je reçois le lien ?', 'J\'ai raté une séance ?', 'Je n\'ai qu\'un téléphone ?', 'Comment payer ?', 'J\'ai payé, et après ?', 'Vais-je recevoir une attestation ?'] as $question) {
            $this->assertStringContainsString($question, $text);
        }

        $this->assertStringContainsString('Places non limitées', $text);
        $this->assertStringContainsString('le programme à présenter est celui des séances en direct', $text);
        $this->assertStringNotContainsString('Ne décris aucun programme', $text);

        // Après la semaine, Ama ne présente plus l'offre.
        Carbon::setTestNow(CarbonImmutable::parse('2026-10-08 08:00', 'Africa/Lome'));
        $this->assertStringNotContainsString('EN DIRECT', AmaKnowledge::formations());
    }

    public function test_the_price_shown_follows_the_admin(): void
    {
        $formation = $this->formation(['price' => 35000]);

        $this->get(route('formations.show', $formation))->assertOk()
            ->assertSee("envoyez le montant exact (35\u{00A0}000\u{00A0}FCFA)", false);
    }
}
