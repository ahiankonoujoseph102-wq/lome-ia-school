<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Throwable;

/**
 * Mesure de la voix naturelle d'Ama, une ligne par événement (jamais de texte) : demande envoyée à Google (temps mis,
 * réussite ou cause de l'échec), passage à la voix du téléphone (cause exacte), réponse affichée sans voix (cause
 * exacte), mémoire impossible à écrire. Même
 * journée que AssistantUsage (celle de Google). Effacée après 90 jours (model:prune).
 *
 * @property int $id
 * @property string $day
 * @property string $kind
 * @property string|null $provider fournisseur de la voix (gemini, cloudflare) ; vide pour les anciennes mesures (Google)
 * @property string|null $cause
 * @property string|null $source
 * @property int|null $duration_ms
 * @property int|null $chars
 * @property CarbonImmutable|null $created_at
 */
class AssistantVoiceEvent extends Model
{
    use Prunable;

    // Demande envoyée au fournisseur de la voix (Google ou Cloudflare, voir « provider ») ; nom gardé pour les
    // mesures déjà enregistrées.
    public const string GOOGLE = 'google';

    public const string FALLBACK = 'fallback';

    // Partie de réponse que la voix de Gemini n'a pas dite et qui est restée affichée sans voix (voix du téléphone en
    // secours éteinte, ou voix de Gemini déjà entendue dans cette réponse).
    public const string SILENT = 'silent';

    public const string MEMORY = 'memory';

    public const string SOURCE_SITE = 'site';

    public const string SOURCE_PREVIEW = 'preview';

    public const string SOURCE_PREPARE = 'prepare';

    public const null UPDATED_AT = null;

    protected $fillable = ['day', 'kind', 'provider', 'cause', 'source', 'duration_ms', 'chars'];

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
    public static function record(string $kind, ?string $cause = null, ?int $durationMs = null, ?int $chars = null, ?string $source = null, ?string $provider = null): void
    {
        try {
            static::query()->create([
                'day' => AssistantUsage::today(),
                'kind' => $kind,
                'provider' => $provider,
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
     * Mesures de la journée de Google en cours, pour l'admin. Avec $provider, les demandes et leurs temps sont ceux de
     * ce fournisseur seulement (une mesure sans fournisseur, enregistrée avant Cloudflare, vient de Google).
     *
     * @return array{google_count: int, google_ok: int, google_avg_ms: int|null, google_max_ms: int|null, by_source: array<string, int>, causes: array<string, int>, silent_causes: array<string, int>, silent: int, memory_errors: int}
     */
    public static function todayStats(?string $provider = null): array
    {
        $day = AssistantUsage::today();
        $google = static::query()->where('day', $day)->where('kind', self::GOOGLE)
            ->when($provider === 'gemini', fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('provider')->orWhere('provider', 'gemini')))
            ->when($provider !== null && $provider !== 'gemini', fn (Builder $query) => $query->where('provider', $provider));
        $timed = (clone $google)->whereNotNull('duration_ms');

        /** @var array<string, int> $bySource */
        $bySource = (clone $google)->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')
            // Une demande sans origine (clé vide) vient d'un visiteur.
            ->mapWithKeys(fn ($total, $source): array => [(string) $source !== '' ? (string) $source : self::SOURCE_SITE => (int) $total])->all();

        $causes = self::causesOf($day, self::FALLBACK);
        $silentCauses = self::causesOf($day, self::SILENT);

        $average = (clone $timed)->avg('duration_ms');
        $max = (clone $timed)->max('duration_ms');

        return [
            'google_count' => (clone $google)->count(),
            'google_ok' => (clone $google)->whereNull('cause')->count(),
            'google_avg_ms' => $average === null ? null : (int) round((float) $average),
            'google_max_ms' => $max === null ? null : (int) $max,
            'by_source' => $bySource,
            'causes' => $causes,
            'silent_causes' => $silentCauses,
            'silent' => array_sum($silentCauses),
            'memory_errors' => static::query()->where('day', $day)->where('kind', self::MEMORY)->count(),
        ];
    }

    /**
     * Nombre d'événements d'un type par cause, pour une journée, du plus fréquent au moins fréquent.
     *
     * @return array<string, int>
     */
    private static function causesOf(string $day, string $kind): array
    {
        /** @var array<string, int> $causes */
        $causes = static::query()->where('day', $day)->where('kind', $kind)
            ->selectRaw('cause, count(*) as total')->groupBy('cause')->orderByDesc('total')->pluck('total', 'cause')
            ->mapWithKeys(fn ($total, $cause): array => [(string) $cause => (int) $total])->all();

        return $causes;
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('day', '<', now()->subDays(AssistantConversation::RETENTION_DAYS)->format('Y-m-d'));
    }
}
