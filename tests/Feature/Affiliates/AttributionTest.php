<?php

namespace Tests\Feature\Affiliates;

use App\Affiliates\AffiliateManager;
use App\Models\AffiliateClick;
use App\Models\User;

class AttributionTest extends AffiliateTestCase
{
    public function test_link_remembers_the_click_and_redirects_without_the_code(): void
    {
        $affiliate = $this->affiliate();

        $referral = $this->click($affiliate, $this->formation);

        $this->assertStringStartsWith($affiliate->code.'|', (string) $referral);
        $this->assertSame(1, AffiliateClick::query()->where('affiliate_id', $affiliate->id)->where('formation_id', $this->formation->id)->count());
    }

    public function test_repeated_clicks_from_the_same_person_are_counted_once(): void
    {
        $affiliate = $this->affiliate();

        // Même navigateur (cookie visiteur conservé).
        $first = $this->get($affiliate->link());
        $visitor = $first->getCookie((string) config('affiliate.visitor_cookie'))?->getValue();
        $this->withCookie((string) config('affiliate.visitor_cookie'), (string) $visitor)->get($affiliate->link());
        $this->withCookie((string) config('affiliate.visitor_cookie'), (string) $visitor)->get($affiliate->link($this->formation));

        // Cookies effacés, même connexion et même appareil.
        $this->defaultCookies = [];
        $this->get($affiliate->link());

        $this->assertSame(1, $affiliate->clicks()->count());

        // Une autre personne (autre connexion) est comptée.
        $this->defaultCookies = [];
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->get($affiliate->link());
        $this->assertSame(2, $affiliate->clicks()->count());
    }

    public function test_link_preview_robots_are_not_counted(): void
    {
        $affiliate = $this->affiliate();

        $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get($affiliate->link())->assertRedirect(route('home'));

        $this->assertSame(0, $affiliate->clicks()->count());
    }

    public function test_purchase_within_thirty_days_is_attributed_and_the_code_is_stored_on_the_purchase(): void
    {
        $affiliate = $this->affiliate();
        $referral = $this->click($affiliate);

        $this->travel(29)->days();
        $purchase = $this->buy(User::factory()->create(), $referral);

        $this->assertSame($affiliate->code, $purchase->affiliate_code);
    }

    public function test_click_older_than_thirty_days_is_ignored(): void
    {
        $affiliate = $this->affiliate();
        $referral = $this->click($affiliate);

        $this->travel(31)->days();
        $purchase = $this->buy(User::factory()->create(), $referral);

        $this->assertNull($purchase->affiliate_code);
    }

    public function test_the_duration_is_configurable(): void
    {
        config(['affiliate.cookie_days' => 7]);
        $affiliate = $this->affiliate();
        $referral = $this->click($affiliate);

        $this->travel(8)->days();

        $this->assertNull($this->buy(User::factory()->create(), $referral)->affiliate_code);
    }

    public function test_last_clicked_link_wins(): void
    {
        $first = $this->affiliate();
        $second = $this->affiliate();

        $this->click($first);
        $this->travel(2)->days();
        $latest = $this->click($second);

        $purchase = $this->buy(User::factory()->create(), $latest);

        $this->assertSame($second->code, $purchase->affiliate_code);
    }

    public function test_affiliate_never_gets_their_own_purchase(): void
    {
        $affiliate = $this->affiliate();

        // Son propre lien, connecté : ni compté ni mémorisé.
        $this->actingAs($affiliate->user)->get($affiliate->link())->assertRedirect(route('home'));
        $this->assertSame(0, $affiliate->clicks()->count());

        // Lien cliqué sans être connecté, puis achat avec son compte : aucun code enregistré.
        $this->app['auth']->forgetGuards();
        $referral = $this->click($affiliate);
        $purchase = $this->buy($affiliate->user, $referral);

        $this->assertNull($purchase->affiliate_code);
        $this->validatePayment($purchase);
        $this->assertSame(0, $affiliate->commissions()->count());
    }

    public function test_signup_through_a_link_counts_as_a_registration(): void
    {
        $affiliate = $this->affiliate();
        $referral = $this->click($affiliate);

        $this->withReferral($referral)->post(route('register.store'), [
            'name' => 'Kossi Amegan',
            'email' => 'kossi@example.com',
            'phone_country' => '+228',
            'phone' => '91 23 45 67',
            'password' => 'motdepasse1',
        ])->assertRedirect();

        $this->assertSame($affiliate->id, User::query()->where('email', 'kossi@example.com')->value('referred_by_affiliate_id'));
        $this->assertSame(1, $affiliate->referrals()->count());
    }

    public function test_suspended_affiliate_links_are_inactive(): void
    {
        $affiliate = $this->affiliate();
        app(AffiliateManager::class)->suspend($affiliate, 'Spam');

        $referral = $this->click($affiliate);

        $this->assertNull($referral);
        $this->assertSame(0, $affiliate->clicks()->count());
    }

    public function test_unknown_code_is_ignored(): void
    {
        $this->get(route('home').'?ref=INCONNU')->assertRedirect(route('home'));

        $this->assertSame(0, AffiliateClick::query()->count());
    }
}
