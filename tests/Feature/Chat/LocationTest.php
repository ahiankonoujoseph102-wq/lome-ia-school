<?php

namespace Tests\Feature\Chat;

use App\Chat\ChatFeatures;
use App\Enums\ChatMessageType;
use App\Models\ChatMessage;
use App\Models\ChatMessageLocation;
use App\Models\Formation;
use App\Settings\SiteSettings;
use App\Support\GoogleMaps;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Partage de localisation : interrupteur éteint par défaut (bouton absent, route refusée), position du téléphone
 * ou lien Google Maps (domaine vérifié sur le serveur), carte « Ouvrir dans Google Maps », droits du groupe.
 */
class LocationTest extends ChatTestCase
{
    private function enable(): void
    {
        SiteSettings::save(['site.chat_features' => [ChatFeatures::LOCATION => true]]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function share(mixed $user, array $data): TestResponse
    {
        return $this->actingAs($user)->postJson($this->url('chat.locations.store'), $data);
    }

    public function test_switched_off_by_default_the_button_is_absent_and_the_route_refuses(): void
    {
        $student = $this->student();

        $this->assertFalse(ChatFeatures::location());
        $this->actingAs($student)->get($this->url('chat.show'))->assertOk()
            ->assertDontSee('data-test="location-button"', false)
            ->assertDontSee('data-test="location-dialog"', false);

        $this->share($student, ['mode' => 'link', 'maps_url' => 'https://maps.app.goo.gl/abc123'])->assertNotFound();
        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_a_position_is_shared_as_a_card_with_a_google_maps_link(): void
    {
        $this->enable();
        $student = $this->student(['name' => 'Kossi Agbeko']);

        $this->actingAs($student)->get($this->url('chat.show'))->assertOk()
            ->assertSee('data-test="location-button"', false)
            ->assertSee('data-test="location-dialog"', false)
            ->assertSee('Ma position actuelle')
            ->assertSee('Coller un lien Google Maps');

        $this->share($student, ['mode' => 'position', 'latitude' => 6.1724969, 'longitude' => 1.2313577, 'name' => '  Bureau de Lomé IA School ', 'address' => 'Adidogomé, Lomé'])
            ->assertCreated();

        $message = ChatMessage::query()->sole();
        $this->assertSame(ChatMessageType::Location, $message->type);
        $this->assertSame('📍 Lieu · Bureau de Lomé IA School', $message->preview());
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=6.172497,1.231358', $message->location->maps_url);
        $this->assertSame('Bureau de Lomé IA School', $message->location->name);

        // Carte visible par les autres membres : nom, adresse, bouton, sans texte en double.
        $html = $this->actingAs($this->student())->get($this->url('chat.show'))->assertOk()->getContent();
        $this->assertStringContainsString('data-test="location-card"', (string) $html);
        $this->assertStringContainsString('Adidogomé, Lomé', (string) $html);
        $this->assertStringContainsString('Ouvrir dans Google Maps', (string) $html);
        $this->assertStringContainsString('href="https://www.google.com/maps/search/?api=1&amp;query=6.172497,1.231358" target="_blank" rel="noopener noreferrer nofollow"', (string) $html);
        $this->assertSame(1, substr_count((string) $html, '>Bureau de Lomé IA School<'));
    }

    public function test_a_pasted_google_maps_link_is_cleaned_and_accepted(): void
    {
        $this->enable();
        $student = $this->student();

        $this->share($student, ['mode' => 'link', 'maps_url' => "Voici le lieu : HTTPS://Maps.App.Goo.gl/AbC123xyz\u{200B} "])->assertCreated();

        $location = ChatMessageLocation::query()->sole();
        $this->assertSame('https://maps.app.goo.gl/AbC123xyz', $location->maps_url);
        $this->assertNull($location->name);
        $this->assertSame('Lieu partagé', $location->title());
        $this->assertNull($location->latitude);
    }

    public function test_only_google_maps_links_are_accepted(): void
    {
        $valid = [
            'https://maps.app.goo.gl/AbC123',
            'https://goo.gl/maps/xyz',
            'https://www.google.com/maps/place/Lom%C3%A9/@6.13,1.22,12z',
            'https://google.tg/maps?q=Lome',
            'https://maps.google.fr/?q=6.17,1.23',
            'https://www.google.co.uk/maps/search/?api=1&query=1,2',
        ];
        $invalid = [
            'https://evil.com/maps',
            'https://maps.app.goo.gl.evil.com/abc',
            'https://www.google.com/search?q=maps',
            'https://google.com.evil.tg/maps',
            'https://user@maps.app.goo.gl/abc',
            'https://maps.app.goo.gl:8443/abc',
            'http://maps.google.com.attacker.io/',
            'javascript:alert(1)',
            'https://maps.app.goo.gl/',
            'https://goo.gl/abc',
        ];

        foreach ($valid as $link) {
            $this->assertNotNull(GoogleMaps::normalize($link), $link);
        }

        foreach ($invalid as $link) {
            $this->assertNull(GoogleMaps::normalize($link), $link);
        }

        $this->enable();
        $student = $this->student();

        $this->share($student, ['mode' => 'link', 'maps_url' => 'https://evil.com/maps/place'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['maps_url' => GoogleMaps::invalidMessage()]);
        $this->share($student, ['mode' => 'position', 'latitude' => 120, 'longitude' => 1])->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->share($student, ['mode' => 'position'])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->share($student, ['mode' => 'link', 'maps_url' => 'https://maps.app.goo.gl/a', 'name' => str_repeat('a', 121)])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->assertSame(0, ChatMessage::query()->count());
    }

    public function test_only_members_who_can_write_may_share_a_place(): void
    {
        $this->enable();
        $outsider = $this->student([], Formation::factory()->create());
        $data = ['mode' => 'link', 'maps_url' => 'https://maps.app.goo.gl/abc123'];

        // Élève d'une autre formation : groupe refusé.
        $this->share($outsider, $data)->assertForbidden();

        // Membre en sourdine : refus expliqué.
        $muted = $this->student();
        $this->group()->members()->where('user_id', $muted->id)->update(['muted_until' => now()->addDay()]);
        $this->share($muted, $data)->assertUnprocessable()->assertJsonValidationErrors('location');

        // Mode annonces : seule l'équipe écrit.
        $this->group()->forceFill(['announcements_only' => true])->save();
        $this->share($this->student(), $data)->assertUnprocessable();
        $this->share($this->moderator(), $data)->assertCreated();

        $this->assertSame(1, ChatMessage::query()->count());
    }

    public function test_the_usual_message_route_never_accepts_the_location_type(): void
    {
        $this->enable();

        $this->actingAs($this->student())->postJson($this->url('chat.messages.store'), ['type' => 'location', 'body' => 'Ici'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Type de message inconnu.']);
    }

    public function test_deleting_a_place_removes_its_details_and_switched_off_old_cards_stay_visible(): void
    {
        $this->enable();
        $student = $this->student();
        $this->share($student, ['mode' => 'link', 'maps_url' => 'https://maps.app.goo.gl/keep', 'name' => 'Salle A'])->assertCreated();
        $this->share($student, ['mode' => 'link', 'maps_url' => 'https://maps.app.goo.gl/gone', 'name' => 'Salle B'])->assertCreated();
        $gone = ChatMessage::query()->latest('id')->first();

        $this->actingAs($student)->deleteJson($this->url('chat.messages.destroy', ['message' => $gone]))->assertOk();
        $this->assertSame(1, ChatMessageLocation::query()->count());
        $this->assertNull($gone->fresh()->body);

        // Interrupteur éteint ensuite : le bouton disparaît, les lieux déjà partagés restent lisibles.
        SiteSettings::save(['site.chat_features' => [ChatFeatures::LOCATION => false]]);
        $this->actingAs($student)->get($this->url('chat.show'))->assertOk()
            ->assertDontSee('data-test="location-button"', false)
            ->assertSee('Salle A')
            ->assertDontSee('Salle B');
    }

    public function test_the_migration_can_run_again_without_error(): void
    {
        $migration = require database_path('migrations/2026_10_20_100000_create_chat_message_locations_table.php');

        // Base où tout existe déjà : rien ne change, aucune erreur.
        $migration->up();

        // Base à moitié créée (table sans index ni clé) : la reprise ajoute ce qui manque.
        Schema::drop('chat_message_locations');
        Schema::create('chat_message_locations', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('chat_message_id');
            $table->string('name', 120)->nullable();
            $table->string('address', 200)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('maps_url', 500);
            $table->timestamps();
        });
        $migration->up();

        $indexes = collect(Schema::getIndexes('chat_message_locations'))->pluck('name');
        $this->assertContains('chat_locations_message_unique', $indexes);
        $this->assertCount(1, Schema::getForeignKeys('chat_message_locations'));
        foreach ([...$indexes, 'chat_locations_message_fk'] as $name) {
            $this->assertLessThanOrEqual(64, strlen((string) $name));
        }
    }

    public function test_browser_messages_and_link_cleaning_with_node(): void
    {
        $node = (new ExecutableFinder)->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node.js est nécessaire pour exécuter resources/js/chat-location.js.');
        }

        $module = json_encode('file://'.resource_path('js/chat-location.js'));
        $program = <<<JS
            const { geolocationError, cleanMapsLink } = await import({$module});
            console.log(JSON.stringify({
                denied: geolocationError({ code: 1 }),
                unavailable: geolocationError({ code: 2 }),
                timeout: geolocationError({ code: 3 }),
                none: geolocationError(null),
                link: cleanMapsLink('Voici : Maps.App.Goo.gl/AbC12 merci'),
                text: cleanMapsLink('  rien  '),
            }));
            JS;

        $process = new Process([$node, '--input-type=module', '-e', $program]);
        $process->mustRun();
        $out = json_decode(trim($process->getOutput()), true);

        $this->assertStringStartsWith('Vous avez refusé l\'accès à votre position.', $out['denied']);
        $this->assertStringContainsString('Coller un lien Google Maps', $out['denied']);
        $this->assertStringStartsWith('Position introuvable', $out['unavailable']);
        $this->assertStringContainsString('trop de temps', $out['timeout']);
        $this->assertStringContainsString('ne permet pas', $out['none']);
        $this->assertSame('https://maps.app.goo.gl/AbC12', $out['link']);
        $this->assertSame('rien', $out['text']);
    }
}
