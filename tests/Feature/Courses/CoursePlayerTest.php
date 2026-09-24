<?php

namespace Tests\Feature\Courses;

use App\Enums\EnrollmentSource;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\CourseModule;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\LessonView;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoursePlayerTest extends TestCase
{
    use RefreshDatabase;

    private Formation $formation;

    private Lesson $first;

    private Lesson $second;

    private Lesson $preview;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('lesson_resources');
        Mail::fake();

        $this->formation = Formation::factory()->create(['title' => 'Formation ChatGPT', 'slug' => 'chatgpt']);
        $intro = CourseModule::factory()->for($this->formation)->create(['title' => 'Bien démarrer', 'position' => 1]);
        $advanced = CourseModule::factory()->for($this->formation)->create(['title' => 'Aller plus loin', 'position' => 2]);
        $this->preview = Lesson::factory()->for($intro, 'module')->preview()->create(['title' => 'Présentation de la formation', 'position' => 1, 'duration_seconds' => 120]);
        $this->first = Lesson::factory()->for($intro, 'module')->create(['title' => 'Créer son compte', 'position' => 2, 'duration_seconds' => 300, 'video_id' => 'abcDEF12345']);
        $this->second = Lesson::factory()->for($advanced, 'module')->create(['title' => 'Écrire un bon prompt', 'position' => 1, 'duration_seconds' => 600]);
        Lesson::factory()->for($advanced, 'module')->draft()->create(['title' => 'Leçon en préparation', 'position' => 2]);
    }

    private function student(): User
    {
        $student = User::factory()->create();
        $student->enroll($this->formation, EnrollmentSource::Purchase);

        return $student;
    }

    public function test_enrolled_student_watches_a_lesson_in_the_nocookie_player(): void
    {
        $this->actingAs($this->student())
            ->get(route('lessons.show', [$this->formation, $this->first]))
            ->assertOk()
            ->assertSee('Créer son compte')
            ->assertSee('https://www.youtube-nocookie.com/embed/abcDEF12345?rel=0', false)
            ->assertSee('allowfullscreen', false)
            ->assertSee('Marquer comme terminée')
            ->assertSee(route('lessons.show', [$this->formation, $this->preview]), false)
            ->assertSee(route('lessons.show', [$this->formation, $this->second]), false);
    }

    public function test_student_with_provisional_access_can_watch(): void
    {
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->for($this->formation)->status(PurchaseStatus::Provisional)->create();
        app(PurchaseManager::class)->grantAccess($purchase);

        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->first]))->assertOk();
    }

    public function test_non_buyer_and_guest_cannot_watch_a_paid_lesson(): void
    {
        $this->get(route('lessons.show', [$this->formation, $this->first]))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('lessons.show', [$this->formation, $this->first]))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('student.formations.show', $this->formation))->assertForbidden();
    }

    public function test_access_is_lost_when_the_payment_is_rejected(): void
    {
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->for($this->formation)->status(PurchaseStatus::Provisional)->create();
        $manager = app(PurchaseManager::class);
        $manager->grantAccess($purchase);
        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->first]))->assertOk();

        $manager->reject($purchase, User::factory()->create(['role' => UserRole::Admin]), 'Paiement non reçu.');

        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->first]))->assertForbidden();
        $this->actingAs($student)->get(route('student.formations.show', $this->formation))->assertForbidden();
    }

    public function test_free_preview_lesson_is_visible_to_every_visitor(): void
    {
        $this->get(route('lessons.show', [$this->formation, $this->preview]))
            ->assertOk()
            ->assertSee('Présentation de la formation')
            ->assertSee('Accédez à toute la formation')
            ->assertDontSee('Marquer comme terminée')
            ->assertDontSee(route('lessons.show', [$this->formation, $this->first]), false);

        $this->assertSame(0, LessonView::count());
    }

    public function test_unpublished_lesson_is_hidden_from_students_but_visible_to_the_admin(): void
    {
        $draft = Lesson::query()->where('title', 'Leçon en préparation')->firstOrFail();

        $this->actingAs($this->student())->get(route('lessons.show', [$this->formation, $draft]))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('lessons.show', [$this->formation, $draft]))->assertOk();
    }

    public function test_lesson_of_another_formation_returns_not_found(): void
    {
        $other = Formation::factory()->create();

        $this->actingAs($this->student())->get(route('lessons.show', [$other, $this->first]))->assertNotFound();
    }

    public function test_resources_are_protected_like_the_lesson(): void
    {
        Storage::disk('lesson_resources')->put('1/guide.pdf', '%PDF-1.4 exemple');
        $resource = LessonResource::create(['lesson_id' => $this->first->id, 'name' => 'Guide des prompts.pdf', 'path' => '1/guide.pdf', 'mime_type' => 'application/pdf', 'size' => 16]);
        $url = route('lessons.resources.download', [$this->formation, $this->first, $resource]);

        $this->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $response = $this->actingAs($this->student())->get($url)->assertOk();
        $this->assertStringContainsString('Guide des prompts.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // Une ressource d'une autre leçon ne peut pas être demandée via cette leçon.
        $this->actingAs($this->student())->get(route('lessons.resources.download', [$this->formation, $this->second, $resource]))->assertNotFound();
    }

    public function test_student_can_switch_to_the_backup_player(): void
    {
        $driveId = '1AbCdEfGhIjKlMnOpQrStUvWxYz012345';
        $this->first->update(['backup_video_provider' => 'drive', 'backup_video_id' => $driveId]);
        $student = $this->student();

        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->first]))
            ->assertOk()
            ->assertSee('src="https://www.youtube-nocookie.com/embed/abcDEF12345', false)
            ->assertSee('Lecteur de secours')
            ->assertSee(route('lessons.show', [$this->formation, $this->first]).'?lecteur=secours', false);

        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->first]).'?lecteur=secours')
            ->assertOk()
            ->assertSee('src="https://drive.google.com/file/d/'.$driveId.'/preview"', false)
            ->assertDontSee('src="https://www.youtube-nocookie.com', false)
            ->assertSee('Revenir au lecteur principal');

        // Sans lecteur de secours, le paramètre est ignoré.
        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->second]).'?lecteur=secours')
            ->assertOk()
            ->assertDontSee('Lecteur de secours');
    }

    public function test_backup_player_is_protected_like_the_lesson(): void
    {
        $this->first->update(['backup_video_provider' => 'drive', 'backup_video_id' => '1AbCdEfGhIjKlMnOpQrStUvWxYz012345']);

        $this->actingAs(User::factory()->create())
            ->get(route('lessons.show', [$this->formation, $this->first]).'?lecteur=secours')
            ->assertForbidden();
    }

    public function test_drive_resources_are_only_shown_and_opened_for_students_with_access(): void
    {
        $driveUrl = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz012345/view?usp=sharing';
        $link = LessonResource::create(['lesson_id' => $this->preview->id, 'name' => 'Support de cours Drive', 'url' => $driveUrl, 'size' => 0]);
        $url = route('lessons.resources.download', [$this->formation, $this->preview, $link]);

        // Aperçu gratuit : la leçon est visible, mais pas le lien Drive.
        $this->get(route('lessons.show', [$this->formation, $this->preview]))
            ->assertOk()
            ->assertDontSee('Support de cours Drive')
            ->assertDontSee($driveUrl, false);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $student = $this->student();
        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->preview]))
            ->assertOk()
            ->assertSee('Support de cours Drive')
            ->assertDontSee($driveUrl, false);
        $this->actingAs($student)->get($url)->assertRedirect($driveUrl);
    }

    public function test_first_view_is_recorded_once_and_progress_is_tracked(): void
    {
        $student = $this->student();

        $this->travelTo(now()->subHour());
        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->first]))->assertOk();
        $firstViewedAt = LessonView::firstOrFail()->first_viewed_at;
        $this->travelBack();

        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->first]))->assertOk();
        $view = LessonView::firstOrFail();
        $this->assertSame(1, LessonView::count());
        $this->assertTrue($view->first_viewed_at->equalTo($firstViewedAt));
        $this->assertTrue($view->last_viewed_at->greaterThan($firstViewedAt));

        $this->actingAs($student)
            ->post(route('lessons.complete', [$this->formation, $this->first]), ['completed' => 1])
            ->assertRedirect(route('lessons.show', [$this->formation, $this->first]));
        $this->assertNotNull($view->refresh()->completed_at);

        // 1 leçon terminée sur 3 publiées : 33 %, reprise sur la leçon suivante.
        $this->actingAs($student)->get(route('student.formations.show', $this->formation))
            ->assertOk()
            ->assertSee('33 %')
            ->assertSee('Reprendre où j&#039;en étais', false)
            ->assertSee('1 leçon terminée sur 3')
            ->assertSee(route('lessons.show', [$this->formation, $this->second]), false);

        $this->actingAs($student)->post(route('lessons.complete', [$this->formation, $this->first]), ['completed' => 0]);
        $this->assertNull($view->refresh()->completed_at);
    }

    public function test_only_enrolled_students_can_mark_a_lesson_as_completed(): void
    {
        $this->post(route('lessons.complete', [$this->formation, $this->preview]))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->post(route('lessons.complete', [$this->formation, $this->preview]))
            ->assertForbidden();
        $this->assertSame(0, LessonView::count());
    }

    public function test_admin_sees_whether_the_student_has_started_the_formation(): void
    {
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->for($this->formation)->status(PurchaseStatus::Provisional)->create();
        app(PurchaseManager::class)->grantAccess($purchase);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.payments.index'))->assertSee('Non, aucune leçon consultée');

        $this->actingAs($student)->get(route('lessons.show', [$this->formation, $this->first]));

        $this->actingAs($admin)->get(route('admin.payments.index'))->assertSee('Oui, 1re leçon le');
    }

    public function test_public_page_shows_the_real_programme_with_free_previews(): void
    {
        $this->get(route('formations.show', $this->formation))
            ->assertOk()
            ->assertSee('Programme de la formation')
            ->assertSee('2 modules · 3 leçons · 17 min de vidéo')
            ->assertSee('Bien démarrer')
            ->assertSee('Écrire un bon prompt')
            ->assertSee('Aperçu gratuit')
            ->assertSee(route('lessons.show', [$this->formation, $this->preview]), false)
            ->assertDontSee(route('lessons.show', [$this->formation, $this->first]), false)
            ->assertDontSee('Leçon en préparation');
    }

    public function test_programme_is_hidden_when_the_formation_has_no_published_lesson(): void
    {
        $empty = Formation::factory()->create();
        Lesson::factory()->for(CourseModule::factory()->for($empty), 'module')->draft()->create(['title' => 'Brouillon secret']);

        $this->get(route('formations.show', $empty))
            ->assertOk()
            ->assertDontSee('Programme de la formation')
            ->assertDontSee('Brouillon secret');

        $student = User::factory()->create();
        $student->enroll($empty, EnrollmentSource::Purchase);
        $this->actingAs($student)->get(route('student.formations.show', $empty))
            ->assertOk()
            ->assertSee('Les leçons arrivent bientôt');
    }

    public function test_total_duration_is_hidden_when_a_lesson_has_no_duration(): void
    {
        $this->second->update(['duration_seconds' => null]);

        $this->get(route('formations.show', $this->formation))
            ->assertOk()
            ->assertSee('2 modules · 3 leçons')
            ->assertDontSee('de vidéo');
    }
}
