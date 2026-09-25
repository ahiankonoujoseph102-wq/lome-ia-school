<?php

namespace Tests\Feature\Admin;

use App\Affiliates\AffiliateManager;
use App\Affiliates\CommissionManager;
use App\Enums\AdminAction;
use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Mail\PaymentValidatedMail;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\ChatGroupMember;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class StudentAdminTest extends AdminTestCase
{
    public function test_search_by_name_whatsapp_or_email_and_filters(): void
    {
        User::factory()->create(['name' => 'Kossi Exemple', 'phone' => '+22890111111', 'email' => 'kossi@exemple.test']);
        User::factory()->create(['name' => 'Ama Exemple', 'phone' => '+22990222222', 'email' => 'ama@exemple.test', 'blocked_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.students.index', ['q' => 'kossi']))->assertOk()->assertSee('Kossi Exemple')->assertDontSee('Ama Exemple');
        $this->actingAs($this->admin)->get(route('admin.students.index', ['q' => '90 22 22']))->assertOk()->assertSee('Ama Exemple')->assertDontSee('Kossi Exemple');
        $this->actingAs($this->admin)->get(route('admin.students.index', ['q' => 'ama@exemple']))->assertOk()->assertSee('Ama Exemple');
        $this->actingAs($this->admin)->get(route('admin.students.index', ['filtre' => 'bloques']))->assertOk()->assertSee('Ama Exemple')->assertDontSee('Kossi Exemple');
    }

    public function test_student_file_shows_contact_purchases_and_history(): void
    {
        $student = User::factory()->create(['name' => 'Afi Exemple', 'email' => 'afi@exemple.test', 'phone' => '+22890333333']);
        $formation = Formation::factory()->create(['title' => 'Formation Exemple']);
        $purchase = Purchase::query()->create(['user_id' => $student->id, 'formation_id' => $formation->id, 'amount' => 25000, 'gateway' => 'manual_mobile_money', 'status' => PurchaseStatus::Pending]);

        $this->actingAs($this->admin)->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee('Afi Exemple')
            ->assertSee('afi@exemple.test')
            ->assertSee('https://wa.me/22890333333', false)
            ->assertSee('Formation Exemple')
            ->assertSee($purchase->status->label())
            ->assertSee('Création du compte');
    }

    public function test_block_and_unblock_are_logged(): void
    {
        $student = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.block', $student))->assertRedirect();
        $this->assertTrue($student->refresh()->isBlocked());
        $this->actingAs($this->admin)->post(route('admin.users.unblock', $student))->assertRedirect();
        $this->assertFalse($student->refresh()->isBlocked());

        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::UserBlocked->value, 'subject_id' => $student->id]);
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::UserUnblocked->value, 'subject_id' => $student->id]);
        $this->actingAs($this->admin)->get(route('admin.students.show', $student))->assertSee('Compte bloqué par Joseph Admin');
    }

    public function test_manual_access_creates_a_validated_manual_purchase_without_commission(): void
    {
        $formation = Formation::factory()->create(['title' => 'Formation Exemple', 'price' => 25000]);
        $affiliate = app(AffiliateManager::class)->activate(User::factory()->create());
        $student = User::factory()->create(['referred_by_affiliate_id' => $affiliate->id]);

        $this->actingAs($this->admin)->post(route('admin.students.access', $student), ['formation_id' => $formation->id, 'amount' => 20000, 'method' => 'manual-cash', 'reason' => ''])
            ->assertSessionHasErrors('reason', null, 'manualAccess');

        $this->actingAs($this->admin)->post(route('admin.students.access', $student), [
            'formation_id' => $formation->id,
            'amount' => 20000,
            'method' => 'manual-cash',
            'reason' => 'Payé en espèces au bureau',
        ])->assertSessionHasNoErrors();

        $purchase = Purchase::query()->firstOrFail();
        $this->assertSame(PurchaseStatus::Validated, $purchase->status);
        $this->assertSame(PaymentGateway::Manual, $purchase->gateway);
        $this->assertSame(20000, $purchase->amount);
        $this->assertSame(25000, $purchase->original_amount);
        $this->assertSame('Espèces', $purchase->payment_method_name);
        $this->assertSame('Payé en espèces au bureau', $purchase->manual_reason);
        $this->assertSame($this->admin->id, $purchase->verified_by);
        $this->assertTrue($student->isEnrolledIn($formation));
        $this->assertTrue(ChatGroupMember::query()->where('user_id', $student->id)->whereNull('removed_at')->exists());
        Mail::assertQueued(PaymentValidatedMail::class, fn (PaymentValidatedMail $mail): bool => $mail->hasTo($student->email));

        // Même avec un code d'affilié : jamais de commission sur un accès manuel.
        $purchase->forceFill(['affiliate_code' => $affiliate->code])->save();
        app(CommissionManager::class)->createFor($purchase);
        $this->assertSame(0, AffiliateCommission::query()->count());
        $this->assertInstanceOf(Affiliate::class, $affiliate);

        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::ManualAccess->value]);
        $this->actingAs($this->admin)->get(route('admin.sales.index', ['gateway' => 'manual']))->assertOk()->assertSee('Formation Exemple')->assertSee('Manuel')->assertSee('Payé en espèces au bureau');

        // Déjà inscrit : refusé.
        $this->actingAs($this->admin)->post(route('admin.students.access', $student), ['formation_id' => $formation->id, 'amount' => 0, 'method' => 'manual-other', 'reason' => 'Doublon'])
            ->assertSessionHasErrors('formation_id', null, 'manualAccess');
        $this->assertSame(1, Purchase::query()->count());
    }
}
