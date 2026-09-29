<?php

namespace App\Support;

/**
 * Diffusion en temps réel (Pusher) : jamais de plantage quand les clés manquent.
 *
 * Laravel crée le diffuseur dès le démarrage (routes/channels.php), y compris pendant
 * « package:discover » au déploiement. Avec BROADCAST_CONNECTION=pusher et des clés PUSHER_*
 * absentes, le client Pusher refuse d'être créé et tout le déploiement échoue.
 *
 * Au démarrage, si la connexion choisie n'existe pas ou n'a pas toutes ses clés, on bascule sur
 * le diffuseur « null » : les messages sont enregistrés et le chat se met à jour tout seul
 * toutes les quelques secondes (voir App\Chat\ChatRealtime).
 */
class BroadcastingConfig
{
    /**
     * Clés obligatoires par pilote de diffusion.
     *
     * @var array<string, list<string>>
     */
    private const array REQUIRED_KEYS = [
        'pusher' => ['key', 'secret', 'app_id'],
        'reverb' => ['key', 'secret', 'app_id'],
        'ably' => ['key'],
    ];

    public static function configure(): void
    {
        $default = (string) config('broadcasting.default');

        if (! self::usable($default)) {
            config(['broadcasting.default' => 'null']);
        }
    }

    /**
     * La connexion peut-elle être créée sans erreur ?
     */
    public static function usable(string $connection): bool
    {
        if ($connection === '' || $connection === 'null') {
            return $connection === 'null';
        }

        $config = config("broadcasting.connections.{$connection}");

        if (! is_array($config)) {
            return false;
        }

        foreach (self::REQUIRED_KEYS[$config['driver'] ?? ''] ?? [] as $key) {
            if (! filled($config[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
