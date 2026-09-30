<?php

namespace Tests\Feature\Payments;

use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\PromoCode;
use App\Models\Purchase;
use App\Models\User;
use App\Payments\PaymentMethods;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Page de paiement simplifiée : 4 étapes, capture seule, bouton « Payer maintenant » par code USSD.
 */
class SimplePaymentPageTest extends TestCase
{
    use RefreshDatabase;

    private const string ANDROID = 'Mozilla/5.0 (Linux; Android 14; TECNO KL8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';

    private const string IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');
        Mail::fake();
    }

    private function page(Formation $formation, string $userAgent = self::ANDROID, ?string $code = null, ?User $student = null): TestResponse
    {
        return $this->actingAs($student ?? User::factory()->create())
            ->withHeader('User-Agent', $userAgent)
            ->get(route('checkout.show', array_filter([$formation, 'code' => $code])))
            ->assertOk();
    }

    /**
     * Ce que l'élève envoie : le moyen choisi et la capture, rien d'autre.
     *
     * @return array<string, mixed>
     */
    private function capture(string $method = 'tg-mixx', array $extra = []): array
    {
        return ['payment_method' => $method, 'proof' => UploadedFile::fake()->image('capture.png', 600, 1200), ...$extra];
    }

    public function test_page_shows_four_steps_and_no_reference_field(): void
    {
        $formation = Formation::factory()->create(['price' => 25000]);

        $this->page($formation)
            ->assertSee('Payer par Mobile Money')
            ->assertSee('Dans quel pays payez-vous ?')
            ->assertSee('Choisissez votre opérateur')
            ->assertSee('data-test="country-tg"', false)
            ->assertSee('data-test="country-bf"', false)
            ->assertSee('data-test="country-bj"', false)
            ->assertSee('data-test="method-tg-mixx"', false)
            ->assertSee('data-test="method-bf-wave"', false)
            ->assertSee("25\u{00A0}000\u{00A0}FCFA")
            ->assertSee('Copier le numéro')
            ->assertSee('Copier le montant')
            ->assertSee('Ahiankonou Kokou Joseph')
            ->assertSee('Le paiement se fait par transfert Mobile Money depuis votre téléphone. Les frais de transfert sont à votre charge.')
            ->assertSee('Envoyer la capture de mon paiement')
            ->assertDontSee('name="transaction_id"', false)
            ->assertDontSee('name="payer_phone"', false)
            ->assertDontSee('name="payer_name"', false)
            ->assertDontSee('Identifiant de la transaction');
    }

    public function test_full_journey_below_the_threshold_opens_the_formation_at_once(): void
    {
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 25000]);

        $this->page($formation, student: $student);
        $response = $this->actingAs($student)->post(route('checkout.pay', $formation), $this->capture());

        $purchase = Purchase::firstOrFail();
        $response->assertRedirect(route('student.purchases.show', $purchase));
        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);
        $this->assertSame(25000, $purchase->amount);
        $this->assertTrue($student->isEnrolledIn($formation));
        Storage::disk('payment_proofs')->assertExists((string) $purchase->proof_path);

        $this->actingAs($student)->get(route('student.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Paiement reçu, formation ouverte')
            ->assertSee('Commencer la formation')
            ->assertSee(route('student.formations.show', $formation), false);
    }

    public function test_full_journey_above_the_threshold_waits_for_validation(): void
    {
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 75000]);

        $this->page($formation, student: $student)->assertSee('Votre accès sera ouvert dès que votre paiement aura été vérifié et validé');
        $this->actingAs($student)->post(route('checkout.pay', $formation), $this->capture('bf-wave'))->assertSessionHasNoErrors();

        $purchase = Purchase::firstOrFail();
        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertSame('bf-wave', $purchase->payment_method);
        $this->assertFalse($student->isEnrolledIn($formation));

        $this->actingAs($student)->get(route('student.purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Paiement reçu, en attente de validation')
            ->assertSee('Votre accès à la formation sera ouvert dès que votre paiement aura été vérifié et validé')
            ->assertDontSee('Commencer la formation');
    }

    public function test_full_journey_with_a_promo_code_below_the_threshold(): void
    {
        PromoCode::query()->create(['code' => 'RENTREE', 'type' => 'percent', 'value' => 50]);
        $student = User::factory()->create();
        // 60 000 FCFA - 50 % = 30 000 FCFA : sous le seuil, accès immédiat.
        $formation = Formation::factory()->create(['price' => 60000]);

        $this->page($formation, code: 'RENTREE', student: $student)
            ->assertSee('data-test="amount-to-send"', false)
            ->assertSee("30\u{00A0}000\u{00A0}FCFA")
            ->assertSee('href="tel:*145*1%23"', false)
            ->assertSee('name="promo_code" value="RENTREE"', false);

        $this->actingAs($student)->post(route('checkout.pay', $formation), $this->capture(extra: ['promo_code' => 'RENTREE']))->assertSessionHasNoErrors();

        $purchase = Purchase::firstOrFail();
        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);
        $this->assertSame(30000, $purchase->amount);
        $this->assertSame(30000, $purchase->discount_amount);
        $this->assertTrue($student->isEnrolledIn($formation));
    }

    public function test_full_journey_with_a_promo_code_above_the_threshold(): void
    {
        PromoCode::query()->create(['code' => 'MOINS10', 'type' => 'percent', 'value' => 10]);
        $student = User::factory()->create();
        // 80 000 FCFA - 10 % = 72 000 FCFA : au-dessus du seuil, accès après validation.
        $formation = Formation::factory()->create(['price' => 80000]);

        $this->page($formation, code: 'MOINS10', student: $student)->assertSee("72\u{00A0}000\u{00A0}FCFA");
        $this->actingAs($student)->post(route('checkout.pay', $formation), $this->capture(extra: ['promo_code' => 'MOINS10']))->assertSessionHasNoErrors();

        $purchase = Purchase::firstOrFail();
        $this->assertSame(PurchaseStatus::Pending, $purchase->status);
        $this->assertSame(72000, $purchase->amount);
        $this->assertFalse($student->isEnrolledIn($formation));
    }

    public function test_promo_code_is_checked_again_by_the_server(): void
    {
        $student = User::factory()->create();
        $formation = Formation::factory()->create(['price' => 60000]);

        $this->actingAs($student)
            ->post(route('checkout.pay', $formation), $this->capture(extra: ['promo_code' => 'INVENTE']))
            ->assertSessionHasErrors('promo_code');

        $this->assertSame(0, Purchase::count());
    }

    public function test_ussd_button_only_when_a_code_is_set_with_hash_encoded(): void
    {
        $formation = Formation::factory()->create(['price' => 5500]);

        $response = $this->page($formation)
            ->assertSee('data-test="ussd-button"', false)
            ->assertSee('href="tel:*145*1%23"', false)
            ->assertSee('href="tel:*155*1%23"', false)
            ->assertSee('href="tel:*144*2%23"', false)
            ->assertSee('href="tel:*880%23"', false)
            ->assertSee('href="tel:*855*1%23"', false)
            ->assertSee('href="tel:*889%23"', false)
            ->assertDontSee('href="tel:*145*1#"', false)
            ->assertSee('Ouvrir Mixx by Yas')
            ->assertSee('Ouvrir Flooz / Moov Money')
            ->assertSee('Le menu de votre opérateur va s\'ouvrir : choisissez le transfert, saisissez ce numéro et ce montant, puis validez avec votre code secret.', false)
            ->assertSee('Téléphone double SIM : utilisez la carte SIM de cet opérateur.')
            // Wave : pas de code USSD, paiement par l'application.
            ->assertDontSee('Ouvrir Wave')
            ->assertSee('Ouvrez l\'application Wave pour envoyer le montant à ce numéro.', false);

        // Six moyens avec un code USSD, Wave sans.
        $this->assertSame(6, substr_count($response->getContent(), 'data-test="ussd-button"'));

        // Case vidée dans l'admin : plus de bouton pour ce moyen.
        config(['site.payments.methods.tg-mixx.ussd' => '']);

        $this->page($formation)
            ->assertDontSee('tel:*145*1', false)
            ->assertDontSee('Ouvrir Mixx by Yas')
            ->assertSee('Ouvrir Flooz / Moov Money');
    }

    public function test_ussd_code_placeholders_are_replaced_by_the_local_number_and_the_amount(): void
    {
        config(['site.payments.methods.tg-mixx.ussd' => '*145*1*{numero}*{montant}#']);
        $formation = Formation::factory()->create(['price' => 5500]);

        $this->page($formation)
            ->assertSee('href="tel:*145*1*72240231*5500%23"', false)
            ->assertSee('*145*1*72240231*5500#');

        $this->assertSame('0151533935', PaymentMethods::find('bj-mtn')?->localNumber());
        $this->assertSame('tel:*880%23', PaymentMethods::find('bj-mtn')?->ussdTelUrl(5500));
        $this->assertNull(PaymentMethods::find('bf-wave')?->ussdTelUrl(5500));
    }

    public function test_no_ussd_button_on_iphone_but_the_code_to_type(): void
    {
        $formation = Formation::factory()->create(['price' => 5500]);

        $this->page($formation, self::IPHONE)
            ->assertDontSee('data-test="ussd-button"', false)
            ->assertDontSee('tel:*145*1', false)
            ->assertSee('Tapez ce code sur le clavier de votre téléphone')
            ->assertSee('*145*1#')
            ->assertSee('Copier');
    }

    public function test_help_button_opens_whatsapp_with_the_formation_and_the_amount(): void
    {
        $formation = Formation::factory()->create(['title' => 'ChatGPT au quotidien', 'price' => 15000]);

        $message = 'Bonjour Lomé IA School, j\'ai besoin d\'aide pour payer la formation « ChatGPT au quotidien » (montant : '."15\u{00A0}000\u{00A0}FCFA".').';

        $this->page($formation)
            ->assertSee('Besoin d\'aide pour payer ?', false)
            ->assertSee('https://wa.me/'.config('site.whatsapp.number').'?text='.rawurlencode($message), false);
    }

    public function test_admin_sets_checks_and_clears_the_ussd_code(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $methods = [];

        foreach ((array) config('site.payments.methods') as $key => $method) {
            $methods[] = ['key' => $key, ...$method, 'enabled' => $method['enabled'] ? '1' : null];
        }

        $countries = [];

        foreach ((array) config('site.payments.countries') as $code => $country) {
            $countries[] = ['code' => $code, ...$country];
        }

        $form = ['admin_email' => 'academyjoseph71@gmail.com', 'countries' => $countries, 'methods' => $methods, 'current_password' => 'password'];

        $this->actingAs($admin)->get(route('admin.settings.edit', 'paiements'))->assertOk()->assertSee('Code USSD de transfert (facultatif)');

        // Code invalide (texte, code secret en toutes lettres…) : refusé.
        $form['methods'][0]['ussd'] = '*145*1*1234 mon code#';
        $this->actingAs($admin)->put(route('admin.settings.update', 'paiements'), $form)->assertSessionHasErrors('methods.0.ussd');

        // Code collé avec des espaces et un repère : nettoyé et enregistré.
        $form['methods'][0]['ussd'] = " *145*1*{numero}# \u{00A0}";
        $form['methods'][1]['ussd'] = '';
        $this->actingAs($admin)->put(route('admin.settings.update', 'paiements'), $form)->assertSessionHasNoErrors();

        $this->assertSame('*145*1*{numero}#', config('site.payments.methods.tg-mixx.ussd'));
        $this->assertSame('', config('site.payments.methods.tg-flooz.ussd'));
        $this->assertDatabaseHas('admin_activities', ['description' => 'Moyens de paiement modifiés : Mixx by Yas, code USSD : *145*1# → *145*1*{numero}# ; Flooz / Moov Money, code USSD : *155*1# → .']);

        $formation = Formation::factory()->create(['price' => 5500]);
        $this->page($formation)
            ->assertSee('href="tel:*145*1*72240231%23"', false)
            ->assertDontSee('Ouvrir Flooz / Moov Money');
    }

    public function test_migration_adds_the_ussd_codes_to_methods_saved_from_the_admin(): void
    {
        // Moyens enregistrés depuis l'admin avant l'ajout des codes USSD (aucune clé « ussd »).
        $saved = collect((array) config('site.payments.methods'))->map(fn (array $method): array => collect($method)->except('ussd')->all())->all();
        $saved['tg-mixx']['number'] = '+228 90 00 00 00';
        $saved['tg-flooz']['ussd'] = '*999#';
        SiteSettings::save(['site.payments.methods' => $saved]);

        (require database_path('migrations/2026_10_05_100000_add_ussd_codes_to_payment_methods.php'))->up();
        SiteSettings::flush();

        $this->assertSame('*145*1#', config('site.payments.methods.tg-mixx.ussd'));
        $this->assertSame('+228 90 00 00 00', config('site.payments.methods.tg-mixx.number'));
        // Un code déjà réglé n'est jamais écrasé.
        $this->assertSame('*999#', config('site.payments.methods.tg-flooz.ussd'));
        $this->assertSame('', config('site.payments.methods.bf-wave.ussd'));
        $this->assertSame('*889#', config('site.payments.methods.bj-celtiis.ussd'));
        $this->assertSame(1, DB::table('site_settings')->where('key', 'site.payments.methods')->count());
    }
}
