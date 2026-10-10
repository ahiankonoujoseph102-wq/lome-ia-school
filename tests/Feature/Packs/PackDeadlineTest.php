<?php

namespace Tests\Feature\Packs;

use App\Enums\AdminAction;
use App\Enums\EnrollmentSource;
use App\Enums\PurchaseStatus;
use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use App\Packs\PackDeadline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Feature\Admin\AdminTestCase;

/**
 * Fin des inscriptions des offres d'accompagnement : une vraie date limite. Avant : compte à rebours et achat
 * possible ; après : « Inscriptions closes » et toute nouvelle commande refusée par le serveur. Une capture
 * envoyée avant reste à valider ou refuser, les accès déjà ouverts sont gardés, les formations habituelles et
 * les offres sans date ne sont pas concernées.
 */
class PackDeadlineTest extends AdminTestCase
{
    private Formation $pack;

    private Formation $chatgpt;

    private Formation $posters;

    private int $captures = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');

        // Offres créées par les migrations (date réglée au lundi 12 octobre 2026 à 16 h) : publiées ici.
        Formation::query()->where('kind', 'pack')->update(['is_active' => true]);
        $this->pack = Formation::query()->where('slug', 'accompagnement-ia-niveau-1')->sole();
        $this->chatgpt = Formation::factory()->create(['title' => 'Formation ChatGPT & Claude', 'price' => 5500]);
        $this->posters = Formation::factory()->create(['title' => 'Création d\'affiches pro avec l\'IA', 'price' => 36000]);
        $vip = Formation::query()->where('slug', 'groupe-vip')->sole();
        $this->pack->includedFormations()->sync([$this->chatgpt->id => ['position' => 1], $this->posters->id => ['position' => 2], $vip->id => ['position' => 3]]);
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse($time, 'Africa/Lome'));
    }

    /**
     * @return TestResponse<Response>
     */
    private function pay(User $student, Formation $formation)
    {
        return $this->actingAs($student)->post(route('checkout.pay', $formation), [
            'payment_method' => 'tg-mixx',
            'proof' => UploadedFile::fake()->image('capture.png', 600 + ++$this->captures, 1200),
        ]);
    }

    public function test_the_migration_sets_the_three_packs_to_monday_12_october_2026_at_4_pm_lome_time(): void
    {
        $packs = Formation::query()->where('kind', 'pack')->orderBy('sort_order')->get();

        $this->assertCount(3, $packs);

        foreach ($packs as $pack) {
            $this->assertSame('2026-10-12 16:00:00', $pack->registration_ends_at?->format('Y-m-d H:i:s'), $pack->slug);
            $this->assertSame('Africa/Lome', $pack->registration_ends_at->timezone->getName());
            $this->assertSame('lundi 12 octobre 2026 à 16 h', PackDeadline::label($pack->registration_ends_at));
        }

        $this->assertSame('2026-10-12T16:00:00+00:00', $packs[0]->registration_ends_at->toIso8601String());
    }

    public function test_the_initial_date_can_be_applied_again_without_any_side_effect(): void
    {
        $before = DB::table('formations')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $this->assertSame(0, PackDeadline::applyInitial());
        $this->assertSame($before, DB::table('formations')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());

        // Une date réglée dans l'admin n'est jamais remplacée ; une offre sans date reçoit la date de départ, seule.
        DB::table('formations')->where('slug', 'accompagnement-ia-niveau-2')->update(['registration_ends_at' => '2026-11-01 10:00:00']);
        DB::table('formations')->where('slug', 'accompagnement-ia-niveau-3')->update(['registration_ends_at' => null]);
        $level3 = DB::table('formations')->where('slug', 'accompagnement-ia-niveau-3')->first();

        $this->assertSame(1, PackDeadline::applyInitial());
        $this->assertSame('2026-11-01 10:00:00', DB::table('formations')->where('slug', 'accompagnement-ia-niveau-2')->value('registration_ends_at'));
        $after = DB::table('formations')->where('slug', 'accompagnement-ia-niveau-3')->first();
        $this->assertSame('2026-10-12 16:00:00', $after->registration_ends_at);
        $this->assertSame($level3->price, $after->price);
        $this->assertSame($level3->description, $after->description);
        $this->assertSame($level3->updated_at, $after->updated_at);
        $this->assertSame(0, (int) DB::table('formations')->where('kind', '!=', 'pack')->whereNotNull('registration_ends_at')->count());
    }

    public function test_before_the_date_the_card_shows_the_deadline_the_countdown_and_the_button(): void
    {
        $this->at('2026-10-10 12:00:00');

        $this->get(route('packs'))
            ->assertOk()
            ->assertSee('Inscriptions ouvertes jusqu\'au lundi 12 octobre 2026 à 16 h', false)
            ->assertSeeInOrder(['data-test="pack-countdown-days">2<', 'data-test="pack-countdown-hours">04<', 'data-test="pack-countdown-minutes">00<', 'data-test="pack-countdown-seconds">00<'], false)
            ->assertSee('data-test="pack-choose"', false)
            ->assertDontSee('data-test="pack-closed"', false);

        // Même date fixe pour tout le monde : le compte à rebours suit l'heure, il ne repart jamais à zéro.
        $this->at('2026-10-12 15:20:30');
        $this->get(route('packs'))
            ->assertSeeInOrder(['data-test="pack-countdown-days">0<', 'data-test="pack-countdown-hours">00<', 'data-test="pack-countdown-minutes">39<', 'data-test="pack-countdown-seconds">30<'], false);
        $this->actingAs(User::factory()->create())->get(route('packs'))
            ->assertSeeInOrder(['data-test="pack-countdown-days">0<', 'data-test="pack-countdown-hours">00<', 'data-test="pack-countdown-minutes">39<', 'data-test="pack-countdown-seconds">30<'], false);

        // Page de l'offre (récapitulatif de commande) : même encadré, paiement proposé.
        $this->actingAs(User::factory()->create())->get(route('checkout.show', $this->pack))
            ->assertOk()
            ->assertSee('Inscriptions ouvertes jusqu\'au lundi 12 octobre 2026 à 16 h', false)
            ->assertSee('data-test="pack-countdown"', false)
            ->assertDontSee('data-test="checkout-closed"', false);
    }

    public function test_an_order_sent_one_minute_before_the_date_is_accepted(): void
    {
        $this->at('2026-10-12 15:59:00');

        $this->pay(User::factory()->create(), $this->pack)->assertSessionHasNoErrors();

        $this->assertSame(1, Purchase::query()->where('formation_id', $this->pack->id)->count());
    }

    public function test_after_the_date_the_card_shows_closed_without_any_purchase_button(): void
    {
        $this->at('2026-10-12 16:00:00');

        $this->get(route('packs'))
            ->assertOk()
            ->assertSee('Inscriptions closes')
            ->assertSee('data-test="pack-closed"', false)
            ->assertDontSee('data-test="pack-choose"', false)
            ->assertDontSee('Je m\'inscris maintenant', false)
            ->assertDontSee('data-test="pack-countdown"', false)
            ->assertDontSee(route('checkout.show', $this->pack));

        // Ancien lien de la page de l'offre : message clair, aucun paiement ni formulaire de compte.
        $this->actingAs(User::factory()->create())->get(route('checkout.show', $this->pack))
            ->assertOk()
            ->assertSee('Les inscriptions à cette offre sont closes.', false)
            ->assertDontSee('Envoyer la capture de mon paiement')
            ->assertDontSee('data-test="pack-countdown"', false);
        auth()->logout();
        $this->get(route('checkout.show', $this->pack))
            ->assertOk()
            ->assertSee('Les inscriptions à cette offre sont closes.', false)
            ->assertDontSee('commande-inscription');
    }

    public function test_after_the_date_the_server_refuses_any_new_order_even_from_an_old_page(): void
    {
        $student = User::factory()->create();
        $this->at('2026-10-12 16:00:01');

        $this->from(route('checkout.show', $this->pack))
            ->pay($student, $this->pack)
            ->assertRedirect(route('checkout.show', $this->pack))
            ->assertSessionHasErrors(['formation' => 'Les inscriptions à cette offre sont closes.']);

        // Même avec un code promo ou un mois plus tard : rien n'est enregistré, aucune capture gardée, aucun accès.
        $this->at('2026-11-15 09:00:00');
        $this->actingAs($student)->post(route('checkout.pay', $this->pack), [
            'payment_method' => 'tg-mixx',
            'promo_code' => 'INCONNU',
            'proof' => UploadedFile::fake()->image('capture.png', 900, 1200),
        ])->assertSessionHasErrors(['formation' => 'Les inscriptions à cette offre sont closes.']);

        $this->assertSame(0, Purchase::query()->count());
        $this->assertSame([], Storage::disk('payment_proofs')->allFiles());
        $this->assertFalse($student->isEnrolledIn($this->pack));
        $this->assertFalse($student->isEnrolledIn($this->chatgpt));
    }

    public function test_an_order_sent_before_the_date_can_still_be_validated_or_refused_after_it(): void
    {
        // Niveau 3 (75 000 FCFA) : au-dessus du seuil, la capture attend la validation de l'équipe.
        $level3 = Formation::query()->where('slug', 'accompagnement-ia-niveau-3')->sole();
        $level3->includedFormations()->sync([$this->chatgpt->id => ['position' => 1]]);
        [$first, $second] = [User::factory()->create(), User::factory()->create()];

        $this->at('2026-10-12 15:30:00');
        $this->pay($first, $level3)->assertSessionHasNoErrors();
        $this->pay($second, $level3)->assertSessionHasNoErrors();
        [$approved, $refused] = [Purchase::query()->where('user_id', $first->id)->sole(), Purchase::query()->where('user_id', $second->id)->sole()];
        $this->assertSame(PurchaseStatus::Pending, $approved->status);

        $this->at('2026-10-13 09:00:00');
        $this->assertTrue($level3->fresh()->registrationsClosed());

        // L'élève voit que sa commande reste valable.
        $this->actingAs($first)->get(route('checkout.show', $level3))
            ->assertSee('Les inscriptions à cette offre sont closes.', false)
            ->assertSee('data-test="checkout-closed-pending"', false);

        $this->actingAs($this->admin)->post(route('admin.payments.approve', $approved))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.payments.reject', $refused), ['reason' => 'Paiement introuvable'])->assertSessionHasNoErrors();

        $this->assertSame(PurchaseStatus::Validated, $approved->fresh()->status);
        $this->assertSame(PurchaseStatus::Rejected, $refused->fresh()->status);
        $this->assertTrue($first->isEnrolledIn($level3));
        $this->assertTrue($first->isEnrolledIn($this->chatgpt));
        $this->assertFalse($second->isEnrolledIn($level3));
    }

    public function test_students_who_have_the_pack_keep_all_their_access_after_the_date(): void
    {
        $student = User::factory()->create();
        $this->at('2026-10-11 10:00:00');
        $this->pay($student, $this->pack)->assertSessionHasNoErrors();
        $purchase = Purchase::query()->where('user_id', $student->id)->sole();
        $this->assertSame(PurchaseStatus::Provisional, $purchase->status);

        $this->at('2026-10-20 10:00:00');

        $this->assertTrue($student->isEnrolledIn($this->pack));
        $this->assertTrue($student->isEnrolledIn($this->chatgpt));
        $this->assertTrue($student->isEnrolledIn($this->posters));
        $this->assertSame(EnrollmentSource::Pack, $student->enrollments()->where('formation_id', $this->chatgpt->id)->sole()->source);
        $this->actingAs($student)->get(route('packs'))->assertSee('data-test="pack-owned"', false);
        $this->actingAs($student)->get(route('student.formations.show', $this->chatgpt))->assertOk();

        // Validation de l'achat provisoire après la date : accès gardés.
        $this->actingAs($this->admin)->post(route('admin.payments.approve', $purchase))->assertSessionHasNoErrors();
        $this->assertSame(PurchaseStatus::Validated, $purchase->fresh()->status);
        $this->assertTrue($student->fresh()->isEnrolledIn($this->posters));
    }

    public function test_a_pack_without_a_date_has_no_limit(): void
    {
        $this->pack->forceFill(['registration_ends_at' => null])->save();
        $this->at('2027-06-01 10:00:00');

        $this->assertFalse($this->pack->fresh()->registrationsClosed());
        $this->get(route('packs'))
            ->assertSee('data-test="pack-choose"', false)
            ->assertSee('data-test="pack-closed"', false); // les deux autres offres, elles, sont closes
        $this->get(route('checkout.show', $this->pack))->assertDontSee('data-test="pack-deadline"', false)->assertDontSee('data-test="checkout-closed"', false);
        $this->pay(User::factory()->create(), $this->pack)->assertSessionHasNoErrors();
        $this->assertSame(1, Purchase::query()->count());
    }

    public function test_formations_sold_separately_are_not_concerned(): void
    {
        // Même avec une date écrite par erreur dans la colonne, une formation habituelle n'est jamais close.
        $this->chatgpt->forceFill(['registration_ends_at' => '2026-10-01 10:00:00'])->save();
        $this->at('2026-10-20 10:00:00');

        $this->assertFalse($this->chatgpt->fresh()->registrationsClosed());
        $this->assertNull($this->chatgpt->fresh()->registrationDeadline());
        $this->get(route('formations.show', $this->chatgpt))->assertOk()->assertDontSee('Inscriptions closes');
        $this->get(route('checkout.show', $this->chatgpt))->assertOk()->assertDontSee('data-test="checkout-closed"', false)->assertDontSee('data-test="pack-deadline"', false);
        $this->pay(User::factory()->create(), $this->chatgpt)->assertSessionHasNoErrors();
        $this->assertSame(1, Purchase::query()->where('formation_id', $this->chatgpt->id)->count());
    }

    public function test_the_admin_sets_and_removes_the_date_of_one_pack(): void
    {
        $this->at('2026-10-10 12:00:00');
        $form = [
            'title' => $this->pack->title,
            'subtitle' => '',
            'description' => $this->pack->description,
            'price' => $this->pack->price,
            'category' => $this->pack->category->value,
            'included_sent' => '1',
            'included' => $this->pack->includedFormations()->pluck('formations.id')->all(),
        ];

        $this->actingAs($this->admin)->get(route('admin.formations.edit', $this->pack))
            ->assertOk()
            ->assertSee('Fin des inscriptions')
            ->assertSee('value="2026-10-12T16:00"', false)
            ->assertSee('Inscriptions ouvertes jusqu\'au lundi 12 octobre 2026 à 16 h');

        $this->actingAs($this->admin)->put(route('admin.formations.update', $this->pack), $form + ['registration_deadline_sent' => '1', 'registration_ends_at' => '2026-10-14T18:30'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-10-14 18:30:00', $this->pack->fresh()->registration_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('mercredi 14 octobre 2026 à 18 h 30', PackDeadline::label($this->pack->fresh()->registration_ends_at));
        $this->assertStringContainsString('Fin des inscriptions : lundi 12 octobre 2026 à 16 h → mercredi 14 octobre 2026 à 18 h 30.', AdminActivity::query()->where('action', AdminAction::FormationUpdated)->latest('id')->value('description'));

        // Ancienne page restée ouverte (formulaire sans ce champ) : la date reste telle quelle.
        $this->actingAs($this->admin)->put(route('admin.formations.update', $this->pack), $form)->assertSessionHasNoErrors();
        $this->assertNotNull($this->pack->fresh()->registration_ends_at);

        $this->actingAs($this->admin)->put(route('admin.formations.update', $this->pack), $form + ['registration_deadline_sent' => '1', 'registration_ends_at' => 'demain'])
            ->assertSessionHasErrors('registration_ends_at');

        // Champ vidé : aucune limite.
        $this->actingAs($this->admin)->put(route('admin.formations.update', $this->pack), $form + ['registration_deadline_sent' => '1', 'registration_ends_at' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($this->pack->fresh()->registration_ends_at);
        $this->assertSame(10000, $this->pack->fresh()->price);
    }

    public function test_the_admin_sets_the_same_date_for_the_three_packs_at_once(): void
    {
        $this->at('2026-10-10 12:00:00');

        $this->actingAs($this->admin)->get(route('admin.formations.index'))
            ->assertOk()
            ->assertSee('Fin des inscriptions de toutes les offres')
            ->assertSee('Appliquer aux 3 offres')
            ->assertSee('Inscriptions jusqu\'au lundi 12 octobre 2026 à 16 h');

        $prices = Formation::query()->where('kind', 'pack')->orderBy('id')->pluck('price', 'slug')->all();

        $this->actingAs($this->admin)->put(route('admin.formations.packs-deadline'), ['registration_ends_at' => '2026-10-19T08:00'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Fin des inscriptions réglée au lundi 19 octobre 2026 à 8 h pour les 3 offres.');

        foreach (Formation::query()->where('kind', 'pack')->get() as $pack) {
            $this->assertSame('2026-10-19 08:00:00', $pack->registration_ends_at->format('Y-m-d H:i:s'));
        }

        $this->assertSame($prices, Formation::query()->where('kind', 'pack')->orderBy('id')->pluck('price', 'slug')->all());
        $this->assertSame(0, Formation::query()->where('kind', '!=', 'pack')->whereNotNull('registration_ends_at')->count());
        $this->assertStringContainsString('lundi 19 octobre 2026 à 8 h', AdminActivity::query()->latest('id')->value('description'));

        $this->actingAs($this->admin)->put(route('admin.formations.packs-deadline'), ['registration_ends_at' => '19/10/2026'])
            ->assertSessionHasErrors('registration_ends_at');

        $this->actingAs($this->admin)->put(route('admin.formations.packs-deadline'), ['registration_ends_at' => ''])
            ->assertSessionHas('status', 'Fin des inscriptions retirée pour les 3 offres : aucune limite.');
        $this->assertSame(0, Formation::query()->whereNotNull('registration_ends_at')->count());
    }

    public function test_only_the_admin_changes_the_date(): void
    {
        $this->actingAs($this->moderator())->put(route('admin.formations.packs-deadline'), ['registration_ends_at' => ''])->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('admin.formations.packs-deadline'), ['registration_ends_at' => ''])->assertForbidden();

        $this->assertSame(3, Formation::query()->whereNotNull('registration_ends_at')->count());
    }

    public function test_the_server_and_browser_countdowns_compute_the_same_time_left(): void
    {
        $deadline = Carbon::parse('2026-10-12 16:00:00', 'Africa/Lome');
        $cases = ['2026-10-10 12:00:00', '2026-10-12 15:59:30', '2026-10-12 15:20:30', '2026-10-11 16:00:00', '2026-10-12 16:00:00', '2026-10-13 00:00:00'];
        $server = array_map(fn (string $now): array => PackDeadline::remaining($deadline, Carbon::parse($now, 'Africa/Lome')), $cases);

        $this->assertSame(['days' => 2, 'hours' => 4, 'minutes' => 0, 'seconds' => 0, 'closed' => false], $server[0]);
        $this->assertSame(['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 30, 'closed' => false], $server[1]);
        $this->assertSame(['days' => 0, 'hours' => 0, 'minutes' => 39, 'seconds' => 30, 'closed' => false], $server[2]);
        $this->assertSame(['days' => 1, 'hours' => 0, 'minutes' => 0, 'seconds' => 0, 'closed' => false], $server[3]);
        $this->assertTrue($server[4]['closed']);
        $this->assertTrue($server[5]['closed']);

        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/countdown.js.');
        }

        $module = json_encode('file://'.resource_path('js/countdown.js'));
        $deadlineMs = $deadline->getTimestampMs();
        $nows = json_encode(array_map(fn (string $now): int => Carbon::parse($now, 'Africa/Lome')->getTimestampMs(), $cases));
        $program = <<<JS
            const c = await import({$module});
            const out = { parts: {$nows}.map((now) => c.countdownParts({$deadlineMs}, now)) };
            // Téléphone en avance de 3 heures : l'écart avec l'heure du serveur est corrigé.
            const offset = c.clockOffset({$deadlineMs} - 3600000, {$deadlineMs} - 3600000 + 3 * 3600000);
            out.skewed = c.countdownParts({$deadlineMs}, {$deadlineMs} - 3600000 + 3 * 3600000 + offset);
            out.noServer = c.clockOffset(Number.NaN, 5);
            console.log(JSON.stringify(out));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertSame($server, $out['parts']);
        $this->assertSame(['days' => 0, 'hours' => 1, 'minutes' => 0, 'seconds' => 0, 'closed' => false], $out['skewed']);
        $this->assertSame(0, $out['noServer']);
    }
}
