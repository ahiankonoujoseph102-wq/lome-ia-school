<?php

namespace App\Packs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Création des trois offres d'accompagnement et du Groupe VIP (octobre 2026), en brouillon (masquées) :
 * Joseph les vérifie puis les publie dans l'admin (« Formations et prix »).
 *
 * Titres, prix, descriptions et formations incluses fournis par Joseph (descriptions reprises mot pour mot).
 * Les formations incluses sont retrouvées dans la base par leur titre réel (mots du titre, sans tenir compte
 * des majuscules, des accents ni de la ponctuation) ; une formation introuvable ou ambiguë n'est jamais
 * inventée : elle reste seulement citée dans la description et apparaît dans le compte rendu.
 *
 * Relançable sans doublon : tout est écrit dans une seule transaction ; une offre (ou le Groupe VIP) dont
 * l'adresse existe déjà n'est jamais recréée ni modifiée (sa liste se modifie dans l'admin). Seul le groupe
 * de discussion du Groupe VIP est ajouté s'il manque. Le FormationSeeder n'est jamais relancé.
 */
class PackSetup
{
    public const string VIP_SLUG = 'groupe-vip';

    /**
     * Formations citées par Joseph : mots qui doivent tous figurer dans le titre réel.
     *
     * @var array<string, array{label: string, words: list<string>}>
     */
    public const array FORMATIONS = [
        'chatgpt' => ['label' => 'ChatGPT & Claude', 'words' => ['chatgpt', 'claude']],
        'posters' => ['label' => 'Affiches pro', 'words' => ['affiches']],
        'ads' => ['label' => 'Vidéos publicitaires', 'words' => ['videos', 'publicitaires']],
        'stories' => ['label' => 'Vidéos d\'histoire', 'words' => ['videos', 'histoire']],
        'showcase' => ['label' => 'Site vitrine', 'words' => ['site vitrine']],
        'database' => ['label' => 'Sites avec base de données', 'words' => ['sites', 'base de donnees']],
        'apps' => ['label' => 'Applications et logiciels', 'words' => ['applications', 'logiciels']],
        'agents' => ['label' => 'Agents IA', 'words' => ['agents ia']],
        'automation' => ['label' => 'Automatisation', 'words' => ['automatisation']],
        'saas' => ['label' => 'SaaS', 'words' => ['saas']],
    ];

    /**
     * Les trois offres, dans l'ordre (niveau 1, 2, 3).
     *
     * @var list<array{slug: string, title: string, price: int, category: string, formats: list<string>, includes: list<string>, description: string}>
     */
    public const array PACKS = [
        [
            'slug' => 'accompagnement-ia-niveau-1',
            'title' => 'Accompagnement IA, niveau 1 : Créateur de contenu',
            'price' => 10000,
            'category' => 'creative',
            'formats' => ['recorded'],
            'includes' => ['chatgpt', 'posters', 'ads', 'stories'],
            'description' => 'Apprenez à créer des affiches et des vidéos professionnelles avec l\'IA. Initiation complète à l\'intelligence artificielle, affiches professionnelles, vidéos publicitaires, vidéos d\'histoire, création d\'une chaîne YouTube et conditions de monétisation, NotebookLM pour apprendre n\'importe quelle compétence. Formations déjà enregistrées : vous y accédez dès votre paiement et vous avancez à votre rythme. Chaque formation est accompagnée d\'un document, d\'un résumé et d\'un exercice. Accès au groupe VIP et accompagnement à vie. Attestation de participation à la fin.',
        ],
        [
            'slug' => 'accompagnement-ia-niveau-2',
            'title' => 'Accompagnement IA, niveau 2 : Créateur de sites et d\'applications',
            'price' => 30000,
            'category' => 'web',
            'formats' => ['recorded'],
            'includes' => ['chatgpt', 'posters', 'ads', 'stories', 'showcase', 'database', 'apps'],
            'description' => 'Tout le niveau 1, et en plus : créez des sites web et des applications avec l\'IA, sans savoir coder. Sites vitrines professionnels, sites avec base de données, applications. Vous apprenez aussi comment utiliser l\'IA pour multiplier votre productivité et vos résultats par 10 : comment l\'intégrer dans votre activité et quels outils utiliser. Formations déjà enregistrées : vous y accédez dès votre paiement et vous avancez à votre rythme. Chaque formation est accompagnée d\'un document, d\'un résumé et d\'un exercice. Accès au groupe VIP et accompagnement à vie. Attestation de participation à la fin.',
        ],
        [
            'slug' => 'accompagnement-ia-niveau-3',
            'title' => 'Accompagnement IA, niveau 3 : Parcours complet avec suivi personnel',
            'price' => 75000,
            'category' => 'automation',
            'formats' => ['recorded', 'live', 'onsite'],
            'includes' => ['chatgpt', 'posters', 'ads', 'stories', 'showcase', 'database', 'apps', 'agents', 'automation', 'saas'],
            'description' => 'Tout le niveau 1 et le niveau 2, et en plus : agents IA, automatisation et création de SaaS, jusqu\'au niveau avancé. Vous recevez tous les enregistrements, et en plus, Coach Joseph refait la formation en direct, spécialement pour votre groupe, à partir du lundi 12 octobre 2026, en présentiel à Lomé et en ligne. Il vous accompagne personnellement dans la réalisation de votre propre projet. Chaque formation est accompagnée d\'un document, d\'un résumé et d\'un exercice. Accès au groupe VIP et accompagnement à vie. Attestation de participation à la fin.',
        ],
    ];

    /**
     * Crée ce qui manque. Compte rendu : pour chaque offre, créée ou déjà là, formations rattachées et introuvables.
     *
     * @return array{vip: string, packs: list<array{slug: string, title: string, status: string, attached: list<string>, missing: list<string>}>}
     */
    public static function run(): array
    {
        return DB::transaction(function (): array {
            [$vipId, $vipStatus] = self::vipGroup();
            $matches = self::findFormations();
            $packs = [];

            foreach (self::PACKS as $definition) {
                $packs[] = self::pack($definition, $matches, $vipId);
            }

            return ['vip' => $vipStatus, 'packs' => $packs];
        });
    }

    /**
     * Groupe VIP : « formation » de type groupe, masquée, gratuite, avec son groupe de discussion.
     *
     * @return array{0: int, 1: string}
     */
    private static function vipGroup(): array
    {
        $now = now();
        $id = DB::table('formations')->where('slug', self::VIP_SLUG)->value('id');
        $status = 'déjà présent';

        if ($id === null) {
            $id = DB::table('formations')->insertGetId([
                'title' => 'Groupe VIP',
                'slug' => self::VIP_SLUG,
                'kind' => 'group',
                'subtitle' => null,
                'description' => 'Groupe de discussion réservé aux acheteurs des offres d\'accompagnement.',
                'price' => 0,
                'category' => 'ai-basics',
                'formats' => json_encode(['recorded']),
                'sort_order' => (int) DB::table('formations')->max('sort_order') + 1,
                'is_featured' => false,
                'is_active' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $status = 'créé';
        }

        if (! DB::table('chat_groups')->where('formation_id', $id)->exists()) {
            DB::table('chat_groups')->insert([
                'formation_id' => $id,
                'rules' => config('chat.default_rules'),
                'rules_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return [(int) $id, $status];
    }

    /**
     * @param  array{slug: string, title: string, price: int, category: string, formats: list<string>, includes: list<string>, description: string}  $definition
     * @param  array<string, array{id: int, title: string}|null>  $matches
     * @return array{slug: string, title: string, status: string, attached: list<string>, missing: list<string>}
     */
    private static function pack(array $definition, array $matches, int $vipId): array
    {
        $missing = array_values(array_map(
            fn (string $key): string => self::FORMATIONS[$key]['label'],
            array_filter($definition['includes'], fn (string $key): bool => $matches[$key] === null),
        ));

        $existing = DB::table('formations')->where('slug', $definition['slug'])->value('id');

        if ($existing !== null) {
            // Déjà créée : jamais modifiée (la liste se règle dans l'admin). Compte rendu de la liste actuelle.
            return [
                'slug' => $definition['slug'],
                'title' => (string) DB::table('formations')->where('id', $existing)->value('title'),
                'status' => 'déjà présente, non modifiée',
                'attached' => array_values(DB::table('formation_pack_items')
                    ->join('formations', 'formations.id', '=', 'formation_pack_items.formation_id')
                    ->where('formation_pack_items.pack_id', $existing)
                    ->orderBy('formation_pack_items.position')
                    ->pluck('formations.title')
                    ->map(fn ($title): string => (string) $title)
                    ->all()),
                'missing' => $missing,
            ];
        }

        $now = now();
        $packId = DB::table('formations')->insertGetId([
            'title' => $definition['title'],
            'slug' => $definition['slug'],
            'kind' => 'pack',
            'subtitle' => null,
            'description' => $definition['description'],
            'price' => $definition['price'],
            'category' => $definition['category'],
            'formats' => json_encode($definition['formats']),
            'sort_order' => (int) DB::table('formations')->max('sort_order') + 1,
            'is_featured' => false,
            'is_active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $attached = [];
        $position = 0;

        foreach ($definition['includes'] as $key) {
            if ($matches[$key] === null) {
                continue;
            }

            DB::table('formation_pack_items')->insert([
                'pack_id' => $packId,
                'formation_id' => $matches[$key]['id'],
                'position' => ++$position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $attached[] = $matches[$key]['title'];
        }

        DB::table('formation_pack_items')->insert([
            'pack_id' => $packId,
            'formation_id' => $vipId,
            'position' => ++$position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attached[] = 'Groupe VIP';

        return ['slug' => $definition['slug'], 'title' => $definition['title'], 'status' => 'créée (masquée)', 'attached' => $attached, 'missing' => $missing];
    }

    /**
     * Formation réelle de chaque nom cité, ou null si aucune ou plusieurs correspondent.
     *
     * @return array<string, array{id: int, title: string}|null>
     */
    public static function findFormations(): array
    {
        $courses = DB::table('formations')
            ->where('kind', 'course')
            ->orderBy('id')
            ->get(['id', 'title'])
            ->map(fn (object $row): array => ['id' => (int) $row->id, 'title' => (string) $row->title, 'words' => self::normalize((string) $row->title)]);

        $matches = [];

        foreach (self::FORMATIONS as $key => $formation) {
            $found = $courses->filter(fn (array $course): bool => collect($formation['words'])
                ->every(fn (string $word): bool => str_contains($course['words'], self::normalize($word))));

            $matches[$key] = $found->count() === 1 ? ['id' => $found->first()['id'], 'title' => $found->first()['title']] : null;
        }

        return $matches;
    }

    /**
     * « Création de vidéos d'histoire avec l'IA » → «  creation de videos d histoire avec l ia  ».
     */
    private static function normalize(string $text): string
    {
        $words = trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))));

        return ' '.$words.' ';
    }
}
