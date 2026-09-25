<?php

namespace Tests\Feature\Consultations;

use App\Enums\ConsultationStatus;
use App\Mail\ConsultationAdminMail;
use App\Mail\ConsultationMail;
use App\Models\Consultation;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;

class BookingTest extends ConsultationTestCase
{
    public function test_visitor_books_without_account_and_sees_the_confirmation(): void
    {
        $formation = Formation::factory()->create(['title' => 'Automatisation avec l\'IA']);

        $response = $this->book('2026-10-06 10:15', ['name' => 'Kossi Amegan', 'email' => 'Kossi@Example.com', 'formation_id' => $formation->id]);

        $consultation = Consultation::query()->sole();
        $response->assertRedirect($consultation->manageUrl());
        $this->assertSame(ConsultationStatus::Booked, $consultation->status);
        $this->assertSame('2026-10-06 10:15', $consultation->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-06 10:45', $consultation->ends_at->format('Y-m-d H:i'));
        $this->assertSame('kossi@example.com', $consultation->email);
        $this->assertSame($formation->id, $consultation->formation_id);
        $this->assertNull($consultation->user_id);

        Mail::assertQueued(ConsultationMail::class, fn (ConsultationMail $mail): bool => $mail->kind === 'confirmed' && $mail->hasTo('kossi@example.com') && str_contains($mail->render(), self::MEET));
        Mail::assertQueued(ConsultationAdminMail::class, fn (ConsultationAdminMail $mail): bool => $mail->kind === 'booked' && $mail->hasTo(config('site.payments.admin_email')));

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Votre consultation est confirmée')
            ->assertSee('Mardi 6 octobre 2026')
            ->assertSee("11\u{00A0}h\u{00A0}15 au Bénin (GMT+1)", false)
            ->assertSee(self::MEET)
            ->assertSee('Ajouter à mon agenda')
            ->assertSee('Installez-vous dans un endroit calme.');
    }

    public function test_whatsapp_number_is_normalized_like_the_registration(): void
    {
        [$togo] = $this->booked('2026-10-06 10:15', ['phone_country' => '+228', 'phone' => '90 12 34 56']);
        [$benin] = $this->booked('2026-10-06 11:00', ['phone_country' => '+229', 'phone' => '97 00 00 01']);

        $this->assertSame('+22890123456', $togo->phone);
        $this->assertSame('+2290197000001', $benin->phone);

        $this->book('2026-10-06 11:45', ['phone' => '12'])->assertSessionHasErrors('phone');
        $this->book('2026-10-06 11:45', ['topic' => ''])->assertSessionHasErrors('topic');
        $this->book('2026-10-06 11:45', ['starts_at' => ''])->assertSessionHasErrors('starts_at');
    }

    public function test_meet_link_never_appears_on_public_pages(): void
    {
        $this->booked('2026-10-06 10:15');
        $formation = Formation::factory()->create();

        foreach ([route('home'), route('consultation'), route('formations.index'), route('formations.show', $formation), route('about'), route('contact'), route('legal.terms'), route('legal.privacy'), route('sitemap')] as $url) {
            $this->get($url)->assertOk()->assertDontSee('abc-defg-hij');
        }
    }

    public function test_two_people_cannot_book_the_same_slot(): void
    {
        $this->booked('2026-10-06 10:15');

        $this->book('2026-10-06 10:15')->assertSessionHasErrors('starts_at');
        $this->book('2026-10-06 10:30')->assertSessionHasErrors('starts_at'); // pas un créneau de la grille

        $this->assertSame(1, Consultation::query()->count());
    }

    public function test_only_one_upcoming_booking_per_whatsapp_and_per_email(): void
    {
        [$first] = $this->booked('2026-10-06 10:15', ['phone' => '90 11 11 11', 'email' => 'awa@example.com']);

        $this->book('2026-10-07 10:15', ['phone' => '90 11 11 11', 'email' => 'autre@example.com'])->assertSessionHasErrors('email');
        $this->book('2026-10-07 10:15', ['phone' => '90 22 22 22', 'email' => 'awa@example.com'])->assertSessionHasErrors('email');
        $this->assertSame(1, Consultation::query()->count());

        // Après annulation, une nouvelle réservation est possible.
        $first->forceFill(['status' => ConsultationStatus::Cancelled, 'slot_key' => null])->save();
        $this->book('2026-10-07 10:15', ['phone' => '90 11 11 11', 'email' => 'awa@example.com'])->assertSessionHasNoErrors();
    }

    public function test_booking_is_linked_to_an_existing_student_account(): void
    {
        $byPhone = User::factory()->create(['phone' => '+22890555555']);
        $byEmail = User::factory()->create(['email' => 'eleve@example.com']);

        [$first] = $this->booked('2026-10-06 10:15', ['phone' => '90 55 55 55']);
        [$second, $token] = $this->booked('2026-10-06 11:00', ['email' => 'Eleve@example.com']);

        $this->assertSame($byPhone->id, $first->user_id);
        $this->assertSame($byEmail->id, $second->user_id);

        $this->actingAs($byEmail)->get(route('dashboard'))->assertOk()->assertSee('Ma consultation gratuite')->assertSee(route('student.consultation', $second), false);
        $this->actingAs($byEmail)->get(route('student.consultation', $second))->assertRedirect(route('consultation.manage', $token));
        $this->actingAs($byEmail)->get(route('student.consultation', $first))->assertForbidden();
    }

    public function test_honeypot_blocks_robots_without_captcha(): void
    {
        $this->book('2026-10-06 10:15', ['website' => 'https://spam.example'])
            ->assertRedirect(route('consultation'))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Consultation::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_booking_requests_are_rate_limited(): void
    {
        $this->withMiddleware(ThrottleRequests::class);

        for ($i = 0; $i < 5; $i++) {
            $this->book('2026-10-06 10:15', ['topic' => ''])->assertStatus(302);
        }

        $this->book('2026-10-06 10:15')->assertStatus(429);
        $this->assertSame(0, Consultation::query()->count());
    }

    public function test_legal_pages_describe_the_consultation_data(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Si vous réservez une consultation gratuite')
            ->assertSee('supprimés automatiquement 12 mois après la date de la consultation');

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('10. Consultation gratuite')
            ->assertSee('jusqu\'à 3 heures avant le rendez-vous', false)
            ->assertSee('11. Contact');
    }
}
