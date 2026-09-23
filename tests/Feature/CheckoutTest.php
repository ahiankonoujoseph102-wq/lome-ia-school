<?php

namespace Tests\Feature;

use App\Enums\EnrollmentSource;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_button_leads_to_the_order_summary(): void
    {
        $formation = Formation::factory()->create();

        $this->get(route('formations.show', $formation))
            ->assertSee(route('checkout.show', $formation), false);
    }

    public function test_guest_sees_the_summary_with_account_forms(): void
    {
        $formation = Formation::factory()->create(['title' => 'Automatisation avec l\'IA', 'price' => 25500, 'bonuses' => ['Des modèles prêts à adapter']]);

        $this->get(route('checkout.show', $formation))
            ->assertOk()
            ->assertSee('Récapitulatif de commande')
            ->assertSee('Automatisation avec l&#039;IA', false)
            ->assertSee("25\u{00A0}500\u{00A0}FCFA")
            ->assertSee('Ce qui est inclus')
            ->assertSee('Des modèles prêts à adapter')
            ->assertSee('Créer un compte')
            ->assertSee("J'ai déjà un compte", false)
            ->assertSee(route('register.store'), false)
            ->assertSee(route('login.store'), false)
            ->assertDontSee('Payer par Mobile Money');
    }

    public function test_guest_who_registers_from_the_summary_comes_back_to_it(): void
    {
        Mail::fake();
        $formation = Formation::factory()->create();

        $this->get(route('checkout.show', $formation))->assertOk();

        $this->post(route('register.store'), [
            'name' => 'Edem Agbeko',
            'email' => 'edem@example.com',
            'phone_country' => '+228',
            'phone' => '91 22 33 44',
            'password' => 'motdepasse1',
        ])->assertRedirect(route('checkout.show', $formation));

        $this->get(route('checkout.show', $formation))
            ->assertOk()
            ->assertSee('Payer par Mobile Money')
            ->assertSee('Edem Agbeko');
    }

    public function test_guest_who_logs_in_from_the_summary_comes_back_to_it(): void
    {
        $user = User::factory()->create();
        $formation = Formation::factory()->create();

        $this->get(route('checkout.show', $formation));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('checkout.show', $formation));
    }

    public function test_failed_registration_from_the_summary_keeps_the_visitor_on_the_page(): void
    {
        $formation = Formation::factory()->create();

        $this->get(route('checkout.show', $formation));

        $this->from(route('checkout.show', $formation))
            ->post(route('register.store'), ['form' => 'register', 'name' => 'Edem', 'email' => 'pas-un-email', 'phone' => '', 'password' => 'x'])
            ->assertRedirect(route('checkout.show', $formation))
            ->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_student_sees_the_mobile_money_payment_button_announcing_it_is_coming_soon(): void
    {
        $formation = Formation::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('checkout.show', $formation))
            ->assertOk()
            ->assertSee('Payer par Mobile Money')
            ->assertSee('Le paiement en ligne arrive très bientôt.')
            ->assertSee('T-Money')
            ->assertSee('Flooz');
    }

    public function test_enrolled_student_is_told_they_already_have_access(): void
    {
        $user = User::factory()->create();
        $formation = Formation::factory()->create();
        $user->enroll($formation, EnrollmentSource::Purchase);

        $this->actingAs($user)
            ->get(route('checkout.show', $formation))
            ->assertOk()
            ->assertSee('Vous avez déjà accès à cette formation.')
            ->assertSee(route('student.formations.show', $formation), false)
            ->assertDontSee('Payer par Mobile Money');
    }

    public function test_inactive_formation_cannot_be_ordered(): void
    {
        $this->get(route('checkout.show', Formation::factory()->inactive()->create()))->assertNotFound();
    }
}
