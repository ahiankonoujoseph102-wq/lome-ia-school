<?php

namespace Tests\Feature\Affiliates;

use App\Affiliates\AffiliateManager;
use App\Enums\UserRole;
use App\Models\Affiliate;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class AffiliateTestCase extends TestCase
{
    use RefreshDatabase;

    protected Formation $formation;

    private int $proofs = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('payment_proofs');
        Storage::fake('chat_media');
        $this->formation = Formation::factory()->create(['slug' => 'automatisation-ia', 'title' => 'Automatisation avec l\'IA', 'price' => 25000]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function affiliate(array $attributes = []): Affiliate
    {
        return app(AffiliateManager::class)->activate(User::factory()->create($attributes));
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    /**
     * Clic sur un lien d'affiliation : renvoie la valeur du cookie « dernier lien cliqué ».
     */
    protected function click(Affiliate $affiliate, ?Formation $formation = null): ?string
    {
        // Clic d'un visiteur non connecté.
        $this->app['auth']->forgetGuards();
        $response = $this->get($affiliate->link($formation));
        $response->assertRedirect($formation ? route('formations.show', $formation) : route('home'));

        return $response->getCookie((string) config('affiliate.cookie'))?->getValue();
    }

    /**
     * Preuve de paiement Mobile Money : la capture seule (différente à chaque appel).
     *
     * @return array<string, mixed>
     */
    protected function proof(): array
    {
        $this->proofs++;

        return [
            'payment_method' => 'tg-mixx',
            'proof' => UploadedFile::fake()->image('capture.png', 600, 1000 + $this->proofs),
        ];
    }

    /**
     * Achat d'une formation, avec le cookie du dernier lien cliqué s'il y en a un.
     * $payerPhone : numéro payeur d'un ancien paiement (saisi par l'élève avant la page de paiement simplifiée).
     */
    protected function buy(User $buyer, ?string $referral, ?Formation $formation = null, ?string $payerPhone = null): Purchase
    {
        $this->withReferral($referral)
            ->actingAs($buyer)
            ->post(route('checkout.pay', $formation ?? $this->formation), $this->proof())
            ->assertRedirect();

        $purchase = Purchase::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();

        if ($payerPhone !== null) {
            $purchase->forceFill(['payer_phone' => $payerPhone])->save();
        }

        return $purchase;
    }

    protected function withReferral(?string $referral): static
    {
        $this->defaultCookies = [];

        return $referral === null ? $this : $this->withCookie((string) config('affiliate.cookie'), $referral);
    }

    protected function validatePayment(Purchase $purchase): Purchase
    {
        return app(PurchaseManager::class)->validate($purchase, $this->admin());
    }

    /**
     * Achat validé passé par le lien de l'affilié.
     */
    protected function validatedSale(Affiliate $affiliate, ?Formation $formation = null): Purchase
    {
        $referral = $this->click($affiliate, $formation);

        return $this->validatePayment($this->buy(User::factory()->create(), $referral, $formation));
    }

    protected function assertPageDoesNotShow(TestResponse $response, User $buyer): void
    {
        $response->assertDontSee($buyer->name)->assertDontSee($buyer->email)->assertDontSee((string) $buyer->phone)->assertDontSee($buyer->formatted_phone);
    }
}
