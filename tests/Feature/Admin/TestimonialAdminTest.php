<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminAction;
use App\Models\AdminActivity;
use App\Models\Testimonial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TestimonialAdminTest extends AdminTestCase
{
    public function test_admin_adds_the_three_types_and_they_appear_on_the_site(): void
    {
        Storage::fake('public');
        $this->get(route('home'))->assertDontSee('Ils se sont formés avec nous');

        $this->actingAs($this->admin)->post(route('admin.testimonials.store'), ['type' => 'text', 'name' => '', 'text' => ''])->assertSessionHasErrors(['name', 'text']);
        $this->actingAs($this->admin)->post(route('admin.testimonials.store'), ['type' => 'video', 'youtube_url' => 'https://exemple.test/video'])->assertSessionHasErrors('youtube_url');
        $this->actingAs($this->admin)->post(route('admin.testimonials.store'), ['type' => 'screenshot', 'is_active' => '1'])->assertSessionHasErrors('image');

        $this->actingAs($this->admin)->post(route('admin.testimonials.store'), [
            'type' => 'text', 'name' => 'Afi (Exemple)', 'formation' => 'Formation ChatGPT & Claude', 'text' => 'Texte de démonstration.',
            'photo' => UploadedFile::fake()->image('afi.jpg', 900, 600), 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.testimonials.store'), [
            'type' => 'screenshot', 'name' => 'Kossi (Exemple)', 'image' => UploadedFile::fake()->image('capture.png', 1080, 2400), 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.testimonials.store'), [
            'type' => 'video', 'name' => 'Ama (Exemple)', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $photo = (string) Testimonial::query()->where('type', 'text')->value('photo_path');
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($photo)) ?: [0, 0];
        $this->assertSame([320, 320], [$width, $height]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Ils se sont formés avec nous')
            ->assertSee('Texte de démonstration.')
            ->assertSee('Kossi (Exemple)')
            ->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
        $this->assertSame(3, AdminActivity::query()->where('action', AdminAction::TestimonialsUpdated)->count());
    }

    public function test_hide_order_and_delete(): void
    {
        Storage::fake('public');
        $first = Testimonial::query()->create(['type' => 'text', 'name' => 'Premier (Exemple)', 'text' => 'Un.', 'position' => 1]);
        $second = Testimonial::query()->create(['type' => 'text', 'name' => 'Second (Exemple)', 'text' => 'Deux.', 'position' => 2]);

        $this->actingAs($this->admin)->post(route('admin.testimonials.move', [$second, 'up']))->assertRedirect();
        $this->assertSame(['Second (Exemple)', 'Premier (Exemple)'], Testimonial::query()->ordered()->pluck('name')->all());

        $this->actingAs($this->admin)->post(route('admin.testimonials.toggle', $first))->assertRedirect();
        $this->get(route('home'))->assertSee('Second (Exemple)')->assertDontSee('Premier (Exemple)');

        // Tous masqués : la section disparaît.
        $this->actingAs($this->admin)->post(route('admin.testimonials.toggle', $second));
        $this->get(route('home'))->assertDontSee('Ils se sont formés avec nous');

        $this->actingAs($this->admin)->delete(route('admin.testimonials.destroy', $second))->assertRedirect(route('admin.testimonials.index'));
        $this->assertModelMissing($second);
    }

    public function test_the_testimonial_video_link_is_cleaned_before_being_checked(): void
    {
        $this->actingAs($this->admin)->post(route('admin.testimonials.store'), [
            'type' => 'video', 'name' => 'Ama (Exemple)', 'youtube_url' => "Témoignage d'Ama :\u{00A0}youtu.be/dQw4w9WgXcQ\u{200B}", 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', Testimonial::query()->value('youtube_url'));

        $this->actingAs($this->admin)->post(route('admin.testimonials.store'), ['type' => 'video', 'youtube_url' => 'https://exemple.test/video'])
            ->assertSessionHasErrors(['youtube_url' => 'Le lien YouTube doit ressembler à https://www.youtube.com/watch?v=abcdefghijk ou https://youtu.be/abcdefghijk. Sur YouTube : « Partager », puis « Copier le lien ».']);
    }
}
