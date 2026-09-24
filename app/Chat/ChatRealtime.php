<?php

namespace App\Chat;

use App\Events\Chat\ChatGroupChanged;
use App\Models\ChatGroup;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Temps réel des groupes (Pusher).
 *
 * - Diffusion immédiate (jamais par la file d'attente, pour rester instantané).
 * - Si Pusher n'est pas configuré ou ne répond pas, rien ne casse : le message est enregistré
 *   et les navigateurs le récupèrent par la mise à jour régulière (toutes les quelques secondes).
 */
class ChatRealtime
{
    public function __construct(private BroadcastFactory $broadcaster) {}

    /**
     * Pusher configuré avec ses trois clés : les navigateurs peuvent s'y connecter.
     */
    public static function pusherConfigured(): bool
    {
        return config('broadcasting.default') === 'pusher'
            && filled(config('broadcasting.connections.pusher.key'))
            && filled(config('broadcasting.connections.pusher.secret'))
            && filled(config('broadcasting.connections.pusher.app_id'));
    }

    /**
     * Le serveur diffuse-t-il ? (Pusher configuré, ou autre diffuseur actif : journal, tests…)
     */
    public static function broadcastingEnabled(): bool
    {
        $default = (string) config('broadcasting.default');
        $driver = (string) config("broadcasting.connections.{$default}.driver", $default);

        return match ($driver) {
            'null', '' => false,
            'pusher' => self::pusherConfigured(),
            default => true,
        };
    }

    /**
     * Réglages transmis au navigateur (clé publique et cluster uniquement, jamais le secret).
     *
     * @return array{key: string, cluster: string}|null
     */
    public static function clientConfig(): ?array
    {
        if (! self::pusherConfigured()) {
            return null;
        }

        return [
            'key' => (string) config('broadcasting.connections.pusher.key'),
            'cluster' => (string) (config('broadcasting.connections.pusher.options.cluster') ?: 'mt1'),
        ];
    }

    public function notify(ChatGroup $group, string $kind, ?int $messageId = null): bool
    {
        if (! self::broadcastingEnabled()) {
            return false;
        }

        try {
            $this->broadcaster->queue(new ChatGroupChanged($group, $kind, $messageId));

            return true;
        } catch (Throwable $exception) {
            // Pusher indisponible : les membres recevront le message par la mise à jour régulière.
            Log::warning('Diffusion en temps réel impossible, mise à jour régulière utilisée.', [
                'group' => $group->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
