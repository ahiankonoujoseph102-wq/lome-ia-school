<?php

namespace Tests\Feature\Courses;

use App\Enums\UserRole;
use App\Models\CourseModule;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Formation $formation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('lesson_resources');
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->formation = Formation::factory()->create(['slug' => 'automatisation-ia']);
    }

    public function test_content_screens_are_reserved_to_the_admin(): void
    {
        $module = CourseModule::factory()->for($this->formation)->create();
        $lesson = Lesson::factory()->for($module, 'module')->create();
        $student = User::factory()->create();

        $this->get(route('admin.content.index'))->assertRedirect(route('login'));
        $this->actingAs($student)->get(route('admin.content.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.content.show', $this->formation))->assertForbidden();
        $this->actingAs($student)->post(route('admin.modules.store', $this->formation), ['title' => 'Piratage'])->assertForbidden();
        $this->actingAs($student)->get(route('admin.lessons.edit', $lesson))->assertForbidden();
        $this->actingAs($student)->post(route('admin.lessons.publish', $lesson))->assertForbidden();

        $this->assertSame(1, CourseModule::count());
    }

    public function test_admin_creates_a_module_then_a_lesson_from_a_youtube_link(): void
    {
        $this->actingAs($this->admin)->get(route('admin.content.index'))->assertOk()->assertSee($this->formation->title);

        $this->actingAs($this->admin)
            ->post(route('admin.modules.store', $this->formation), ['title' => 'Introduction'])
            ->assertRedirect();
        $module = CourseModule::firstOrFail();
        $this->assertSame('Introduction', $module->title);

        $this->actingAs($this->admin)->get(route('admin.lessons.create', $module))->assertOk()->assertSee('Lien de la vidéo YouTube');

        $this->actingAs($this->admin)->post(route('admin.lessons.store', $module), [
            'course_module_id' => $module->id,
            'title' => 'Bienvenue',
            'description' => 'Présentation du parcours.',
            'video_url' => 'https://youtu.be/abcDEF12345?si=partage',
            'duration' => '4:30',
            'is_published' => '1',
            'is_preview' => '1',
            'resources' => [UploadedFile::fake()->create('Guide.pdf', 120, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $lesson = Lesson::firstOrFail();
        $this->assertSame('youtube', $lesson->video_provider);
        $this->assertSame('abcDEF12345', $lesson->video_id);
        $this->assertSame(270, $lesson->duration_seconds);
        $this->assertTrue($lesson->is_published);
        $this->assertTrue($lesson->is_preview);
        $resource = LessonResource::firstOrFail();
        $this->assertSame('Guide.pdf', $resource->name);
        Storage::disk('lesson_resources')->assertExists($resource->path);

        $this->actingAs($this->admin)->get(route('admin.content.show', $this->formation))
            ->assertOk()
            ->assertSee('Bienvenue')
            ->assertSee('Publiée')
            ->assertSee('Aperçu gratuit')
            ->assertSee('4 min');
    }

    public function test_unknown_video_link_is_refused(): void
    {
        $module = CourseModule::factory()->for($this->formation)->create();

        $this->actingAs($this->admin)->post(route('admin.lessons.store', $module), [
            'course_module_id' => $module->id,
            'title' => 'Leçon',
            'video_url' => 'https://exemple.com/ma-video',
        ])->assertSessionHasErrors('video_url');

        $this->assertSame(0, Lesson::count());
    }

    public function test_lesson_cannot_be_moved_into_a_module_of_another_formation(): void
    {
        $module = CourseModule::factory()->for($this->formation)->create();
        $foreign = CourseModule::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.lessons.store', $module), [
            'course_module_id' => $foreign->id,
            'title' => 'Leçon',
        ])->assertSessionHasErrors('course_module_id');
    }

    public function test_admin_reorders_publishes_and_deletes_lessons(): void
    {
        $module = CourseModule::factory()->for($this->formation)->create();
        $a = Lesson::factory()->for($module, 'module')->create(['title' => 'A', 'position' => 1]);
        $b = Lesson::factory()->for($module, 'module')->create(['title' => 'B', 'position' => 2]);
        $c = Lesson::factory()->for($module, 'module')->create(['title' => 'C', 'position' => 3]);

        $this->actingAs($this->admin)->post(route('admin.lessons.move', [$c, 'up']))->assertRedirect();
        $this->assertSame(['A', 'C', 'B'], $module->lessons()->pluck('title')->all());

        $this->actingAs($this->admin)->post(route('admin.lessons.move', [$a, 'up']));
        $this->assertSame(['A', 'C', 'B'], $module->lessons()->pluck('title')->all());

        $this->actingAs($this->admin)->post(route('admin.lessons.publish', $b));
        $this->assertFalse($b->refresh()->is_published);
        $this->actingAs($this->admin)->post(route('admin.lessons.publish', $b));
        $this->assertTrue($b->refresh()->is_published);

        Storage::disk('lesson_resources')->put('x/fiche.pdf', 'contenu');
        $b->resources()->create(['name' => 'fiche.pdf', 'path' => 'x/fiche.pdf', 'size' => 7]);

        $this->actingAs($this->admin)->delete(route('admin.lessons.destroy', $b))->assertRedirect();
        $this->assertModelMissing($b);
        Storage::disk('lesson_resources')->assertMissing('x/fiche.pdf');
    }

    public function test_admin_reorders_renames_and_deletes_modules(): void
    {
        $first = CourseModule::factory()->for($this->formation)->create(['title' => 'Premier', 'position' => 1]);
        $second = CourseModule::factory()->for($this->formation)->create(['title' => 'Second', 'position' => 2]);
        Lesson::factory()->for($second, 'module')->create();

        $this->actingAs($this->admin)->post(route('admin.modules.move', [$second, 'up']));
        $this->assertSame(['Second', 'Premier'], $this->formation->modules()->pluck('title')->all());

        $this->actingAs($this->admin)->put(route('admin.modules.update', $first), ['title' => 'Premiers pas']);
        $this->assertSame('Premiers pas', $first->refresh()->title);

        $this->actingAs($this->admin)->delete(route('admin.modules.destroy', $second));
        $this->assertModelMissing($second);
        $this->assertSame(0, Lesson::count());
    }

    public function test_admin_updates_a_lesson_and_deletes_a_resource(): void
    {
        $module = CourseModule::factory()->for($this->formation)->create();
        $other = CourseModule::factory()->for($this->formation)->create();
        $lesson = Lesson::factory()->for($module, 'module')->create(['title' => 'Ancien titre']);
        Storage::disk('lesson_resources')->put('1/a.pdf', 'x');
        $resource = $lesson->resources()->create(['name' => 'a.pdf', 'path' => '1/a.pdf', 'size' => 1]);

        $this->actingAs($this->admin)->get(route('admin.lessons.edit', $lesson))->assertOk()->assertSee('a.pdf');

        $this->actingAs($this->admin)->put(route('admin.lessons.update', $lesson), [
            'course_module_id' => $other->id,
            'title' => 'Nouveau titre',
            'video_url' => 'https://www.youtube.com/watch?v=ZYX98765432',
            'duration' => '12',
        ])->assertRedirect(route('admin.lessons.edit', $lesson));

        $lesson->refresh();
        $this->assertSame('Nouveau titre', $lesson->title);
        $this->assertSame($other->id, $lesson->course_module_id);
        $this->assertSame('ZYX98765432', $lesson->video_id);
        $this->assertSame(720, $lesson->duration_seconds);
        $this->assertFalse($lesson->is_published);

        $this->actingAs($this->admin)->delete(route('admin.resources.destroy', $resource))->assertRedirect();
        $this->assertModelMissing($resource);
        Storage::disk('lesson_resources')->assertMissing('1/a.pdf');
    }
}
