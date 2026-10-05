<?php

namespace Tests\Feature\FreeRegistrations;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\FreeRegistrations\FreeRegistrar;
use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Vidéo de présentation d'une formation gratuite : lien YouTube collé dans l'admin (Formations > Inscriptions),
 * interrupteur « Afficher la vidéo », format horizontal ou vertical ; sur la page, au-dessus du bloc
 * « Pour vous inscrire, créez d'abord votre compte », avec le même lecteur léger que la vidéo d'accueil.
 * Aucun lien n'est écrit dans le code : les identifiants ci-dessous ne servent qu'aux essais.
 */
class FreeFormationVideoTest extends TestCase
{
    use RefreshDatabase;

    private const string ID = 'dQw4w9WgXcQ';

    private const string TITLE = 'Coach Joseph vous présente la formation';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00', 'Africa/Lome'));
    }

    private function freeFormation(): Formation
    {
        $formation = Formation::factory()->create([
            'title' => 'Formation gratuite exemple',
            'price' => 0,
            'event_date' => '2026-10-10',
            'event_location' => 'Adidogomé, Lycée moderne 2, Lomé',
        ]);

        $formation->freeOptions()->create(['label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => 20, 'position' => 1]);
        $formation->freeOptions()->create(['label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50, 'position' => 2]);

        return $formation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(Formation $formation, array $data): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.free-registrations.video', $formation), $data);
    }

    private function guestPage(Formation $formation): string
    {
        auth()->logout();

        return (string) $this->get(route('formations.show', $formation))->assertOk()->getContent();
    }

    private function views(): string
    {
        return file_get_contents(resource_path('views/site/formations/partials/free-video.blade.php'))
            .file_get_contents(resource_path('views/components/site/lazy-youtube.blade.php'));
    }

    public function test_without_a_link_the_page_is_exactly_as_before(): void
    {
        $formation = $this->freeFormation();

        $this->assertNull($formation->presentation_video_url);
        $this->assertFalse($formation->presentation_video_enabled);
        $this->assertNull($formation->presentationVideoId());

        $html = $this->guestPage($formation);

        $this->assertStringNotContainsString('data-test="free-video"', $html);
        $this->assertStringNotContainsString(self::TITLE, $html);
        $this->assertStringNotContainsString('i.ytimg.com', $html);
        $this->assertStringNotContainsString('youtube-nocookie.com', $html);
        $this->assertStringContainsString('Pour vous inscrire, créez d\'abord votre compte', $html);
        $this->assertStringContainsString('data-test="free-create-account"', $html);
    }

    public function test_the_admin_screen_has_the_link_field_and_the_switch(): void
    {
        $formation = $this->freeFormation();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.free-registrations.show', $formation))->assertOk()
            ->assertSee('data-test="free-video-settings"', false)
            ->assertSee('Vidéo de présentation (lien YouTube)')
            ->assertSee('Afficher la vidéo')
            ->assertSee('name="presentation_video_url"', false)
            ->assertSee('inputmode="url"', false)
            ->assertDontSee('type="url"', false)
            ->assertSee('name="presentation_video_enabled"', false)
            ->assertSee('Aucune vidéo affichée sur la page pour le moment.');
    }

    public function test_the_video_shows_above_the_registration_block_with_a_thumbnail_and_no_player_before_the_touch(): void
    {
        $formation = $this->freeFormation();

        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://www.youtube.com/watch?v='.self::ID])
            ->assertRedirect(route('admin.free-registrations.show', $formation))
            ->assertSessionHasNoErrors();

        $html = $this->guestPage($formation);

        $this->assertStringContainsString('data-test="free-video"', $html);
        $this->assertStringContainsString(self::TITLE, $html);
        $this->assertStringContainsString('https://i.ytimg.com/vi/'.self::ID.'/hqdefault.jpg', $html);
        $this->assertStringContainsString('data-test="free-video-play"', $html);

        // Juste au-dessus du bloc « Pour vous inscrire… », puis le bouton « Créer mon compte ».
        $video = strpos($html, 'data-test="free-video"');
        $block = strpos($html, 'data-test="free-registration"');
        $title = strpos($html, 'Pour vous inscrire, créez d\'abord votre compte');
        $button = strpos($html, 'data-test="free-create-account"');
        $this->assertLessThan($block, $video);
        $this->assertLessThan($title, $block);
        $this->assertLessThan($button, $title);
        // Rien d'autre entre la vidéo et le bloc d'inscription.
        $this->assertStringNotContainsString('<section data-reveal>', substr($html, $video, $block - $video));

        // Lecteur ajouté seulement au toucher (modèle Alpine), jamais en lecture automatique, sur youtube-nocookie.com.
        $this->assertStringContainsString('<template x-if="playing">', $html);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/'.self::ID.'?autoplay=1', $html);
        $this->assertStringNotContainsString('https://www.youtube.com/embed/', $html);
        $this->assertStringNotContainsString('muted', $html);
        $this->assertLessThan(strpos($html, '<iframe'), strpos($html, '<template x-if="playing">'));

        // Sans JavaScript, le bouton lecture reste un lien vers YouTube ; vidéo illisible : message et lien.
        $this->assertStringContainsString('href="https://www.youtube.com/watch?v='.self::ID.'"', $html);
        $this->assertStringContainsString('Cette vidéo ne peut pas être lue ici pour le moment.', $html);
    }

    public function test_the_player_is_never_outside_a_touch_template(): void
    {
        $component = file_get_contents(resource_path('views/components/site/lazy-youtube.blade.php'));

        $this->assertSame(1, substr_count($component, '<iframe'));
        $this->assertLessThan(strpos($component, '<iframe'), strpos($component, '<template x-if="playing">'));
        $this->assertStringContainsString('referrerpolicy="strict-origin-when-cross-origin"', $component);
        $this->assertStringContainsString('x-data="homeVideo"', $component);

        // La vidéo de présentation utilise le lecteur commun de la vidéo d'accueil.
        $this->assertStringContainsString('<x-site.lazy-youtube', file_get_contents(resource_path('views/site/formations/partials/free-video.blade.php')));
        $this->assertStringContainsString('<x-site.lazy-youtube', file_get_contents(resource_path('views/site/partials/home-video.blade.php')));
    }

    public function test_short_links_and_shorts_are_accepted_and_shorts_are_shown_vertically(): void
    {
        $formation = $this->freeFormation();

        foreach ([
            'https://youtu.be/'.self::ID.'?si=abc' => 'horizontal',
            'https://www.youtube.com/watch?v='.self::ID => 'horizontal',
            'https://m.youtube.com/watch?v='.self::ID => 'horizontal',
            'https://www.youtube.com/shorts/'.self::ID => 'vertical',
            'https://youtube.com/shorts/'.self::ID.'?feature=share' => 'vertical',
            // Lien collé avec du texte autour et un espace insécable : nettoyé avant la vérification.
            "Regardez : \u{00A0}https://youtu.be/".self::ID => 'horizontal',
        ] as $link => $format) {
            $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => $link])->assertSessionHasNoErrors();

            $formation->refresh();
            $this->assertSame(self::ID, $formation->presentationVideoId(), $link);
            $this->assertSame($format, $formation->presentation_video_format, $link);

            $html = $this->guestPage($formation);
            $this->assertStringContainsString('data-video-format="'.$format.'"', $html, $link);
        }
    }

    public function test_vertical_and_horizontal_videos_get_their_own_frame(): void
    {
        $formation = $this->freeFormation();

        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://www.youtube.com/shorts/'.self::ID])->assertSessionHasNoErrors();
        $vertical = $this->guestPage($formation);
        $this->assertStringContainsString('aspect-[9/16]', $vertical);
        $this->assertStringContainsString('data-test="free-video-backdrop"', $vertical);
        // Aperçu étroit sur téléphone, agrandi seulement au toucher.
        $this->assertStringContainsString('w-40', $vertical);
        $this->assertStringContainsString('data-[open=true]:w-[min(78%,17rem)]', $vertical);

        // Format choisi à la main : il l'emporte sur la détection automatique.
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://www.youtube.com/shorts/'.self::ID, 'presentation_video_format' => 'horizontal'])->assertSessionHasNoErrors();
        $horizontal = $this->guestPage($formation);
        $this->assertStringContainsString('data-video-format="horizontal"', $horizontal);
        $this->assertStringContainsString('aspect-video rounded-2xl', $horizontal);
        $this->assertStringNotContainsString('data-test="free-video-backdrop"', $horizontal);

        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID, 'presentation_video_format' => 'vertical'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('data-video-format="vertical"', $this->guestPage($formation));
    }

    public function test_an_invalid_link_is_refused_and_a_stored_invalid_link_never_breaks_the_page(): void
    {
        $formation = $this->freeFormation();

        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://vimeo.com/123456'])
            ->assertSessionHasErrors('presentation_video_url');
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => ''])
            ->assertSessionHasErrors(['presentation_video_url' => 'Collez le lien YouTube de la vidéo, ou éteignez « Afficher la vidéo ».']);
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID, 'presentation_video_format' => 'carre'])
            ->assertSessionHasErrors('presentation_video_format');

        $this->assertNull($formation->refresh()->presentation_video_url);

        // Lien invalide déjà enregistré (ancienne valeur, saisie directe) : aucune vidéo, la page s'affiche.
        $formation->forceFill(['presentation_video_enabled' => true, 'presentation_video_url' => 'pas un lien'])->save();
        $html = $this->guestPage($formation);
        $this->assertStringNotContainsString('data-test="free-video"', $html);
        $this->assertStringContainsString('data-test="free-create-account"', $html);
    }

    public function test_the_switch_hides_the_video_without_losing_the_link(): void
    {
        $formation = $this->freeFormation();

        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID])->assertSessionHasNoErrors();
        $this->save($formation, ['presentation_video_enabled' => '0', 'presentation_video_url' => 'https://youtu.be/'.self::ID])->assertSessionHasNoErrors();

        $formation->refresh();
        $this->assertSame('https://youtu.be/'.self::ID, $formation->presentation_video_url);
        $this->assertFalse($formation->presentation_video_enabled);

        $html = $this->guestPage($formation);
        $this->assertStringNotContainsString('data-test="free-video"', $html);
        $this->assertStringNotContainsString('i.ytimg.com', $html);

        // Le lien vidé : rien n'est gardé, la page reste sans vidéo.
        $this->save($formation, ['presentation_video_enabled' => '0', 'presentation_video_url' => ''])->assertSessionHasNoErrors();
        $this->assertNull($formation->refresh()->presentation_video_url);
    }

    public function test_ama_and_the_install_banner_never_cover_the_video_and_the_button_stays_close(): void
    {
        $formation = $this->freeFormation();
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID])->assertSessionHasNoErrors();

        $html = $this->guestPage($formation);

        // Le cadre de la vidéo et le bouton « Créer mon compte » sont des zones qu'Ama ne couvre pas.
        $this->assertMatchesRegularExpression('/data-test="free-video-frame"[^>]*data-ama-avoid[^>]*data-ama-clear[^>]*data-install-avoid/s', $html);
        $this->assertMatchesRegularExpression('/<div class="mt-6" data-ama-avoid data-ama-clear data-install-avoid>\s*(?:@error[^\n]*\n)?.*?data-test="free-create-account"/s', $html);

        // Vidéo courte sur téléphone : titre court, aucun texte supplémentaire entre la vidéo et le bloc.
        $partial = file_get_contents(resource_path('views/site/formations/partials/free-video.blade.php'));
        $this->assertStringContainsString('class="mb-6"', $partial);
        $this->assertStringContainsString('text-xl', $partial);
    }

    public function test_the_registration_journey_is_unchanged(): void
    {
        $formation = $this->freeFormation();
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID])->assertSessionHasNoErrors();

        $html = $this->guestPage($formation);

        $this->assertStringContainsString('href="'.route('free-registration.start', $formation).'"', $html);
        $this->assertStringContainsString('data-test="free-login"', $html);
        $this->assertStringContainsString('data-test="free-options-info"', $html);
        $this->assertSame(2, substr_count($html, 'data-test="free-option-info"'));
        $this->assertStringContainsString('20 places restantes', $html);
        $this->assertSame(2, $formation->freeOptions()->count());
        $this->assertSame(0, $formation->freeRegistrations()->count());

        // L'enregistrement de la vidéo ne touche ni à la date, ni au lieu, ni aux options.
        $formation->refresh();
        $this->assertSame('2026-10-10', $formation->event_date?->format('Y-m-d'));
        $this->assertSame('Adidogomé, Lycée moderne 2, Lomé', $formation->event_location);
        $this->assertSame([20, 50], $formation->freeOptions()->orderBy('position')->pluck('capacity')->all());
    }

    public function test_the_create_account_button_comes_right_after_the_video_for_a_visitor(): void
    {
        $formation = $this->freeFormation();
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID])->assertSessionHasNoErrors();

        $html = $this->guestPage($formation);

        // Même lien que le bouton du bloc : le parcours ne change pas.
        $this->assertMatchesRegularExpression('/<div class="mt-4" data-ama-avoid data-ama-clear data-install-avoid>\s*<a href="'.preg_quote(route('free-registration.start', $formation), '/').'" class="btn-gold[^"]*" data-test="free-video-action">\s*Créer mon compte/', $html);

        // Juste après le cadre de la vidéo, avant le bloc d'inscription.
        $frame = strpos($html, 'data-test="free-video-frame"');
        $action = strpos($html, 'data-test="free-video-action"');
        $block = strpos($html, 'data-test="free-registration"');
        $this->assertLessThan($action, $frame);
        $this->assertLessThan($block, $action);
        $this->assertSame(1, substr_count($html, 'data-test="free-video-action"'));
    }

    public function test_a_logged_in_person_sees_the_video_and_the_matching_next_step(): void
    {
        $formation = $this->freeFormation();
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID])->assertSessionHasNoErrors();

        $unconfirmed = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unconfirmed)->get(route('formations.show', $formation))->assertOk()
            ->assertSee('data-test="free-video"', false)
            ->assertSeeInOrder(['data-test="free-video-action"', 'Confirmer mon e-mail', 'data-test="free-registration"'], false);

        $confirmed = User::factory()->create();
        $this->actingAs($confirmed)->get(route('formations.show', $formation))->assertOk()
            ->assertSee('href="'.route('free-registration.options', $formation).'" class="btn-gold w-full !py-4 text-lg" data-test="free-video-action"', false)
            ->assertSee('Choisir mon option');
    }

    public function test_no_extra_button_once_registered_or_when_everything_is_full(): void
    {
        $formation = $this->freeFormation();
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID])->assertSessionHasNoErrors();

        $student = User::factory()->create();
        app(FreeRegistrar::class)->register($student, $formation, $formation->freeOptions()->value('id'));
        $this->actingAs($student)->get(route('formations.show', $formation))->assertOk()
            ->assertSee('data-test="free-video"', false)
            ->assertSee('data-test="free-registered"', false)
            ->assertDontSee('data-test="free-video-action"', false);

        $formation->freeOptions()->update(['capacity' => 1]);
        $formation->freeOptions()->where('id', '!=', $formation->freeOptions()->value('id'))->get()
            ->each(fn ($option) => app(FreeRegistrar::class)->register(User::factory()->create(), $formation, $option->id));

        $html = $this->guestPage($formation);
        $this->assertStringContainsString('data-test="free-full"', $html);
        $this->assertStringNotContainsString('data-test="free-video-action"', $html);
    }

    public function test_paid_formations_never_show_it_and_cannot_be_set(): void
    {
        $paid = Formation::factory()->create(['price' => 25000, 'presentation_video_enabled' => true, 'presentation_video_url' => 'https://youtu.be/'.self::ID]);

        $this->assertNull($paid->presentationVideoId());
        $this->get(route('formations.show', $paid))->assertOk()->assertDontSee(self::TITLE)->assertDontSee('data-test="free-video"', false);

        $this->save($paid, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID])->assertNotFound();
    }

    public function test_the_change_is_logged_and_only_the_admin_can_edit_it(): void
    {
        $formation = $this->freeFormation();
        $this->save($formation, ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://www.youtube.com/shorts/'.self::ID])->assertSessionHasNoErrors();

        $this->assertTrue(AdminActivity::query()
            ->where('action', AdminAction::FreeRegistrationUpdated)
            ->where('description', 'like', '%Vidéo de présentation modifiée%format vertical%')
            ->exists());

        $data = ['presentation_video_enabled' => '1', 'presentation_video_url' => 'https://youtu.be/'.self::ID];
        $this->actingAs(User::factory()->create(['role' => UserRole::Moderator]))->put(route('admin.free-registrations.video', $formation), $data)->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('admin.free-registrations.video', $formation), $data)->assertForbidden();
        auth()->logout();
        $this->put(route('admin.free-registrations.video', $formation), $data)->assertRedirect(route('login'));
    }

    public function test_the_style_does_not_depend_on_forced_dark_mode(): void
    {
        $views = $this->views();

        $this->assertStringNotContainsString('dark:', $views);
        $this->assertStringNotContainsString('bg-white/', $views);
        $this->assertDoesNotMatchRegularExpression('/\b(?:border|ring)-veil|bg-veil|text-gold-(?:200|300)/', $views);
        $this->assertStringContainsString('bg-night-900', $views);
        $this->assertStringContainsString('text-white', $views);
    }

    public function test_the_migration_can_run_again_without_error(): void
    {
        $migration = require database_path('migrations/2026_10_19_100000_add_presentation_video_to_formations.php');

        // Colonnes déjà présentes (base à jour, ou reprise après un échec) : aucune erreur.
        $migration->up();

        // Une seule colonne manquante : seule elle est ajoutée.
        Schema::table('formations', fn ($table) => $table->dropColumn('presentation_video_format'));
        $migration->up();

        foreach (['presentation_video_url', 'presentation_video_enabled', 'presentation_video_format'] as $column) {
            $this->assertTrue(Schema::hasColumn('formations', $column), $column);
        }
    }
}
