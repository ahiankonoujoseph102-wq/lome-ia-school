<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Minutes d'audio fabriquées par jour pour un fournisseur de la voix naturelle d'Ama (aujourd'hui : Cloudflare, dont
 * l'offre gratuite se compte en neurones par minute d'audio). Journée de Cloudflare : 0 h 00 UTC, soit 0 h 00 à Lomé.
 * Les phrases resservies de la mémoire ne sont jamais comptées (aucune demande). Effacée après 90 jours (model:prune).
 *
 * @property int $id
 * @property string $provider
 * @property string $day
 * @property int $requests
 * @property int $audio_ms
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class AssistantVoiceQuota extends Model
{
    use Prunable;

    protected $table = 'assistant_voice_quota';

    protected $fillable = ['provider', 'day', 'requests', 'audio_ms'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requests' => 'integer', 'audio_ms' => 'integer'];
    }

    /**
     * Journée en cours du fournisseur (date UTC : Cloudflare remet ses compteurs à zéro à 0 h 00 UTC).
     */
    public static function today(): string
    {
        return now()->utc()->format('Y-m-d');
    }

    /**
     * Début de la prochaine journée, à l'heure de l'application (Lomé).
     */
    public static function nextReset(): CarbonInterface
    {
        return now()->utc()->addDay()->startOfDay()->setTimezone((string) config('app.timezone'));
    }

    /**
     * Ajoute une demande réussie et sa durée d'audio à la journée en cours.
     */
    public static function add(string $provider, int $audioMs): void
    {
        $day = self::today();
        static::query()->insertOrIgnore(['provider' => $provider, 'day' => $day, 'requests' => 0, 'audio_ms' => 0, 'created_at' => now(), 'updated_at' => now()]);
        static::query()->where('provider', $provider)->where('day', $day)->incrementEach(['requests' => 1, 'audio_ms' => max(0, $audioMs)], ['updated_at' => now()]);
    }

    /**
     * Audio déjà fabriqué aujourd'hui (millisecondes).
     */
    public static function usedMs(string $provider): int
    {
        return (int) static::query()->where('provider', $provider)->where('day', self::today())->value('audio_ms');
    }

    /**
     * Demandes réussies aujourd'hui.
     */
    public static function requestsToday(string $provider): int
    {
        return (int) static::query()->where('provider', $provider)->where('day', self::today())->value('requests');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('day', '<', now()->subDays(AssistantConversation::RETENTION_DAYS)->format('Y-m-d'));
    }
}
