<?php

namespace Tests\Fakes;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Str;

/**
 * Faux diffuseur (à la place de Pusher) : garde en mémoire chaque diffusion, ou échoue sur demande.
 */
class RecordingBroadcaster extends Broadcaster
{
    /** @var list<array{channels: array<int, string>, event: string, payload: array<string, mixed>}> */
    public array $broadcasts = [];

    public bool $failing = false;

    /**
     * Vérifie l'accès au canal privé avec les règles de routes/channels.php (comme Pusher).
     */
    public function auth($request)
    {
        return $this->verifyUserCanAccessChannel($request, Str::after((string) $request->channel_name, 'private-'));
    }

    public function validAuthenticationResponse($request, $result)
    {
        return ['auth' => 'ok'];
    }

    /**
     * @param  array<int, mixed>  $channels
     * @param  string  $event
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        if ($this->failing) {
            throw new BroadcastException('Pusher indisponible (simulation).');
        }

        $this->broadcasts[] = [
            'channels' => array_map(fn ($channel): string => (string) $channel, $channels),
            'event' => $event,
            'payload' => $payload,
        ];
    }
}
