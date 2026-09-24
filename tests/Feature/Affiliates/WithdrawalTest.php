<?php

namespace Tests\Feature\Affiliates;

use App\Affiliates\AffiliateAccount;
use App\Affiliates\AffiliateManager;
use App\Enums\CommissionStatus;
use App\Enums\WithdrawalStatus;
use App\Mail\AffiliateAdminMail;
use App\Mail\AffiliateMail;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateWithdrawal;
use App\Models\Formation;
use App\Services\PurchaseManager;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;

class WithdrawalTest extends AffiliateTestCase
{
    /**
     * @return array<string, string>
     */
    private function payout(array $overrides = []): array
    {
        return array_merge([
            'payout_country' => 'tg',
            'payout_method' => 'tg-flooz',
            'payout_phone' => '91 00 00 01',
            'payout_name' => 'Awa Mensah',
        ], $overrides);
    }

    /**
     * Affilié avec coordonnées de retrait et des commissions disponibles.
     */
    private function affiliateWithBalance(int $sales = 1): Affiliate
    {
        $affiliate = $this->affiliate();
        $this->actingAs($affiliate->user)->put(route('student.affiliate.payout'), $this->payout())->assertRedirect(route('student.affiliate.withdrawals'));

        for ($i = 0; $i < $sales; $i++) {
            $this->validatedSale($affiliate);
        }

        $this->travel(8)->days();
        $this->artisan('app:release-affiliate-commissions');

        return $affiliate->refresh();
    }

    public function test_payout_details_are_saved_with_the_operators_of_the_payment_methods(): void
    {
        $affiliate = $this->affiliate();

        $this->actingAs($affiliate->user)->get(route('student.affiliate.withdrawals'))->assertOk()->assertSee('Mixx by Yas')->assertSee('Wave')->assertSee('Celtiis')->assertSee('Burkina Faso');
        $this->actingAs($affiliate->user)->put(route('student.affiliate.payout'), $this->payout())->assertSessionHasNoErrors();

        $affiliate->refresh();
        $this->assertSame('tg-flooz', $affiliate->payout_method);
        $this->assertSame('+22891000001', $affiliate->payout_phone);
        $this->assertNull($affiliate->payout_changed_at);
        Mail::assertNotQueued(AffiliateAdminMail::class);

        // Opérateur d'un autre pays : refusé.
        $this->actingAs($affiliate->user)->put(route('student.affiliate.payout'), $this->payout(['payout_method' => 'bj-mtn']))->assertSessionHasErrors('payout_method');
    }

    public function test_changing_payout_details_is_reported_to_the_admin(): void
    {
        $affiliate = $this->affiliate();
        $this->actingAs($affiliate->user)->put(route('student.affiliate.payout'), $this->payout());

        $this->actingAs($affiliate->user)->put(route('student.affiliate.payout'), $this->payout(['payout_country' => 'bj', 'payout_method' => 'bj-mtn', 'payout_phone' => '01 97 00 00 01']));

        $this->assertNotNull($affiliate->refresh()->payout_changed_at);
        $this->assertSame('+2290197000001', $affiliate->payout_phone);
        Mail::assertQueued(AffiliateAdminMail::class, fn (AffiliateAdminMail $mail): bool => $mail->kind === 'payout_changed' && $mail->hasTo(config('site.payments.admin_email')));
    }

    public function test_withdrawal_requires_the_minimum_balance(): void
    {
        // 10 % de 15 000 FCFA = 1 500 FCFA : sous le minimum de 2 000 FCFA.
        $this->formation->update(['price' => 15000]);
        $affiliate = $this->affiliateWithBalance();

        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'))->assertSessionHasErrors('withdrawal');
        $this->assertSame(0, AffiliateWithdrawal::query()->count());

        config(['affiliate.min_withdrawal' => 1000]);
        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'))->assertSessionHasNoErrors();
        $this->assertSame(1500, AffiliateWithdrawal::query()->sole()->amount);
    }

    public function test_only_one_withdrawal_request_at_a_time(): void
    {
        $affiliate = $this->affiliateWithBalance(2);

        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'))->assertSessionHasNoErrors();
        $withdrawal = AffiliateWithdrawal::query()->sole();
        $this->assertSame(5000, $withdrawal->amount);
        $this->assertSame('Flooz / Moov Money', $withdrawal->payout_method_name);
        Mail::assertQueued(AffiliateAdminMail::class, fn (AffiliateAdminMail $mail): bool => $mail->kind === 'withdrawal_requested');

        // Nouvelle commission disponible pendant la demande : une seconde demande reste impossible.
        $this->validatedSale($affiliate);
        $this->travel(8)->days();
        $this->artisan('app:release-affiliate-commissions');

        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'))->assertSessionHasErrors('withdrawal');
        $this->assertSame(1, AffiliateWithdrawal::query()->count());
        $this->assertSame(2500, app(AffiliateAccount::class)->balance($affiliate));
    }

    public function test_withdrawal_requires_payout_details(): void
    {
        $affiliate = $this->affiliate();
        $this->validatedSale($affiliate);
        $this->travel(8)->days();
        $this->artisan('app:release-affiliate-commissions');

        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'))->assertSessionHasErrors('withdrawal');
    }

    public function test_paid_withdrawal_deducts_the_real_fee(): void
    {
        $affiliate = $this->affiliateWithBalance(2);
        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'));
        $withdrawal = AffiliateWithdrawal::query()->sole();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.affiliates.withdrawals'))->assertOk()->assertSee('+228 91 00 00 01')->assertSee('Awa Mensah');

        // Frais supérieurs au montant : refusés.
        $this->actingAs($admin)->post(route('admin.affiliates.withdrawals.pay', $withdrawal), ['fee' => 5000, 'transaction_id' => 'FL261001.0001'])->assertSessionHasErrors('fee');

        $this->actingAs($admin)->post(route('admin.affiliates.withdrawals.pay', $withdrawal), ['fee' => 150, 'transaction_id' => 'FL261001.0001'])->assertRedirect(route('admin.affiliates.withdrawals'));

        $withdrawal->refresh();
        $this->assertSame(WithdrawalStatus::Paid, $withdrawal->status);
        $this->assertSame(150, $withdrawal->fee);
        $this->assertSame(4850, $withdrawal->net_amount);
        $this->assertSame(2, AffiliateCommission::query()->where('status', CommissionStatus::Paid)->count());
        $this->assertSame(0, app(AffiliateAccount::class)->balance($affiliate));
        $this->assertSame(5000, app(AffiliateAccount::class)->summary($affiliate)['paid']);
        Mail::assertQueued(AffiliateMail::class, fn (AffiliateMail $mail): bool => $mail->kind === 'withdrawal_paid' && $mail->hasTo($affiliate->user->email));

        $this->actingAs($affiliate->user)->get(route('student.affiliate.withdrawals'))
            ->assertSee(Money::fcfa(150))
            ->assertSee(Money::fcfa(4850))
            ->assertSee('FL261001.0001');
    }

    public function test_rejected_withdrawal_returns_the_amount_to_the_balance(): void
    {
        $affiliate = $this->affiliateWithBalance();
        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'));
        $withdrawal = AffiliateWithdrawal::query()->sole();
        $this->assertSame(0, app(AffiliateAccount::class)->balance($affiliate));

        $this->actingAs($this->admin())->post(route('admin.affiliates.withdrawals.reject', $withdrawal), ['reason' => 'Nom du titulaire différent.'])->assertRedirect();

        $this->assertSame(WithdrawalStatus::Rejected, $withdrawal->refresh()->status);
        $this->assertSame(2500, app(AffiliateAccount::class)->balance($affiliate));
        Mail::assertQueued(AffiliateMail::class, fn (AffiliateMail $mail): bool => $mail->kind === 'withdrawal_rejected');

        // Une nouvelle demande est de nouveau possible.
        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'))->assertSessionHasNoErrors();
    }

    public function test_refund_of_a_purchase_in_an_open_withdrawal_reduces_the_requested_amount(): void
    {
        $affiliate = $this->affiliate();
        app(AffiliateManager::class)->updatePayout($affiliate, ['payout_country' => 'tg', 'payout_method' => 'tg-mixx', 'payout_phone' => '+22891000001', 'payout_name' => 'Awa Mensah']);
        $refunded = $this->validatedSale($affiliate);
        $this->validatedSale($affiliate, Formation::factory()->create(['price' => 36000]));
        $this->travel(8)->days();
        $this->artisan('app:release-affiliate-commissions');
        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'));
        $this->assertSame(6100, AffiliateWithdrawal::query()->sole()->amount);

        app(PurchaseManager::class)->refund($refunded, $this->admin());

        $this->assertSame(3600, AffiliateWithdrawal::query()->sole()->amount);
    }

    public function test_suspended_affiliate_cannot_withdraw(): void
    {
        $affiliate = $this->affiliateWithBalance();
        app(AffiliateManager::class)->suspend($affiliate, 'Vérification en cours');

        $this->actingAs($affiliate->user)->post(route('student.affiliate.withdraw'))->assertSessionHasErrors('withdrawal');
        $this->actingAs($affiliate->user)->get(route('student.affiliate.index'))->assertSee('Votre compte affilié est suspendu : Vérification en cours');
    }
}
