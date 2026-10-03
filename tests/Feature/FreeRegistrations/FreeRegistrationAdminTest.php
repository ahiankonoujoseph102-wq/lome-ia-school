<?php

namespace Tests\Feature\FreeRegistrations;

use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Models\AdminActivity;
use App\Models\Formation;
use App\Models\FreeRegistration;
use App\Models\FreeRegistrationOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin d'une formation gratuite : inscrits par option (nom, WhatsApp, e-mail), places prises, export CSV,
 * réglages (date, lieu, options). Réservé à l'admin.
 */
class FreeRegistrationAdminTest extends TestCase
{
    use RefreshDatabase;

    private Formation $formation;

    private FreeRegistrationOption $onsite;

    private FreeRegistrationOption $online;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formation = Formation::factory()->create(['title' => 'Formation gratuite exemple', 'price' => 0, 'event_date' => '2026-10-10', 'event_location' => 'Adidogomé, Lycée moderne 2, Lomé']);
        $this->onsite = $this->formation->freeOptions()->create(['label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => 20, 'position' => 1]);
        $this->online = $this->formation->freeOptions()->create(['label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50, 'position' => 2]);
    }

    private function register(User $user, FreeRegistrationOption $option, bool $cancelled = false): FreeRegistration
    {
        return FreeRegistration::query()->create([
            'formation_id' => $this->formation->id,
            'user_id' => $user->id,
            'free_registration_option_id' => $option->id,
            'cancelled_at' => $cancelled ? now() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $options = [], array $extra = []): array
    {
        return [
            'event_date' => '2026-10-10',
            'event_location' => 'Adidogomé, Lycée moderne 2, Lomé',
            'options' => $options ?: [
                ['id' => $this->onsite->id, 'label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => 20],
                ['id' => $this->online->id, 'label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50],
            ],
            ...$extra,
        ];
    }

    public function test_the_admin_sees_registrants_by_option_with_contact_details_and_seats_taken(): void
    {
        $ama = User::factory()->create(['name' => 'Ama Exemple', 'email' => 'ama@exemple.test', 'phone' => '+22890112233']);
        $kofi = User::factory()->create(['name' => 'Kofi Exemple', 'email' => 'kofi@exemple.test']);
        $gone = User::factory()->create(['name' => 'Annulé Exemple']);
        $this->register($ama, $this->onsite);
        $this->register($kofi, $this->online);
        $this->register($gone, $this->onsite, cancelled: true);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.free-registrations.show', $this->formation))
            ->assertOk()
            ->assertSeeInOrder(['Présentiel journée', 'Ama Exemple', '+228 90 11 22 33', 'ama@exemple.test', 'En ligne soir', 'Kofi Exemple', 'kofi@exemple.test'])
            ->assertSee('1 / 20 places prises')
            ->assertSee('1 / 50 places prises')
            ->assertDontSee('Annulé Exemple');
    }

    public function test_the_export_lists_registrants_and_is_logged(): void
    {
        $this->register(User::factory()->create(['name' => 'Ama Exemple', 'email' => 'ama@exemple.test', 'phone' => '+22890112233']), $this->onsite);
        $this->register(User::factory()->create(['name' => 'Annulé Exemple']), $this->online, cancelled: true);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.free-registrations.export', $this->formation))
            ->assertOk();

        $csv = $response->streamedContent();
        $lines = array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), array_values(array_filter(explode("\n", ltrim($csv, "\u{FEFF}")))));
        $this->assertSame(['Option', 'Nom', 'WhatsApp', 'E-mail', 'Inscrit(e) le'], $lines[0]);
        // Le numéro commence par « + » : préfixé d'une apostrophe, comme dans les autres exports (protection des formules).
        $this->assertSame(['Présentiel journée', 'Ama Exemple', "'+228 90 11 22 33", 'ama@exemple.test'], array_slice($lines[1], 0, 4));
        $this->assertCount(2, $lines);
        $this->assertStringNotContainsString('Annulé Exemple', $csv);
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::Export->value)->exists());
    }

    public function test_only_the_admin_has_access_and_only_for_a_free_formation(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.free-registrations.show', $this->formation))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Moderator]))->get(route('admin.free-registrations.show', $this->formation))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Moderator]))->get(route('admin.free-registrations.export', $this->formation))->assertForbidden();

        $paid = Formation::factory()->create(['price' => 25000]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.free-registrations.show', $paid))->assertNotFound();
    }

    public function test_the_formations_list_links_to_registrations_for_a_free_formation_only(): void
    {
        Formation::factory()->create(['price' => 25000]);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.formations.index'))->assertOk();

        $response->assertSee(route('admin.free-registrations.show', $this->formation));
        // La formation gratuite créée par la migration compte aussi : un lien par formation à 0 FCFA, aucun pour le payant.
        $this->assertSame(Formation::query()->where('price', 0)->count(), substr_count($response->getContent(), 'data-test="free-registrations-link"'));
        $this->assertSame(Formation::query()->count() - Formation::query()->where('price', 0)->count(), substr_count($response->getContent(), 'vente validée') + substr_count($response->getContent(), 'ventes validées'));
    }

    public function test_the_admin_updates_date_place_and_options_and_adds_one(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.free-registrations.update', $this->formation), $this->form([
                ['id' => $this->onsite->id, 'label' => 'Présentiel journée (Lomé)', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => 25],
                ['id' => $this->online->id, 'label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 60],
                ['label' => 'En ligne journée', 'schedule' => '9 h à 16 h', 'mode' => 'online', 'capacity' => 50],
                ['label' => '', 'schedule' => '', 'mode' => 'onsite', 'capacity' => ''],
            ], ['event_date' => '2026-10-17', 'event_location' => 'Lomé']))
            ->assertRedirect(route('admin.free-registrations.show', $this->formation))
            ->assertSessionHasNoErrors();

        $this->formation->refresh();
        $this->assertSame('2026-10-17', $this->formation->event_date->toDateString());
        $this->assertSame('Lomé', $this->formation->event_location);
        $this->assertSame(['Présentiel journée (Lomé)', 'En ligne soir', 'En ligne journée'], $this->formation->freeOptions()->pluck('label')->all());
        $this->assertSame([25, 60, 50], $this->formation->freeOptions()->pluck('capacity')->all());
        $this->assertTrue(AdminActivity::query()->where('action', AdminAction::FreeRegistrationUpdated->value)->exists());
    }

    public function test_seats_cannot_go_below_the_registrants_and_a_used_option_cannot_be_removed(): void
    {
        $this->register(User::factory()->create(), $this->onsite);
        $this->register(User::factory()->create(), $this->onsite);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.free-registrations.update', $this->formation), $this->form([
                ['id' => $this->onsite->id, 'label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => 1],
                ['id' => $this->online->id, 'label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50],
            ]))
            ->assertSessionHasErrors('options.0.capacity');

        $this->actingAs($admin)
            ->put(route('admin.free-registrations.update', $this->formation), $this->form([
                ['id' => $this->onsite->id, 'label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => 20, 'remove' => '1'],
                ['id' => $this->online->id, 'label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50, 'remove' => '1'],
            ]))
            ->assertSessionHasErrors('options.0.label');

        $this->assertSame(20, $this->onsite->fresh()->capacity);
        $this->assertSame(2, $this->formation->freeOptions()->count());
    }

    public function test_an_unused_option_can_be_removed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.free-registrations.update', $this->formation), $this->form([
                ['id' => $this->onsite->id, 'label' => 'Présentiel journée', 'schedule' => '9 h à 16 h', 'mode' => 'onsite', 'capacity' => 20],
                ['id' => $this->online->id, 'label' => 'En ligne soir', 'schedule' => '18 h à 20 h', 'mode' => 'online', 'capacity' => 50, 'remove' => '1'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['Présentiel journée'], $this->formation->freeOptions()->pluck('label')->all());
    }
}
