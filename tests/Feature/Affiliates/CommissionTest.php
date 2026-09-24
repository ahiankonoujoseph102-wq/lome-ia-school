<?php

namespace Tests\Feature\Affiliates;

use App\Affiliates\AffiliateAccount;
use App\Affiliates\AffiliateManager;
use App\Affiliates\WithdrawalManager;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\EnrollmentSource;
use App\Enums\PurchaseStatus;
use App\Events\FormationAccessGranted;
use App\Mail\AffiliateMail;
use App\Models\AffiliateCommission;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;

class CommissionTest extends AffiliateTestCase
{
    public function test_no_commission_on_provisional_access_commission_created_only_at_validation(): void
    {
        $affiliate = $this->affiliate();
        $purchase = $this->buy(User::factory()->create(), $this->click($affiliate));

        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);
        $this->travel(10)->days();
        $this->artisan('app:release-affiliate-commissions')->assertSuccessful();
        $this->assertSame(0, AffiliateCommission::query()->count());

        $this->validatePayment($purchase);

        $commission = AffiliateCommission::query()->sole();
        $this->assertSame($affiliate->id, $commission->affiliate_id);
        $this->assertSame(CommissionType::Sale, $commission->type);
        $this->assertSame(2500, $commission->amount);
        $this->assertSame(25000, $commission->base_amount);
        $this->assertSame(10, $commission->rate);
        $this->assertSame(CommissionStatus::Pending, $commission->status);
        $this->assertTrue($commission->available_at?->isSameDay(now()->addDays(7)));
        Mail::assertQueued(AffiliateMail::class, fn (AffiliateMail $mail): bool => $mail->hasTo($affiliate->user->email) && $mail->kind === 'commission_created');
    }

    public function test_pending_purchase_above_the_threshold_gets_the_commission_at_validation(): void
    {
        $formation = Formation::factory()->create(['price' => 60000]);
        $affiliate = $this->affiliate();
        $purchase = $this->buy(User::factory()->create(), $this->click($affiliate, $formation), $formation);

        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertSame(0, AffiliateCommission::query()->count());

        $this->validatePayment($purchase);
        $this->assertSame(6000, AffiliateCommission::query()->sole()->amount);
    }

    public function test_rate_is_configurable(): void
    {
        config(['affiliate.rate' => 15]);
        $affiliate = $this->affiliate();

        $this->validatedSale($affiliate);

        $this->assertSame(3750, AffiliateCommission::query()->sole()->amount);
    }

    public function test_rejected_purchase_never_earns_a_commission(): void
    {
        $affiliate = $this->affiliate();
        $purchase = $this->buy(User::factory()->create(), $this->click($affiliate));

        app(PurchaseManager::class)->reject($purchase, $this->admin(), 'Paiement non reçu.');

        $this->assertSame(0, AffiliateCommission::query()->count());
    }

    public function test_session_supplement_earns_a_commission(): void
    {
        $affiliate = $this->affiliate();
        $student = User::factory()->create();
        FormationAccessGranted::dispatch($student->enroll($this->formation, EnrollmentSource::Admin));
        $session = LiveSession::factory()->for($this->formation)->onsite(10, 8000)->create();
        $referral = $this->click($affiliate);

        $this->actingAs($student)->post(route('student.sessions.register', $session));
        $this->withReferral($referral)->actingAs($student)->post(route('student.sessions.pay', $session), $this->proof())->assertRedirect();
        $purchase = Purchase::query()->where('live_session_id', $session->id)->sole();
        $this->assertSame($affiliate->code, $purchase->affiliate_code);

        $this->validatePayment($purchase);

        $this->assertSame(800, AffiliateCommission::query()->sole()->amount);
    }

    public function test_commission_becomes_available_after_seven_days(): void
    {
        $affiliate = $this->affiliate();
        $this->validatedSale($affiliate);
        $account = app(AffiliateAccount::class);

        $this->travel(6)->days();
        $this->artisan('app:release-affiliate-commissions')->assertSuccessful();
        $this->assertSame(CommissionStatus::Pending, AffiliateCommission::query()->sole()->status);
        $this->assertSame(0, $account->balance($affiliate));

        $this->travel(1)->days();
        $this->travel(1)->minutes();
        $this->artisan('app:release-affiliate-commissions')->assertSuccessful();

        $this->assertSame(CommissionStatus::Available, AffiliateCommission::query()->sole()->status);
        $this->assertSame(2500, $account->balance($affiliate));
        Mail::assertQueued(AffiliateMail::class, fn (AffiliateMail $mail): bool => $mail->kind === 'commission_available' && $mail->amount === 2500);
    }

    public function test_refund_before_payout_cancels_the_commission(): void
    {
        $affiliate = $this->affiliate();
        $purchase = $this->validatedSale($affiliate);

        app(PurchaseManager::class)->refund($purchase, $this->admin());

        $commission = AffiliateCommission::query()->sole();
        $this->assertSame(CommissionStatus::Cancelled, $commission->status);
        $this->assertSame('purchase_refunded', $commission->cancel_reason);

        $this->travel(8)->days();
        $this->artisan('app:release-affiliate-commissions');
        $this->assertSame(0, app(AffiliateAccount::class)->balance($affiliate));
    }

    public function test_refund_after_payout_is_deducted_and_the_balance_can_be_negative(): void
    {
        $affiliate = $this->affiliate();
        $affiliateManager = app(AffiliateManager::class);
        $affiliateManager->updatePayout($affiliate, ['payout_country' => 'tg', 'payout_method' => 'tg-mixx', 'payout_phone' => '+22891000001', 'payout_name' => 'Awa Mensah']);
        $purchase = $this->validatedSale($affiliate);

        $this->travel(8)->days();
        $this->artisan('app:release-affiliate-commissions');
        $withdrawal = app(WithdrawalManager::class)->request($affiliate);
        app(WithdrawalManager::class)->markPaid($withdrawal, $this->admin(), 100, 'CI260101.0001');
        $this->assertSame(CommissionStatus::Paid, AffiliateCommission::query()->where('type', CommissionType::Sale)->sole()->status);

        app(PurchaseManager::class)->refund($purchase, $this->admin());

        $reversal = AffiliateCommission::query()->where('type', CommissionType::Reversal)->sole();
        $this->assertSame(-2500, $reversal->amount);
        $this->assertSame(CommissionStatus::Available, $reversal->status);
        $account = app(AffiliateAccount::class);
        $this->assertSame(-2500, $account->balance($affiliate));

        // La prochaine commission compense la déduction.
        $second = Formation::factory()->create(['price' => 36000]);
        $this->validatedSale($affiliate, $second);
        $this->travel(8)->days();
        $this->artisan('app:release-affiliate-commissions');
        $this->assertSame(1100, $account->balance($affiliate));

        // Visible par l'admin.
        $this->actingAs($this->admin())->get(route('admin.affiliates.index'))->assertSee(Money::fcfa(1100));
    }

    public function test_suspended_affiliate_earns_no_new_commission(): void
    {
        $affiliate = $this->affiliate();
        $purchase = $this->buy(User::factory()->create(), $this->click($affiliate));

        app(AffiliateManager::class)->suspend($affiliate, 'Fraude');
        $this->validatePayment($purchase);

        $this->assertSame(0, AffiliateCommission::query()->count());
    }

    public function test_statistics_count_clicks_signups_and_sales(): void
    {
        $affiliate = $this->affiliate();
        $this->validatedSale($affiliate);
        User::factory()->create(['referred_by_affiliate_id' => $affiliate->id]);

        $summary = app(AffiliateAccount::class)->summary($affiliate);

        $this->assertSame(1, $summary['clicks']);
        $this->assertSame(1, $summary['signups']);
        $this->assertSame(1, $summary['sales']);
        $this->assertSame(2500, $summary['pending']);
        $this->assertSame(0, $summary['balance']);
    }
}
