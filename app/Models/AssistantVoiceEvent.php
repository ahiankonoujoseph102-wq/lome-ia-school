<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Throwable;

/**
 * Mesure de la voix naturelle d'Ama, une ligne par événement (jamais de texte) : demande envoyée à Google (temps mis,
 * réussite ou cause de l'échec), passage à la voix du téléphone (cause exacte), mémoire impossible à écrire. Même
 * journée que AssistantUsage (celle de Google). Effacée après 90 jours (model:prune).
 *
 * @property int $id
 * @property string $day
 * @property string $kind
 * @property string|null $cause
 * @property string|null $source
 * @property int|null $duration_ms
 * @property int|null $chars
 * @property CarbonImmutable|null $created_at
 */
class AssistantVoiceEvent extends Model
{
    use Prunable;

    public const string GOOGLE = 'google';

    public const string FALLBACK = 'fallback';

    public const string MEMORY = 'memory';

    public const string SOURCE_SITE = 'site';

    public const string SOURCE_PREVIEW = 'preview';

    public const string SOURCE_PREPARE = 'prepare';

    public const null UPDATED_AT = null;

    protected $fillable = ['day', 'kind', 'cause', 'source', 'duration_ms', 'chars'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['duration_ms' => 'integer', 'chars' => 'integer'];
    }

    /**
     * Enregistre un événement pour la journée de Google en cours. Une mesure ne doit jamais empêcher Ama de parler.
     */
    public static function record(string $kind, ?string $cause = null, ?int $durationMs = null, ?int $chars = null, ?string $source = null): void
    {
        try {
            static::query()->create([
                'day' => AssistantUsage::today(),
                'kind' => $kind,
                'cause' => $cause,
                'source' => $source,
                'duration_ms' => $durationMs === null ? null : max(0, $durationMs),
                'chars' => $chars,
            ]);
        } catch (Throwable) {
            // Mesure perdue : sans importance pour le visiteur.
        }
    }

    /**
     * Mesures de la journée de Google en cours, pour l'admin.
     *
     * @return array{google_count: int, google_ok: int, google_avg_ms: int|null, google_max_ms: int|null, by_source: array<string, int>, causes: array<string, int>, memory_errors: int}
     */
    public static function todayStats(): array
    {
        $day = AssistantUsage::today();
        $google = static::query()->where('day', $day)->where('kind', self::GOOGLE);
        $timed = (clone $google)->whereNotNull('duration_ms');

        /** @var array<string, int> $bySource */
        $bySource = (clone $google)->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')
            // Une demande sans origine (clé vide) vient d'un visiteur.
            ->mapWithKeys(fn ($total, $source): array => [(string) $source !== '' ? (string) $source : self::SOURCE_SITE => (int) $total])->all();

        /** @var array<string, int> $causes */
        $causes = static::query()->where('day', $day)->where('kind', self::FALLBACK)
            ->selectRaw('cause, count(*) as total')->groupBy('cause')->orderByDesc('total')->pluck('total', 'cause')
            ->mapWithKeys(fn ($total, $cause): array => [(string) $cause => (int) $total])->all();

        $average = (clone $timed)->avg('duration_ms');
        $max = (clone $timed)->max('duration_ms');

        return [
            'google_count' => (clone $google)->count(),
            'google_ok' => (clone $google)->whereNull('cause')->count(),
            'google_avg_ms' => $average === null ? null : (int) round((float) $average),
            'google_max_ms' => $max === null ? null : (int) $max,
            'by_source' => $bySource,
            'causes' => $causes,
            'memory_errors' => static::query()->where('day', $day)->where('kind', self::MEMORY)->count(),
        ];
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('day', '<', now()->subDays(AssistantConversation::RETENTION_DAYS)->format('Y-m-d'));
    }
}
