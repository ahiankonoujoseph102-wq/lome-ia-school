<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Compteurs par jour de la voix naturelle d'Ama. Même journée que AssistantUsage : celle de Google (ses quotas
 * quotidiens recommencent à minuit, heure du Pacifique).
 *
 * @property int $id
 * @property string $day
 * @property int $spoken lectures avec la voix naturelle (mémoire comprise)
 * @property int $cached dont lectures tirées de la mémoire (aucune demande à Google)
 * @property int $google_requests demandes envoyées à Google
 * @property int $refusals refus ou échecs de Google (quota, erreur, délai dépassé, réponse sans audio)
 * @property int $fallbacks passages à la voix du téléphone
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class AssistantVoiceUsage extends Model
{
    use Prunable;

    public const array COUNTERS = ['spoken', 'cached', 'google_requests', 'refusals', 'fallbacks'];

    protected $table = 'assistant_voice_usage';

    protected $fillable = ['day', ...self::COUNTERS];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_fill_keys(self::COUNTERS, 'integer');
    }

    /**
     * Ajoute 1 aux compteurs donnés pour la journée de Google en cours.
     */
    public static function bump(string ...$counters): void
    {
        $counters = array_values(array_intersect($counters, self::COUNTERS));

        if ($counters === []) {
            return;
        }

        $day = AssistantUsage::today();
        static::query()->insertOrIgnore(['day' => $day, ...array_fill_keys(self::COUNTERS, 0), 'created_at' => now(), 'updated_at' => now()]);
        static::query()->where('day', $day)->incrementEach(array_fill_keys($counters, 1), ['updated_at' => now()]);
    }

    /**
     * Compteurs de la journée de Google en cours (0 partout s'il ne s'est rien passé).
     *
     * @return array<string, int>
     */
    public static function today(): array
    {
        $row = static::query()->where('day', AssistantUsage::today())->first();

        return array_map(fn (string $counter): int => $row === null ? 0 : (int) $row->{$counter}, array_combine(self::COUNTERS, self::COUNTERS));
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('day', '<', now()->subDays(AssistantConversation::RETENTION_DAYS)->format('Y-m-d'));
    }
}
