<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminAction;
use App\Mail\SecurityAlertMail;
use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SiteSettingsTest extends AdminTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function paymentsForm(array $overrides = []): array
    {
        $methods = [];

        foreach ((array) config('site.payments.methods') as $key => $method) {
            $methods[] = ['key' => $key, ...$method, 'enabled' => $method['enabled'] ? '1' : null];
        }

        $countries = [];

        foreach ((array) config('site.payments.countries') as $code => $country) {
            $countries[] = ['code' => $code, ...$country];
        }

        return array_replace([
            'admin_email' => 'academyjoseph71@gmail.com',
            'countries' => $countries,
            'methods' => $methods,
            'current_password' => 'password',
        ], $overrides);
    }

    public function test_every_settings_page_opens(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()->assertSee('Moyens de paiement');

        foreach (['coordonnees', 'image', 'paiements', 'regles', 'affiliation', 'textes'] as $section) {
            $this->actingAs($this->admin)->get(route('admin.settings.edit', $section))->assertOk();
        }

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'inconnu'))->assertNotFound();
    }

    public function test_contact_changes_are_visible_on_the_site_immediately(): void
    {
        $this->actingAs($this->admin)->get(route('home'))->assertOk();

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'coordonnees'), [
            'phones' => ['+228 90 00 00 01', ''],
            'email' => 'contact@exemple.test',
            'address' => 'Adidogomé, Lomé',
            'map_url' => '',
            'hours' => 'Du lundi au vendredi, de 9 h à 18 h',
            'whatsapp_number' => '+228 90 00 00 01',
            'whatsapp_message' => 'Bonjour',
            'consultation_message' => 'Bonjour, consultation',
            'social' => ['facebook' => 'https://www.facebook.com/lomeiaschool', 'tiktok' => '', 'youtube' => ''],
            'trained' => '1 200+',
            'trainer_since' => '2023',
        ])->assertSessionHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('1 200+')
            ->assertSee('année où Joseph a commencé à former')
            ->assertDontSee('année de création')
            ->assertSee('https://www.facebook.com/lomeiaschool')
            ->assertSee('wa.me/22890000001', false);
        $this->get(route('contact'))->assertOk()->assertSee('Du lundi au vendredi, de 9 h à 18 h')->assertSee('contact@exemple.test');

        $this->assertSame('22890000001', config('site.whatsapp.number'));
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::SettingsUpdated->value]);
        Mail::assertNotQueued(SecurityAlertMail::class);
    }

    public function test_threshold_change_updates_texts_and_the_access_rule(): void
    {
        $formation = Formation::factory()->create(['price' => 40000]);
        $this->get(route('formations.show', $formation))->assertOk()->assertSee('Accès immédiat');

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'regles'), [
            'verification_threshold' => 30000,
            'verification_hours' => 'du lundi au samedi, de 8 h à 20 h',
            'fees_notice' => 'Les frais de transfert sont à la charge de l\'expéditeur.',
            'default_location' => 'Adidogomé, Lycée moderne 2, Lomé',
            'default_duration' => 120,
            'cancel_deadline_hours' => 24,
            'join_window_minutes' => 15,
            'payment_hold_hours' => 24,
        ])->assertSessionHasNoErrors();

        $this->assertSame(30000, config('site.payments.verification_threshold'));
        $this->assertTrue($formation->needsPaymentVerification());
        $this->get(route('home'))->assertOk()->assertSee("30\u{00A0}000\u{00A0}FCFA")->assertDontSee('{seuil}');
        $this->get(route('formations.show', $formation))->assertOk()->assertSee('Accès après vérification');
    }

    public function test_payment_methods_need_the_password_and_send_an_alert(): void
    {
        $form = $this->paymentsForm();
        $form['methods'][0]['number'] = '+228 90 11 22 33';

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'paiements'), [...$form, 'current_password' => ''])->assertSessionHasErrors('current_password');
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'paiements'), [...$form, 'current_password' => 'mauvais'])->assertSessionHasErrors('current_password');
        $this->assertSame('+228 72 24 02 31', config('site.payments.methods.tg-mixx.number'));
        Mail::assertNothingQueued();

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'paiements'), $form)->assertSessionHasNoErrors();

        $this->assertSame('+228 90 11 22 33', config('site.payments.methods.tg-mixx.number'));
        Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $mail): bool => $mail->hasTo('academyjoseph71@gmail.com')
            && str_contains($mail->activity->description, '+228 72 24 02 31 → +228 90 11 22 33'));
        $this->assertDatabaseHas('admin_activities', ['action' => AdminAction::PaymentMethodsUpdated->value, 'user_id' => $this->admin->id]);
    }

    public function test_add_disable_and_keep_payment_methods(): void
    {
        $form = $this->paymentsForm();
        $form['methods'][] = ['key' => '', 'country' => 'tg', 'name' => 'Nouveau Money', 'number' => '+228 91 00 00 00', 'beneficiary' => 'Exemple Bénéficiaire', 'color' => '#22c55e', 'enabled' => '1'];
        $form['methods'][1]['enabled'] = null;
        // bj-celtiis retiré du formulaire : il reste enregistré, désactivé.
        $form['methods'] = array_values(array_filter($form['methods'], fn (array $method): bool => $method['key'] !== 'bj-celtiis'));

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'paiements'), $form)->assertSessionHasNoErrors();

        $methods = (array) config('site.payments.methods');
        $this->assertArrayHasKey('tg-nouveau-money', $methods);
        $this->assertTrue($methods['tg-nouveau-money']['enabled']);
        $this->assertFalse($methods['tg-flooz']['enabled']);
        $this->assertFalse($methods['bj-celtiis']['enabled']);

        $formation = Formation::factory()->create();
        $student = User::factory()->create();
        $this->actingAs($student)->get(route('checkout.show', $formation))
            ->assertOk()
            ->assertSee('Nouveau Money')
            ->assertDontSee('Celtiis');

        // Au moins un moyen actif.
        $form['methods'] = array_map(fn (array $method): array => [...$method, 'enabled' => null], $form['methods']);
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'paiements'), $form)->assertSessionHasErrors('methods');
    }

    public function test_changing_the_alert_address_warns_the_old_one_too(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'paiements'), $this->paymentsForm(['admin_email' => 'nouvelle@exemple.test']))->assertSessionHasNoErrors();

        Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $mail): bool => $mail->hasTo('nouvelle@exemple.test'));
        Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $mail): bool => $mail->hasTo('academyjoseph71@gmail.com'));
    }

    public function test_affiliate_settings_need_the_password_and_update_public_texts(): void
    {
        $form = ['rate' => 15, 'cookie_days' => 60, 'availability_days' => 10, 'min_withdrawal' => 5000, 'alert_signups_without_purchase' => 10];

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'affiliation'), $form)->assertSessionHasErrors('current_password');
        $this->assertSame(10, config('affiliate.rate'));

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'affiliation'), [...$form, 'current_password' => 'password'])->assertSessionHasNoErrors();

        $this->assertSame(15, config('affiliate.rate'));
        $this->get(route('home'))->assertOk()->assertSee('recevez 15 % de chaque achat validé')->assertDontSee('{affiliation_taux}');
        Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $mail): bool => str_contains($mail->activity->description, 'rate : 10 → 15'));
    }

    public function test_texts_and_faq_can_be_edited(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'textes'), [
            'hero' => ['badge' => 'Exemple de badge', 'title' => 'Titre modifié depuis l\'admin', 'subtitle' => 'Sous-titre', 'points' => ['Accès à vie', '']],
            'trainer' => ['name' => 'Joseph Ahiankonou', 'role' => 'Formateur', 'short_bio' => 'Bio courte', 'bio' => ['Paragraphe'], 'highlights' => ['Point fort']],
            'faq_home' => [['question' => 'Question ajoutée ?', 'answer' => 'Réponse avec le seuil {seuil}.'], ['question' => '', 'answer' => '']],
            'faq_formation' => [['question' => 'Question formation ?', 'answer' => 'Oui.']],
        ])->assertSessionHasNoErrors();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Titre modifié depuis l&#039;admin', false)
            ->assertSee('Question ajoutée ?')
            ->assertSee("Réponse avec le seuil 50\u{00A0}000\u{00A0}FCFA.");

        // La valeur brute garde le marqueur pour les prochaines modifications.
        $this->assertSame('Réponse avec le seuil {seuil}.', SiteSettings::get('site.faq')['home'][0]['answer']);
    }

    public function test_logo_and_share_image_are_uploaded_resized_and_used(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'image'), [
            'logo' => UploadedFile::fake()->image('logo.png', 1200, 400),
            'og_image' => UploadedFile::fake()->image('partage.jpg', 2000, 2000),
        ])->assertSessionHasNoErrors();

        $logo = (string) config('site.brand.logo');
        $og = (string) config('site.brand.og_image');
        Storage::disk('public')->assertExists([$logo, $og]);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($og)) ?: [0, 0];
        $this->assertSame([1200, 630], [$width, $height]);
        [, $logoHeight] = getimagesizefromstring(Storage::disk('public')->get($logo)) ?: [0, 0];
        $this->assertLessThanOrEqual(144, $logoHeight);

        $this->get(route('home'))->assertOk()->assertSee(Storage::disk('public')->url($logo));
        $this->get(route('og.site'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        // Nouvelle icône : toutes les tailles sont créées et utilisées (onglet, écran d'accueil, notifications).
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'image'), [
            'icon' => UploadedFile::fake()->image('icone.png', 600, 600),
        ])->assertSessionHasNoErrors();
        $icons = (string) config('site.brand.icons');
        Storage::disk('public')->assertExists(["{$icons}/favicon.ico", "{$icons}/favicon-32.png", "{$icons}/apple-touch-icon.png", "{$icons}/icon-512.png", "{$icons}/icon-maskable-512.png", "{$icons}/badge-96.png"]);
        $this->get(route('home'))->assertSee(Storage::disk('public')->url("{$icons}/favicon-32.png"));
        $this->get(route('pwa.manifest'))->assertJsonPath('icons.0.src', Storage::disk('public')->url("{$icons}/icon-192.png"));

        // Retour aux fichiers d'origine : les fichiers envoyés sont supprimés.
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'image'), ['reset' => ['logo', 'icons', 'og_image']])->assertSessionHasNoErrors();
        $this->assertSame('images/brand/logo-horizontal.png', config('site.brand.logo'));
        $this->assertSame('icons', config('site.brand.icons'));
        Storage::disk('public')->assertMissing([$logo, $og, "{$icons}/icon-192.png"]);
        $this->get(route('home'))->assertSee(asset('images/brand/logo-horizontal.png'));

        // Une icône trop petite est refusée.
        $this->actingAs($this->admin)->put(route('admin.settings.update', 'image'), ['icon' => UploadedFile::fake()->image('petite.png', 100, 100)])->assertSessionHasErrors('icon');
    }

    public function test_settings_are_forbidden_to_students_and_moderators(): void
    {
        $student = User::factory()->create();
        // Entrées déjà écrites par les migrations (textes de la page de vente des offres).
        $logged = AdminActivity::query()->count();

        foreach ([$student, $this->moderator()] as $user) {
            $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.settings.update', 'regles'), ['verification_threshold' => 1])->assertForbidden();
            $this->actingAs($user)->get(route('admin.testimonials.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.activity.index'))->assertForbidden();
        }

        $this->assertSame($logged, AdminActivity::query()->count());
    }

    public function test_social_and_map_links_are_cleaned_before_being_checked(): void
    {
        $form = [
            'phones' => ['+228 90 00 00 01'],
            'email' => 'contact@exemple.test',
            'address' => 'Adidogomé, Lomé',
            'hours' => 'Du lundi au samedi, de 8 h à 20 h',
            'whatsapp_number' => '+228 90 00 00 01',
            'whatsapp_message' => 'Bonjour',
            'consultation_message' => 'Bonjour, consultation',
            'trained' => '1 000+',
            'trainer_since' => '2023',
        ];

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'coordonnees'), [...$form,
            'map_url' => "Notre adresse : maps.app.goo.gl/AbCdEf123\u{200B}",
            'social' => [
                'facebook' => "  Www.facebook.com/LomeIASchool\u{00A0}\n",
                'tiktok' => "Suivez-nous sur TikTok : https://www.tiktok.com/@lomeiaschool\u{FEFF}",
                'youtube' => 'Https://www.youtube.com/@LomeIASchool',
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://maps.app.goo.gl/AbCdEf123', config('site.contact.map_url'));
        $this->assertSame('https://www.facebook.com/LomeIASchool', config('site.social.facebook'));
        $this->assertSame('https://www.tiktok.com/@lomeiaschool', config('site.social.tiktok'));
        $this->assertSame('https://www.youtube.com/@LomeIASchool', config('site.social.youtube'));

        $this->actingAs($this->admin)->put(route('admin.settings.update', 'coordonnees'), [...$form,
            'map_url' => '',
            'social' => ['facebook' => 'ma page facebook', 'tiktok' => '', 'youtube' => ''],
        ])->assertSessionHasErrors(['social.facebook' => 'Le lien Facebook doit ressembler à https://www.facebook.com/votre-page.']);
        $this->assertSame('https://www.facebook.com/LomeIASchool', config('site.social.facebook'));

        $this->actingAs($this->admin)->get(route('admin.settings.edit', 'coordonnees'))->assertOk()
            ->assertDontSee('type="url"', false)
            ->assertSee('inputmode="url" autocapitalize="none"', false);
    }
}
