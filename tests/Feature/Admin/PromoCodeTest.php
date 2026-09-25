<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminAction;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\AffiliateCommission;
use App\Models\Formation;
use App\Models\PromoCode;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Affiliates\AffiliateTestCase;

/**
 * Codes promo : création dans l'admin, récapitulatif avec prix barré, validité (dates, limites,
 * formations concernées), remise enregistrée, seuil et commission calculés sur le montant payé.
 */
class PromoCodeTest extends AffiliateTestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function code(array $attributes = []): PromoCode
    {
        return PromoCode::query()->create(array_merge(['code' => 'RENTREE', 'type' => 'percent', 'value' => 20], $attributes));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pay(User $student, Formation $formation, string $code, array $extra = []): TestResponse
    {
        return $this->actingAs($student)->post(route('checkout.pay', $formation), [...$this->proof(), 'promo_code' => $code, ...$extra]);
    }

    public function test_admin_creates_a_promo_code_for_some_formations(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $other = Formation::factory()->create(['price' => 10000]);

        $this->actingAs($admin)->get(route('admin.promo-codes.create'))->assertOk();
        $this->actingAs($admin)->post(route('admin.promo-codes.store'), [
            'code' => 'rentrée !',
            'type' => 'percent',
            'value' => 150,
            'all_formations' => '1',
            'is_active' => '1',
        ])->assertSessionHasErrors(['code', 'value']);

        $this->actingAs($admin)->post(route('admin.promo-codes.store'), [
            'code' => 'rentree-2026',
            'description' => 'Offre de rentrée',
            'type' => 'fixed',
            'value' => 5000,
            'starts_at' => '2026-10-01T08:00',
            'ends_at' => '2026-10-31T20:00',
            'max_uses' => 50,
            'max_uses_per_user' => 1,
            'all_formations' => '0',
            'formations' => [$this->formation->id],
            'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.promo-codes.index'));

        $promo = PromoCode::query()->firstOrFail();
        $this->assertSame('RENTREE-2026', $promo->code);
        $this->assertFalse($promo->all_formations);
        $this->assertTrue($promo->appliesTo($this->formation));
        $this->assertFalse($promo->appliesTo($other));
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::PromoCodeCreated->value]);

        $this->actingAs($admin)->get(route('admin.promo-codes.index'))->assertOk()->assertSee('RENTREE-2026')->assertSee('Offre de rentrée');
    }

    public function test_summary_shows_the_crossed_out_price_and_the_new_amount(): void
    {
        $this->code();
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('checkout.show', [$this->formation, 'code' => 'rentree']))
            ->assertOk()
            ->assertSee('Code RENTREE appliqué')
            ->assertSee("<s data-test=\"original-price\">25\u{00A0}000\u{00A0}FCFA</s>", false)
            ->assertSee("-5\u{00A0}000\u{00A0}FCFA")
            ->assertSee("20\u{00A0}000\u{00A0}FCFA")
            ->assertSee('name="promo_code" value="RENTREE"', false);

        $this->actingAs($student)->get(route('checkout.show', [$this->formation, 'code' => 'INCONNU']))
            ->assertOk()
            ->assertSee('Ce code promo n&#039;existe pas ou n&#039;est plus actif.', false)
            ->assertDontSee('name="promo_code"', false);
    }

    public function test_code_validity_dates_limits_and_formations(): void
    {
        $student = User::factory()->create();
        $cases = [
            'Ce code promo n&#039;existe pas ou n&#039;est plus actif.' => ['is_active' => false],
            'Ce code promo sera valable à partir du' => ['starts_at' => now()->addDay()],
            'Ce code promo a expiré' => ['ends_at' => now()->subMinute()],
            'Ce code promo ne s&#039;applique pas à cette formation.' => ['all_formations' => false],
        ];

        foreach ($cases as $message => $attributes) {
            PromoCode::query()->delete();
            $this->code($attributes);
            $this->actingAs($student)->get(route('checkout.show', [$this->formation, 'code' => 'RENTREE']))->assertSee($message, false)->assertDontSee('Code RENTREE appliqué');
        }

        // Dans ses dates et pour cette formation : accepté.
        PromoCode::query()->delete();
        $this->code(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'all_formations' => false])->formations()->attach($this->formation);
        $this->actingAs($student)->get(route('checkout.show', [$this->formation, 'code' => 'RENTREE']))->assertSee('Code RENTREE appliqué');
    }

    public function test_payment_with_a_code_records_the_discount(): void
    {
        $promo = $this->code();
        $student = User::factory()->create();

        $this->pay($student, $this->formation, 'rentree')->assertSessionHasNoErrors()->assertRedirect();

        $purchase = Purchase::query()->firstOrFail();
        $this->assertSame(20000, $purchase->amount);
        $this->assertSame(25000, $purchase->original_amount);
        $this->assertSame(5000, $purchase->discount_amount);
        $this->assertSame($promo->id, $purchase->promo_code_id);

        $this->actingAs($student)->get(route('student.purchases.show', $purchase))->assertOk()->assertSee('Remise (code promo)')->assertSee('RENTREE');
    }

    public function test_usage_limits_in_total_and_per_student(): void
    {
        $this->code(['max_uses' => 2, 'max_uses_per_user' => 1]);
        $second = Formation::factory()->create(['price' => 20000]);
        $first = User::factory()->create();

        $this->pay($first, $this->formation, 'RENTREE')->assertSessionHasNoErrors();
        // Même élève, autre formation : limite par élève atteinte.
        $this->pay($first, $second, 'RENTREE')->assertSessionHasErrors('promo_code');

        $this->pay(User::factory()->create(), $this->formation, 'RENTREE')->assertSessionHasNoErrors();
        // Limite totale atteinte.
        $this->pay(User::factory()->create(), $this->formation, 'RENTREE')->assertSessionHasErrors('promo_code');
        $this->assertSame(2, Purchase::query()->count());

        // Un achat refusé libère une utilisation.
        Purchase::query()->latest('id')->firstOrFail()->forceFill(['status' => PurchaseStatus::Rejected])->save();
        $this->pay(User::factory()->create(), $this->formation, 'RENTREE')->assertSessionHasNoErrors();
    }

    public function test_the_access_threshold_applies_to_the_amount_really_paid(): void
    {
        $formation = Formation::factory()->create(['price' => 60000]);
        $this->code(['value' => 20]);
        $student = User::factory()->create();

        // 60 000 FCFA : vérification ; avec le code, 48 000 FCFA : accès provisoire immédiat.
        $this->actingAs($student)->get(route('checkout.show', $formation))->assertSee('Accès ouvert après vérification du paiement');
        $this->actingAs($student)->get(route('checkout.show', [$formation, 'code' => 'RENTREE']))->assertSee("Accès immédiat dès l'envoi de la preuve de paiement", false);

        $this->pay($student, $formation, 'RENTREE')->assertSessionHasNoErrors();

        $purchase = Purchase::query()->firstOrFail();
        $this->assertSame(48000, $purchase->amount);
        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);
        $this->assertTrue($student->isEnrolledIn($formation));
    }

    public function test_affiliate_commission_is_computed_on_the_amount_really_paid(): void
    {
        $this->code(['value' => 20]);
        $affiliate = $this->affiliate();
        $referral = $this->click($affiliate);
        $buyer = User::factory()->create();

        $this->withReferral($referral)->pay($buyer, $this->formation, 'RENTREE')->assertSessionHasNoErrors();
        $purchase = $this->validatePayment(Purchase::query()->firstOrFail());

        $commission = AffiliateCommission::query()->firstOrFail();
        $this->assertSame($purchase->id, $commission->purchase_id);
        $this->assertSame(20000, $commission->base_amount);
        $this->assertSame(2000, $commission->amount);
    }

    public function test_statistics_per_code(): void
    {
        $promo = $this->code();
        $this->pay(User::factory()->create(), $this->formation, 'RENTREE');
        $this->validatePayment(Purchase::query()->firstOrFail());

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('admin.promo-codes.index'))
            ->assertOk()
            ->assertSee("20\u{00A0}000\u{00A0}FCFA")
            ->assertSee("5\u{00A0}000\u{00A0}FCFA");
        $this->assertSame(1, $promo->uses()->count());
    }
}
