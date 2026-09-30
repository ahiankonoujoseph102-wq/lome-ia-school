<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Nombre de demandes envoyées à Gemini par jour. La journée est celle de Google : ses quotas quotidiens
 * recommencent à minuit, heure du Pacifique (7 h ou 8 h du matin à Lomé selon l'heure d'été américaine).
 *
 * @property int $id
 * @property string $day
 * @property int $requests
 * @property int $failures
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class AssistantUsage extends Model
{
    use Prunable;

    public const string GOOGLE_TIMEZONE = 'America/Los_Angeles';

    protected $table = 'assistant_usage';

    protected $fillable = ['day', 'requests', 'failures'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requests' => 'integer', 'failures' => 'integer'];
    }

    /**
     * Journée de Google en cours (AAAA-MM-JJ).
     */
    public static function today(): string
    {
        return now()->setTimezone(self::GOOGLE_TIMEZONE)->format('Y-m-d');
    }

    /**
     * Demandes déjà envoyées aujourd'hui (journée de Google).
     */
    public static function requestsToday(): int
    {
        return (int) static::query()->where('day', self::today())->value('requests');
    }

    /**
     * Heure de Lomé à laquelle la journée de Google recommence (prochain minuit du Pacifique).
     */
    public static function nextReset(): CarbonInterface
    {
        return now()->setTimezone(self::GOOGLE_TIMEZONE)->addDay()->startOfDay()->setTimezone((string) config('app.timezone'));
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('day', '<', now()->subDays(AssistantConversation::RETENTION_DAYS)->format('Y-m-d'));
    }
}
