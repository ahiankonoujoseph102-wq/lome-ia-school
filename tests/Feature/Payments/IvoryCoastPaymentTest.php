<?php

namespace Tests\Feature\Payments;

use App\Enums\PurchaseStatus;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Payments\PaymentMethods;
use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Paiement depuis la Côte d'Ivoire (novembre 2026, numéros fournis par Joseph) : pays et trois moyens (Orange Money,
 * MTN Mobile Money, Wave) sur la page de paiement, paiement reçu par un représentant, CGV, et migration qui les ajoute
 * aux réglages déjà enregistrés sans jamais modifier un moyen existant.
 */
class IvoryCoastPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const string MIGRATION = 'database/migrations/2026_11_11_100001_add_ivory_coast_payment_methods.php';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');
        Mail::fake();
    }

    public function test_the_payment_page_offers_the_ivory_coast_and_its_three_operators(): void
    {
        $formation = Formation::factory()->create(['price' => 25000]);

        $this->actingAs(User::factory()->create())->get(route('checkout.show', $formation))->assertOk()
            ->assertSee('data-test="country-ci"', false)
            ->assertSee('Côte d&#039;Ivoire', false)
            ->assertSee('data-test="method-ci-orange"', false)
            ->assertSee('data-test="method-ci-mtn"', false)
            ->assertSee('data-test="method-ci-wave"', false)
            ->assertSee('+225 07 09 51 82 85')
            ->assertSee('+225 05 45 53 11 70')
            ->assertSee('OULOUSSEGUEA CHARLES DANHAN')
            ->assertSee('Paiement reçu par notre représentant en Côte d&#039;Ivoire', false)
            ->assertSee('Ouvrez l\'application Wave pour envoyer le montant à ce numéro.', false);

        // Aucun code USSD fourni : pas de bouton « Ouvrir … » pour la Côte d'Ivoire.
        foreach (['ci-orange', 'ci-mtn', 'ci-wave'] as $key) {
            $this->assertNull(PaymentMethods::findEnabled($key)?->ussdCode(25000));
        }
    }

    public function test_a_student_pays_from_the_ivory_coast_through_the_representative(): void
    {
        $formation = Formation::factory()->create(['price' => 25000]);
        $student = User::factory()->create();

        $this->actingAs($student)->post(route('checkout.pay', $formation), [
            'payment_method' => 'ci-mtn',
            'proof' => UploadedFile::fake()->image('capture.png', 600, 1200),
        ])->assertSessionHasNoErrors();

        $purchase = Purchase::query()->sole();
        $this->assertSame('ci', $purchase->country);
        $this->assertSame('MTN Mobile Money', $purchase->payment_method_name);
        $this->assertSame('+225 05 45 53 11 70', $purchase->payment_number);
        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);
        $this->assertTrue($purchase->viaRepresentative());
    }

    public function test_the_terms_list_the_ivory_coast(): void
    {
        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('(Togo, Burkina Faso, Bénin ou Côte d&#039;Ivoire)', false)
            ->assertSee('Au Burkina Faso, au Bénin et en Côte d&#039;Ivoire, le paiement est reçu par un représentant', false);
    }

    public function test_the_migration_adds_the_ivory_coast_to_saved_settings_without_touching_the_rest(): void
    {
        $countries = config('site.payments.countries');
        unset($countries['ci']);
        $methods = collect(config('site.payments.methods'))->reject(fn (array $method): bool => $method['country'] === 'ci')->all();
        $methods['tg-mixx']['number'] = '+228 90 00 00 00';
        $faq = config('site.faq');
        $faq['home'] = [['question' => 'Comment payer ?', 'answer' => 'Sur nos numéros (Togo, Burkina Faso ou Bénin).'], ['question' => 'Autre', 'answer' => 'Texte libre de Joseph.']];
        SiteSettings::save(['site.payments.countries' => $countries, 'site.payments.methods' => $methods, 'site.faq' => $faq]);

        (require base_path(self::MIGRATION))->up();
        SiteSettings::apply();

        $this->assertSame('Côte d\'Ivoire', config('site.payments.countries.ci.name'));
        $this->assertSame('+225 07 09 51 82 85', config('site.payments.methods.ci-wave.number'));
        $this->assertSame('OULOUSSEGUEA CHARLES DANHAN', config('site.payments.methods.ci-orange.beneficiary'));
        // Un moyen déjà enregistré n'est jamais modifié ; seul le texte exact est complété.
        $this->assertSame('+228 90 00 00 00', config('site.payments.methods.tg-mixx.number'));
        $this->assertSame('Sur nos numéros (Togo, Burkina Faso, Bénin ou Côte d\'Ivoire).', config('site.faq.home.0.answer'));
        $this->assertSame('Texte libre de Joseph.', config('site.faq.home.1.answer'));
        $this->assertNotNull(DB::table('site_settings')->where('key', 'system.ivory_coast_previous')->value('value'));

        // Relancée, ou un moyen de Côte d'Ivoire déjà modifié par Joseph : rien ne change.
        $saved = (array) SiteSettings::get('site.payments.methods');
        $saved['ci-wave']['number'] = '+225 01 02 03 04 05';
        SiteSettings::save(['site.payments.methods' => $saved]);
        (require base_path(self::MIGRATION))->up();
        SiteSettings::apply();
        $this->assertSame('+225 01 02 03 04 05', config('site.payments.methods.ci-wave.number'));
        $this->assertSame('Sur nos numéros (Togo, Burkina Faso, Bénin ou Côte d\'Ivoire).', config('site.faq.home.0.answer'));
    }
}
