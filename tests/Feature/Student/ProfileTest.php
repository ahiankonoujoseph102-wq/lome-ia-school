<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed_with_current_details(): void
    {
        $user = User::factory()->create(['name' => 'Ama Koffi', 'phone' => '+22890123456']);

        $this->actingAs($user)
            ->get(route('student.profile'))
            ->assertOk()
            ->assertSee('Mon profil')
            ->assertSee('Ama Koffi')
            ->assertSee('value="90123456"', false)
            ->assertSee('Sécurité du compte');
    }

    public function test_old_settings_profile_url_redirects_to_the_new_profile_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertRedirect('/mon-espace/profil');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('student.profile.update'), [
                'name' => 'Kossi Mensah',
                'email' => 'kossi@example.com',
                'phone_country' => '+229',
                'phone' => '97 00 11 22',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('student.profile'));

        $user->refresh();
        $this->assertSame('Kossi Mensah', $user->name);
        $this->assertSame('kossi@example.com', $user->email);
        $this->assertSame('+22997001122', $user->phone);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('student.profile.update'), [
            'name' => 'Nouveau Nom',
            'email' => $user->email,
            'phone' => $user->phone,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_phone_number_must_stay_unique(): void
    {
        User::factory()->create(['phone' => '+22890000001']);
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('student.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone_country' => '+228',
            'phone' => '90000001',
        ])->assertSessionHasErrors(['phone' => 'Ce numéro WhatsApp est déjà utilisé par un autre compte.']);
    }

    public function test_profile_photo_is_resized_and_stored(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('student.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => UploadedFile::fake()->image('photo.png', 900, 600),
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($user->avatar_path)) ?: [0, 0];
        $this->assertSame([320, 320], [$width, $height]);

        $this->get(route('dashboard'))->assertSee($user->avatar_url, false);
    }

    public function test_profile_photo_must_be_an_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('student.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('avatar');
    }

    public function test_profile_photo_can_be_removed(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/ancienne.jpg', 'x');
        $user = User::factory()->create();
        $user->forceFill(['avatar_path' => 'avatars/ancienne.jpg'])->save();

        $this->actingAs($user)->delete(route('student.profile.avatar.destroy'))->assertRedirect(route('student.profile'));

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing('avatars/ancienne.jpg');
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('student.profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('student.profile.destroy'), ['password' => 'mauvais-mot-de-passe'])
            ->assertSessionHasErrorsIn('deleteAccount', 'password');

        $this->assertNotNull($user->fresh());
    }
}
