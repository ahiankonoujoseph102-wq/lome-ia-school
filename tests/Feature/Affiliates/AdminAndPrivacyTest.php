<?php

namespace Tests\Feature\Affiliates;

use App\Affiliates\AffiliateAlerts;
use App\Affiliates\AffiliateManager;
use App\Affiliates\WithdrawalManager;
use App\Enums\UserRole;
use App\Mail\AffiliateMail;
use App\Models\Affiliate;
use App\Models\AffiliateWithdrawal;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class AdminAndPrivacyTest extends AffiliateTestCase
{
    public function test_any_registered_user_can_activate_after_accepting_the_terms(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('student.affiliate.index'))->assertOk()->assertSee('Activer mon compte affilié')->assertSee('Aucune commission sur vos propres achats.');
        $this->actingAs($user)->post(route('student.affiliate.activate'))->assertSessionHasErrors('terms');
        $this->assertNull($user->refresh()->affiliate);

        $this->actingAs($user)->post(route('student.affiliate.activate'), ['terms' => '1'])->assertRedirect(route('student.affiliate.index'));

        $affiliate = $user->refresh()->affiliate;
        $this->assertNotNull($affiliate);
        $this->assertNotNull($affiliate->terms_accepted_at);
        $this->actingAs($user)->get(route('student.affiliate.index'))
            ->assertOk()
            ->assertSee($affiliate->link(), false)
            ->assertSee($affiliate->link($this->formation), false)
            ->assertSee('https://wa.me/?text=', false)
            ->assertSee('facebook.com/sharer', false);
    }

    public function test_buyer_details_are_never_shown_to_the_affiliate(): void
    {
        $affiliate = $this->affiliate();
        $buyer = User::factory()->create(['name' => 'Acheteur Secret', 'email' => 'secret@example.com', 'phone' => '+22893334455']);
        $this->validatePayment($this->buy($buyer, $this->click($affiliate), payerPhone: '93 33 44 55'));

        foreach (['student.affiliate.index', 'student.affiliate.commissions', 'student.affiliate.withdrawals'] as $route) {
            $response = $this->actingAs($affiliate->user)->get(route($route))->assertOk();
            $this->assertPageDoesNotShow($response, $buyer);
            $response->assertDontSee('Afi Dossou');
        }

        $this->actingAs($affiliate->user)->get(route('student.affiliate.commissions'))->assertSee('Automatisation avec l&#039;IA', false);

        Mail::assertQueued(AffiliateMail::class, function (AffiliateMail $mail) use ($buyer): bool {
            $html = $mail->render();

            return ! str_contains($html, $buyer->name) && ! str_contains($html, $buyer->email) && ! str_contains($html, 'Afi Dossou') && str_contains($html, 'Automatisation');
        });
    }

    public function test_admin_pages_are_forbidden_to_students_and_moderators(): void
    {
        $affiliate = $this->affiliate();
        app(AffiliateManager::class)->updatePayout($affiliate, ['payout_country' => 'tg', 'payout_method' => 'tg-mixx', 'payout_phone' => '+22891000001', 'payout_name' => 'Awa Mensah']);
        $this->validatedSale($affiliate);
        $this->travel(8)->days();
        $this->artisan('app:release-affiliate-commissions');
        $withdrawal = app(WithdrawalManager::class)->request($affiliate);

        foreach ([User::factory()->create(), User::factory()->create(['role' => UserRole::Moderator]), $affiliate->user] as $user) {
            $this->actingAs($user)->get(route('admin.affiliates.withdrawals'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.affiliates.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.affiliates.withdrawals.pay', $withdrawal), ['fee' => 0, 'transaction_id' => 'X123'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.affiliates.withdrawals.reject', $withdrawal), ['reason' => 'Non'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.affiliates.suspend', $affiliate))->assertForbidden();
        }

        $this->assertTrue(AffiliateWithdrawal::query()->sole()->isOpen());
        $this->assertFalse($affiliate->refresh()->isSuspended());
        $this->app['auth']->forgetGuards();
        $this->get(route('admin.affiliates.withdrawals'))->assertRedirect(route('login'));

        $this->actingAs($this->admin())->get(route('admin.affiliates.withdrawals'))->assertOk()->assertSee('Retraits à payer')->assertSee($affiliate->user->name);
    }

    public function test_admin_can_suspend_and_reactivate_an_affiliate(): void
    {
        $affiliate = $this->affiliate(['name' => 'Kodjo Affilié']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.affiliates.index'))->assertOk()->assertSee('Kodjo Affilié')->assertSee($affiliate->code);
        $this->actingAs($admin)->post(route('admin.affiliates.suspend', $affiliate), ['reason' => 'Spam'])->assertRedirect();
        $this->assertTrue($affiliate->refresh()->isSuspended());
        $this->actingAs($admin)->get(route('admin.affiliates.index'))->assertSee('Suspendu');

        $this->actingAs($admin)->post(route('admin.affiliates.unsuspend', $affiliate))->assertRedirect();
        $this->assertFalse($affiliate->refresh()->isSuspended());
    }

    public function test_payments_page_shows_the_affiliate_and_warns_when_the_affiliate_paid_himself(): void
    {
        $affiliate = $this->affiliate(['name' => 'Yao Affilié', 'phone' => '+22890111111']);
        // Un autre compte, mais payé depuis le numéro WhatsApp de l'affilié.
        $this->buy(User::factory()->create(), $this->click($affiliate), payerPhone: '90 11 11 11');

        $this->actingAs($this->admin())->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('Achat venu du lien de l\'affilié', false)
            ->assertSee('Yao Affilié')
            ->assertSee('possible auto-parrainage');
    }

    public function test_alerts_for_shared_payout_numbers_and_signups_without_purchase(): void
    {
        $first = $this->affiliate();
        $second = $this->affiliate();
        $details = ['payout_country' => 'tg', 'payout_method' => 'tg-mixx', 'payout_phone' => '+22891000001', 'payout_name' => 'Awa Mensah'];
        app(AffiliateManager::class)->updatePayout($first, $details);
        app(AffiliateManager::class)->updatePayout($second, $details);
        User::factory()->count(10)->create(['referred_by_affiliate_id' => $first->id]);

        $alerts = collect(app(AffiliateAlerts::class)->forAffiliate($first))->pluck('text')->implode(' | ');

        $this->assertStringContainsString('est aussi celui de 1 autre affilié', $alerts);
        $this->assertStringContainsString('10 inscriptions par son lien sans aucun achat', $alerts);

        $this->actingAs($this->admin())->get(route('admin.affiliates.index'))->assertSee('10 inscriptions par son lien sans aucun achat');
    }

    public function test_alert_when_the_payout_number_paid_a_sale(): void
    {
        $affiliate = $this->affiliate();
        app(AffiliateManager::class)->updatePayout($affiliate, ['payout_country' => 'tg', 'payout_method' => 'tg-mixx', 'payout_phone' => '+22891000001', 'payout_name' => 'Awa Mensah']);
        $this->validatePayment($this->buy(User::factory()->create(), $this->click($affiliate), payerPhone: '91 00 00 01'));

        $alerts = collect(app(AffiliateAlerts::class)->forAffiliate($affiliate->refresh()))->pluck('text')->implode(' | ');

        $this->assertStringContainsString('1 vente payée depuis le numéro WhatsApp ou le numéro de retrait de l\'affilié', $alerts);
    }

    public function test_public_page_footer_link_and_legal_pages(): void
    {
        $this->get(route('affiliate.program'))
            ->assertOk()
            ->assertSee('Devenez affilié de Lomé IA School')
            ->assertSee('10 %')
            ->assertSee('Mobile Money')
            ->assertSee('7 jours')
            ->assertSee('aucun montant n\'est garanti', false)
            ->assertDontSee('Gagnez');

        $this->get(route('home'))->assertSee(route('affiliate.program'), false);

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('9. Programme d\'affiliation', false)
            ->assertSee('le dernier lien cliqué qui compte', false)
            ->assertSee('aucune commission n\'est versée sur les propres achats', false)
            ->assertSee('jamais pendant un accès provisoire', false)
            ->assertSee('le solde de l\'affilié peut alors devenir négatif', false)
            ->assertSee('Les frais d\'envoi Mobile Money sont déduits du montant retiré', false);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('vos coordonnées de retrait', false)
            ->assertSee('lis_ref')
            ->assertSee('jamais votre adresse IP en clair', false);
    }

    public function test_moderators_do_not_see_the_admin_affiliate_links(): void
    {
        $moderator = User::factory()->create(['role' => UserRole::Moderator]);

        $this->actingAs($moderator)->get(route('dashboard'))->assertOk()->assertDontSee(route('admin.affiliates.withdrawals'), false);
        $this->actingAs($this->admin())->get(route('admin.index'))->assertSee(route('admin.affiliates.withdrawals'), false)->assertSee(route('admin.affiliates.index'), false);
        $this->assertSame(0, Affiliate::query()->count());
    }
}
