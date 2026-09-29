<?php

namespace Tests\Feature\Deployment;

use App\Chat\ChatRealtime;
use App\Support\BroadcastingConfig;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Broadcast;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Déploiement (Laravel Cloud, Hostinger) : des clés Pusher absentes ou vides ne doivent jamais faire
 * planter le démarrage (« package:discover »), quelle que soit la valeur de BROADCAST_CONNECTION.
 */
class BroadcastingConfigTest extends TestCase
{
    /**
     * Rejoue le démarrage de l'application avec la configuration donnée.
     *
     * @param  array<string, mixed>  $config
     */
    private function bootWith(array $config): BroadcastManager
    {
        config($config);
        BroadcastingConfig::configure();

        $manager = $this->app->make(BroadcastManager::class);
        $manager->purge();

        // Comme au démarrage : les canaux privés sont déclarés, ce qui crée le diffuseur.
        require base_path('routes/channels.php');

        return $manager;
    }

    public function test_missing_pusher_keys_would_crash_without_the_fallback(): void
    {
        config([
            'broadcasting.connections.pusher.key' => null,
            'broadcasting.connections.pusher.secret' => null,
            'broadcasting.connections.pusher.app_id' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('auth_key');

        $this->app->make(BroadcastManager::class)->connection('pusher');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function missingKeys(): array
    {
        return [
            'clés absentes (null)' => [null],
            'clés vides' => [''],
        ];
    }

    #[DataProvider('missingKeys')]
    public function test_pusher_without_keys_falls_back_to_the_null_broadcaster(mixed $value): void
    {
        $manager = $this->bootWith([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => $value,
            'broadcasting.connections.pusher.secret' => $value,
            'broadcasting.connections.pusher.app_id' => $value,
        ]);

        $this->assertSame('null', config('broadcasting.default'));
        $this->assertInstanceOf(NullBroadcaster::class, $manager->connection());
        $this->assertFalse(ChatRealtime::broadcastingEnabled());
        $this->assertNull(ChatRealtime::clientConfig());
    }

    public function test_one_missing_key_is_enough_to_fall_back(): void
    {
        $this->bootWith([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'cle',
            'broadcasting.connections.pusher.secret' => 'secret',
            'broadcasting.connections.pusher.app_id' => null,
        ]);

        $this->assertSame('null', config('broadcasting.default'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherConnections(): array
    {
        return [
            'reverb sans clés' => ['reverb'],
            'ably sans clé' => ['ably'],
            'connexion inconnue' => ['nimporte-quoi'],
            'valeur vide' => [''],
        ];
    }

    #[DataProvider('otherConnections')]
    public function test_any_unusable_connection_falls_back_without_crashing(string $connection): void
    {
        $manager = $this->bootWith([
            'broadcasting.default' => $connection,
            'broadcasting.connections.reverb.key' => null,
            'broadcasting.connections.reverb.secret' => null,
            'broadcasting.connections.reverb.app_id' => null,
            'broadcasting.connections.ably.key' => null,
        ]);

        $this->assertSame('null', config('broadcasting.default'));
        $this->assertInstanceOf(NullBroadcaster::class, $manager->connection());
    }

    public function test_the_null_and_log_connections_are_kept(): void
    {
        $this->bootWith(['broadcasting.default' => 'log']);
        $this->assertSame('log', config('broadcasting.default'));

        $this->bootWith(['broadcasting.default' => 'null']);
        $this->assertSame('null', config('broadcasting.default'));
    }

    public function test_pusher_with_its_three_keys_is_kept(): void
    {
        $manager = $this->bootWith([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'cle-publique',
            'broadcasting.connections.pusher.secret' => 'secret',
            'broadcasting.connections.pusher.app_id' => '123',
        ]);

        $this->assertSame('pusher', config('broadcasting.default'));
        $this->assertInstanceOf(PusherBroadcaster::class, $manager->connection());
        $this->assertTrue(ChatRealtime::pusherConfigured());
    }

    public function test_the_channels_file_declares_the_group_channel(): void
    {
        $this->bootWith(['broadcasting.default' => 'pusher', 'broadcasting.connections.pusher.key' => null]);

        $this->assertArrayHasKey('chat.group.{group}', Broadcast::getChannels()->all());
    }
}
