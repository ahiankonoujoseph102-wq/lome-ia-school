<?php

namespace Tests\Feature\Consultations;

use App\Enums\ConsultationRequesterType;
use App\Mail\ConsultationAdminMail;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * « Vous êtes : un particulier / un centre de formation / une entreprise » et nom de la structure,
 * y compris depuis la page Entreprises (« Réserver un appel gratuit »).
 */
class RequesterTypeTest extends ConsultationTestCase
{
    public function test_the_profile_is_required(): void
    {
        $this->book('2026-10-06 10:15', ['requester_type' => ''])->assertSessionHasErrors('requester_type');
        $this->book('2026-10-06 10:15', ['requester_type' => 'association'])->assertSessionHasErrors('requester_type');
        $this->assertSame(0, Consultation::query()->count());
    }

    public function test_an_organization_must_give_its_name(): void
    {
        $this->book('2026-10-06 10:15', ['requester_type' => 'company', 'organization_name' => ''])->assertSessionHasErrors('organization_name');
        $this->book('2026-10-06 10:15', ['requester_type' => 'training_center', 'organization_name' => '  '])->assertSessionHasErrors('organization_name');

        [$consultation] = $this->booked('2026-10-06 10:15', ['requester_type' => 'training_center', 'organization_name' => ' Centre Excellence Lomé ']);

        $this->assertSame(ConsultationRequesterType::TrainingCenter, $consultation->requester_type);
        $this->assertSame('Centre Excellence Lomé', $consultation->organization_name);
    }

    public function test_an_individual_never_keeps_an_organization_name(): void
    {
        [$consultation] = $this->booked('2026-10-06 10:15', ['requester_type' => 'individual', 'organization_name' => 'Ancienne saisie masquée']);

        $this->assertSame(ConsultationRequesterType::Individual, $consultation->requester_type);
        $this->assertNull($consultation->organization_name);
    }

    public function test_company_is_preselected_from_the_business_page_and_can_be_changed(): void
    {
        $this->get(route('consultation', ['profil' => 'entreprise']))
            ->assertOk()
            ->assertSee('value="company" x-model="requester" class="sr-only" required checked', false)
            ->assertSee('Nom de votre centre ou de votre entreprise');

        $this->get(route('consultation'))
            ->assertOk()
            ->assertSee('Vous êtes')
            ->assertDontSee('required checked', false);

        // Modifiable : la personne peut choisir « Un particulier ».
        [$consultation] = $this->booked('2026-10-06 10:15', ['requester_type' => 'individual']);
        $this->assertSame(ConsultationRequesterType::Individual, $consultation->requester_type);
    }

    public function test_admin_list_email_and_export_show_the_profile(): void
    {
        $this->booked('2026-10-06 10:15', ['requester_type' => 'company', 'organization_name' => 'Société Exemple SARL']);

        Mail::assertQueued(ConsultationAdminMail::class, function (ConsultationAdminMail $mail): bool {
            $html = $mail->render();

            return $mail->kind === 'booked' && str_contains($html, 'Entreprise') && str_contains($html, 'Société Exemple SARL');
        });

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.consultations.index'))
            ->assertOk()
            ->assertSee('Entreprise : Société Exemple SARL');

        $csv = $this->actingAs($admin)->get(route('admin.exports.download', 'consultations'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Profil;Structure', $csv);
        $this->assertMatchesRegularExpression('/Entreprise;"?Société Exemple SARL"?;/', $csv);
    }

    public function test_student_record_shows_the_profile(): void
    {
        $student = User::factory()->create(['email' => 'eleve@example.com']);
        $this->booked('2026-10-06 10:15', ['email' => 'eleve@example.com', 'requester_type' => 'training_center', 'organization_name' => 'Centre Exemple']);

        $this->actingAs($this->admin())->get(route('admin.students.show', $student))->assertOk()->assertSee('Centre de formation : Centre Exemple');
    }

    public function test_existing_bookings_are_individuals(): void
    {
        [$consultation] = $this->booked('2026-10-06 10:15');

        $this->assertSame(ConsultationRequesterType::Individual, $consultation->fresh()?->requester_type);
        $this->assertSame('Particulier', $consultation->requesterSummary());
    }

    public function test_privacy_policy_mentions_the_profile(): void
    {
        $this->get(route('legal.privacy'))->assertOk()->assertSee('votre profil (particulier, centre de formation ou entreprise)', false);
    }
}
