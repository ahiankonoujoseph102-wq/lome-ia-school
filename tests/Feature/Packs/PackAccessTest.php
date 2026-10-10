<?php

namespace Tests\Feature\Packs;

use App\Enums\EnrollmentSource;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Offres d'accompagnement : l'accès à l'offre ouvre (et retire) l'accès aux formations incluses et au Groupe VIP,
 * sans jamais toucher une formation achetée séparément.
 */
class PackAccessTest extends TestCase
{
    use RefreshDatabase;

    private Formation $chatgpt;

    private Formation $posters;

    private Formation $vip;

    private Formation $pack;

    private int $captures = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');
        Mail::fake();

        $this->chatgpt = Formation::factory()->create(['title' => 'Formation ChatGPT & Claude', 'price' => 5500]);
        $this->posters = Formation::factory()->create(['title' => 'Création d\'affiches pro avec l\'IA', 'price' => 36000]);
        $this->vip = Formation::factory()->groupOnly()->create(['title' => 'Groupe VIP', 'slug' => 'groupe-vip']);
        $this->pack = Formation::factory()->pack()->create(['title' => 'Accompagnement IA, niveau 1', 'price' => 10000]);
        $this->pack->includedFormations()->attach([
            $this->chatgpt->id => ['position' => 1],
            $this->posters->id => ['position' => 2],
            $this->vip->id => ['position' => 3],
        ]);
    }

    private function pay(User $student, Formation $formation): Purchase
    {
        $this->actingAs($student)->post(route('checkout.pay', $formation), [
            'payment_method' => 'tg-mixx',
            // Chaque capture est différente (une capture déjà envoyée est refusée).
            'proof' => UploadedFile::fake()->image('capture.png', 600 + ++$this->captures, 1200),
        ])->assertSessionHasNoErrors();

        return Purchase::query()->where('user_id', $student->id)->where('formation_id', $formation->id)->latest('id')->firstOrFail();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function inGroup(User $user, Formation $formation): bool
    {
        return ChatGroupMember::query()
            ->where('user_id', $user->id)
            ->where('chat_group_id', ChatGroup::forFormation($formation)->id)
            ->whereNull('removed_at')
            ->exists();
    }

    public function test_a_pack_has_no_chat_group_of_its_own(): void
    {
        $this->assertNull(ChatGroup::query()->where('formation_id', $this->pack->id)->first());
        $this->assertNotNull(ChatGroup::query()->where('formation_id', $this->vip->id)->first());
    }

    public function test_provisional_access_to_a_pack_opens_every_included_formation_and_the_vip_group(): void
    {
        $student = User::factory()->create();

        $purchase = $this->pay($student, $this->pack);

        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);
        $this->assertTrue($student->isEnrolledIn($this->pack));
        $this->assertTrue($student->isEnrolledIn($this->chatgpt));
        $this->assertTrue($student->isEnrolledIn($this->posters));
        $this->assertTrue($student->isEnrolledIn($this->vip));

        $included = $student->enrollments()->where('formation_id', $this->chatgpt->id)->firstOrFail();
        $this->assertSame(EnrollmentSource::Pack, $included->source);
        $this->assertSame($this->pack->id, $included->pack_id);
        // L'achat reste relié au seul accès de l'offre (un refus retire bien l'offre elle-même).
        $this->assertNull($included->purchase_id);
        $this->assertSame($this->pack->id, $purchase->enrollment()->firstOrFail()->formation_id);

        $this->assertTrue($this->inGroup($student, $this->chatgpt));
        $this->assertTrue($this->inGroup($student, $this->posters));
        $this->assertTrue($this->inGroup($student, $this->vip));
        $this->assertSame(0, ChatGroup::query()->where('formation_id', $this->pack->id)->count());

        // Les formations incluses s'ouvrent comme les autres (contrôle FormationPolicy::access).
        $this->actingAs($student)->get(route('student.formations.show', $this->chatgpt))->assertOk();
        $this->actingAs($student)->get(route('chat.show', $this->vip))->assertOk();
    }

    public function test_pending_pack_gives_access_only_after_validation(): void
    {
        $this->pack->update(['price' => 75000]);
        $student = User::factory()->create();

        $purchase = $this->pay($student, $this->pack);

        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertFalse($student->isEnrolledIn($this->chatgpt));
        $this->assertFalse($this->inGroup($student, $this->vip));

        $this->actingAs($this->admin())->post(route('admin.payments.approve', $purchase))->assertSessionHasNoErrors();

        $this->assertTrue($student->isEnrolledIn($this->chatgpt));
        $this->assertTrue($student->isEnrolledIn($this->posters));
        $this->assertTrue($this->inGroup($student, $this->vip));
    }

    public function test_rejection_removes_the_pack_accesses_and_the_vip_group(): void
    {
        $student = User::factory()->create();
        $purchase = $this->pay($student, $this->pack);

        $this->actingAs($this->admin())
            ->post(route('admin.payments.reject', $purchase), ['reason' => 'Paiement non reçu sur notre compte.'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($student->isEnrolledIn($this->pack));
        $this->assertFalse($student->isEnrolledIn($this->chatgpt));
        $this->assertFalse($student->isEnrolledIn($this->posters));
        $this->assertFalse($student->isEnrolledIn($this->vip));
        $this->assertFalse($this->inGroup($student, $this->chatgpt));
        $this->assertFalse($this->inGroup($student, $this->vip));
    }

    public function test_refund_removes_the_pack_accesses(): void
    {
        $student = User::factory()->create();
        $purchase = $this->pay($student, $this->pack);
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.payments.approve', $purchase));

        app(PurchaseManager::class)->refund($purchase->fresh(), $admin);

        $this->assertSame(0, $student->enrollments()->count());
        $this->assertFalse($this->inGroup($student, $this->vip));
    }

    public function test_a_formation_bought_separately_is_kept_without_duplicate(): void
    {
        $student = User::factory()->create();
        $own = $this->pay($student, $this->chatgpt);
        $this->actingAs($this->admin())->post(route('admin.payments.approve', $own));

        $purchase = $this->pay($student, $this->pack);

        // Aucune erreur, aucun doublon : l'accès d'origine est gardé tel quel.
        $this->assertSame(1, Enrollment::query()->where('user_id', $student->id)->where('formation_id', $this->chatgpt->id)->count());
        $kept = $student->enrollments()->where('formation_id', $this->chatgpt->id)->firstOrFail();
        $this->assertSame(EnrollmentSource::Purchase, $kept->source);
        $this->assertSame($own->id, $kept->purchase_id);
        $this->assertNull($kept->pack_id);

        $this->actingAs($this->admin())
            ->post(route('admin.payments.reject', $purchase), ['reason' => 'Paiement non reçu sur notre compte.'])
            ->assertSessionHasNoErrors();

        // L'offre refusée ne retire jamais la formation achetée séparément, ni son groupe.
        $this->assertTrue($student->isEnrolledIn($this->chatgpt));
        $this->assertTrue($this->inGroup($student, $this->chatgpt));
        $this->assertFalse($student->isEnrolledIn($this->posters));
        $this->assertFalse($student->isEnrolledIn($this->vip));
    }

    public function test_access_given_by_the_team_or_a_free_registration_is_never_removed(): void
    {
        $student = User::factory()->create();
        $student->enroll($this->posters, EnrollmentSource::Admin);

        $purchase = $this->pay($student, $this->pack);
        $this->actingAs($this->admin())
            ->post(route('admin.payments.reject', $purchase), ['reason' => 'Paiement non reçu sur notre compte.']);

        $this->assertTrue($student->isEnrolledIn($this->posters));
        $this->assertFalse($student->isEnrolledIn($this->chatgpt));
    }

    public function test_a_formation_shared_by_two_packs_stays_while_one_pack_remains(): void
    {
        $level2 = Formation::factory()->pack()->create(['price' => 30000]);
        $level2->includedFormations()->attach([$this->chatgpt->id => ['position' => 1], $this->vip->id => ['position' => 2]]);
        $student = User::factory()->create();
        $admin = $this->admin();

        $first = $this->pay($student, $this->pack);
        $this->actingAs($admin)->post(route('admin.payments.approve', $first));
        $second = $this->pay($student, $level2);
        $this->actingAs($admin)->post(route('admin.payments.approve', $second));

        app(PurchaseManager::class)->refund($first->fresh(), $admin);

        // Toujours incluse dans le niveau 2 : l'accès reste et passe à cette offre.
        $this->assertTrue($student->isEnrolledIn($this->chatgpt));
        $this->assertSame($level2->id, $student->enrollments()->where('formation_id', $this->chatgpt->id)->value('pack_id'));
        $this->assertTrue($this->inGroup($student, $this->vip));
        // Seulement dans le niveau 1 : retirée.
        $this->assertFalse($student->isEnrolledIn($this->posters));

        app(PurchaseManager::class)->refund($second->fresh(), $admin);
        $this->assertSame(0, $student->enrollments()->count());
        $this->assertFalse($this->inGroup($student, $this->vip));
    }

    public function test_access_granted_and_revoked_by_the_command_follows_the_pack(): void
    {
        $student = User::factory()->create(['email' => 'eleve@example.com']);

        $this->artisan('app:grant-access', ['email' => 'eleve@example.com', 'formation' => $this->pack->slug])->assertSuccessful();
        $this->assertTrue($student->isEnrolledIn($this->chatgpt));
        $this->assertTrue($this->inGroup($student, $this->vip));

        $this->artisan('app:grant-access', ['email' => 'eleve@example.com', 'formation' => $this->pack->slug, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($student->isEnrolledIn($this->chatgpt));
        $this->assertFalse($this->inGroup($student, $this->vip));
    }

    public function test_the_vip_group_is_never_sold_nor_shown(): void
    {
        $student = User::factory()->create();

        $this->get(route('formations.show', $this->vip))->assertNotFound();
        $this->actingAs($student)->get(route('checkout.show', $this->vip))->assertNotFound();
        $this->actingAs($student)->post(route('checkout.pay', $this->vip), [
            'payment_method' => 'tg-mixx',
            'proof' => UploadedFile::fake()->image('capture.png', 600, 1200),
        ])->assertNotFound();
        $this->assertSame(0, Purchase::query()->count());

        // Un élève sans offre n'entre pas dans le Groupe VIP.
        $this->actingAs($student)->get(route('chat.show', $this->vip))->assertForbidden();
    }

    public function test_the_student_space_lists_included_formations_but_not_the_pack_nor_the_group(): void
    {
        $student = User::factory()->create();
        $this->pay($student, $this->pack);

        $this->actingAs($student)->get(route('student.formations.index'))
            ->assertOk()
            ->assertSee($this->chatgpt->title)
            ->assertSee($this->posters->title)
            ->assertSee('Incluse dans « Accompagnement IA, niveau 1 »', false)
            ->assertSee('Accès provisoire')
            ->assertDontSee(route('student.formations.show', $this->vip))
            ->assertDontSee(route('student.formations.show', $this->pack));

        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee($this->chatgpt->title);
        $this->actingAs($student)->get(route('student.formations.show', $this->pack))->assertRedirect(route('student.formations.index'));
        $this->actingAs($student)->get(route('student.formations.show', $this->vip))->assertRedirect(route('chat.show', $this->vip));
        $this->actingAs($student)->get(route('chat.index'))->assertOk()->assertSee('Groupe VIP');
    }

    public function test_packs_and_groups_stay_out_of_the_catalog(): void
    {
        $this->pack->update(['is_active' => true]);

        $this->get(route('formations.index'))
            ->assertOk()
            ->assertSee($this->chatgpt->title)
            ->assertDontSee($this->pack->title)
            ->assertDontSee('Groupe VIP');

        $this->assertFalse(Formation::published()->get()->contains($this->pack));
        $this->assertTrue(Formation::query()->publishedPacks()->get()->contains($this->pack));
    }
}
