<?php

namespace App\Support;

use App\Settings\SiteSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Illustrations des formations dessinées en code (resources/illustrations/formations/{slug}.svg, converties en WebP
 * dans public/images/formations/{slug}.webp ; script : scripts/build-formation-illustrations.sh).
 *
 * Elles ne remplissent que les formations SANS image : une image ajoutée dans l'admin n'est jamais remplacée.
 *
 * « Agents IA » est à part : tant que le direct n'est pas terminé (jusqu'au 7 octobre 2026 inclus), cette formation
 * garde son visuel généré et son aperçu de partage avec les dates ; son illustration n'est posée qu'à partir du
 * 8 octobre 2026 (heure de Lomé), une seule fois (voir applyAgentsWhenDue()).
 */
final class FormationIllustrations
{
    public const string AGENTS_SLUG = 'creation-agents-ia';

    /** Premier jour où l'illustration d'« Agents IA » peut être posée (fuseau Africa/Lome). */
    public const string AGENTS_FROM = '2026-10-08 00:00:00';

    /** Réglage qui garde en mémoire que le choix concernant « Agents IA » a été fait (jamais reposée ensuite). */
    public const string AGENTS_DONE_KEY = 'system.agents_illustration_done';

    /**
     * Adresse (slug) des formations illustrées dès la mise en ligne, sans « Agents IA ».
     *
     * @var list<string>
     */
    public const array NOW = [
        'chatgpt-claude-initiation-ia',
        'creation-affiches-pro-ia',
        'videos-publicitaires-ia',
        'videos-histoire-ia',
        'videos-films-ia',
        'chansons-personnalisees-ia',
        'ebooks-livres-professionnels-ia',
        'site-vitrine-web-ia',
        'sites-web-base-de-donnees-projets-avances',
        'applications-logiciels-ia',
        'creation-saas-ia',
        'automatisation-ia',
        'creer-formations-apprendre-competences-ia',
        'missions-upwork-competences-ia',
        'contenu-tiktok-facebook-youtube-videos-ia',
        'e-commerce-produits-digitaux-ia',
    ];

    /**
     * Toutes les formations illustrées (la liste immédiate et « Agents IA »).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [...self::NOW, self::AGENTS_SLUG];
    }

    public static function file(string $slug): string
    {
        return $slug.'.webp';
    }

    /**
     * Pose l'illustration sur les formations sans image (null ou vide). Renvoie le nombre de formations modifiées.
     *
     * @param  list<string>  $slugs
     */
    public static function fill(array $slugs): int
    {
        $count = 0;

        foreach ($slugs as $slug) {
            if (! is_file(public_path('images/formations/'.self::file($slug)))) {
                continue;
            }

            $count += DB::table('formations')
                ->where('slug', $slug)
                ->where(fn ($query) => $query->whereNull('image')->orWhere('image', ''))
                ->update(['image' => self::file($slug), 'updated_at' => now()]);
        }

        return $count;
    }

    public static function agentsAreDue(): bool
    {
        return now('Africa/Lome')->gte(Carbon::parse(self::AGENTS_FROM, 'Africa/Lome'));
    }

    /**
     * À partir du 8 octobre 2026 (Lomé), pose l'illustration d'« Agents IA » si la formation n'a toujours pas d'image,
     * puis note que c'est fait : si l'image est retirée plus tard dans l'admin, elle n'est jamais reposée.
     * Avant cette date, ou une fois fait, ne fait rien. Renvoie vrai si l'illustration vient d'être posée.
     */
    public static function applyAgentsWhenDue(): bool
    {
        if (! self::agentsAreDue() || SiteSettings::get(self::AGENTS_DONE_KEY) === true) {
            return false;
        }

        $applied = self::fill([self::AGENTS_SLUG]) > 0;

        SiteSettings::save([self::AGENTS_DONE_KEY => true]);

        return $applied;
    }
}
