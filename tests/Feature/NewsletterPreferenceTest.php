<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Afi Dossou',
            'email' => 'afi@example.com',
            'phone_country' => '+228',
            'phone' => '90 12 34 56',
            'password' => 'motdepasse1',
        ], $overrides);
    }

    public function test_registration_form_shows_an_unchecked_newsletter_box(): void
    {
        $response = $this->get(route('register'))->assertOk();

        $response->assertSee('Je souhaite être informé(e) des nouvelles formations');
        $this->assertDoesNotMatchRegularExpression('/name="wants_newsletter"[^>]*checked/', $response->getContent());
    }

    public function test_student_is_not_subscribed_when_the_box_is_left_unchecked(): void
    {
        $this->post(route('register.store'), $this->registration())->assertSessionHasNoErrors();

        $this->assertFalse(User::where('email', 'afi@example.com')->firstOrFail()->wants_newsletter);
    }

    public function test_student_can_subscribe_when_registering(): void
    {
        $this->post(route('register.store'), $this->registration(['wants_newsletter' => '1']))->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'afi@example.com')->firstOrFail()->wants_newsletter);
    }

    public function test_profile_shows_the_current_choice(): void
    {
        $user = User::factory()->create(['wants_newsletter' => true]);

        $response = $this->actingAs($user)->get(route('student.profile'))->assertOk();

        $response->assertSee('Je souhaite être informé(e) des nouvelles formations');
        $this->assertMatchesRegularExpression('/name="wants_newsletter"[^>]*checked/', $response->getContent());
    }

    public function test_student_can_change_their_choice_from_the_profile(): void
    {
        $user = User::factory()->create(['phone' => '+22890123456']);
        $profile = ['name' => $user->name, 'email' => $user->email, 'phone_country' => '+228', 'phone' => '90 12 34 56'];

        $this->actingAs($user)
            ->put(route('student.profile.update'), [...$profile, 'wants_newsletter' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->refresh()->wants_newsletter);

        // Case décochée : le navigateur n'envoie pas le champ.
        $this->actingAs($user)
            ->put(route('student.profile.update'), $profile)
            ->assertSessionHasNoErrors();
        $this->assertFalse($user->refresh()->wants_newsletter);
    }

    public function test_privacy_policy_explains_the_newsletter_consent(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Je souhaite être informé(e) des nouvelles formations')
            ->assertSee('jamais cochée par défaut');
    }
}
