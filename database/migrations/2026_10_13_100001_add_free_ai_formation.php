<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Formation gratuite du samedi 10 octobre 2026 (« formation-gratuite-ia »), créée en brouillon (masquée) :
 * Joseph la relit, puis la publie dans l'admin. Textes, date, lieu et options fournis par Joseph.
 *
 * Le FormationSeeder n'est pas relancé. Reprise sans doublon : tout est écrit dans une seule transaction (la
 * formation, ses options et son groupe, ou rien) ; si l'adresse existe déjà, la formation et ses options ne sont
 * jamais recréées ni modifiées (seul son groupe de discussion est ajouté s'il manque). Relançable sans effet.
 */
return new class extends Migration
{
    public const string SLUG = 'formation-gratuite-ia';

    public function up(): void
    {
        DB::transaction(function (): void {
            $existing = DB::table('formations')->where('slug', self::SLUG)->value('id');

            if ($existing !== null) {
                $this->addChatGroup((int) $existing);

                return;
            }

            $this->createFormation();
        });
    }

    private function createFormation(): void
    {
        $now = now();

        $formationId = DB::table('formations')->insertGetId([
            'title' => 'Formation gratuite : utiliser l\'IA au quotidien',
            'slug' => self::SLUG,
            'subtitle' => 'Samedi 10 octobre 2026, à Lomé ou en ligne sur Google Meet. ChatGPT, Claude et Perplexity, pour tous les niveaux.',
            'description' => 'Une formation gratuite pour apprendre à utiliser ChatGPT, Claude et Perplexity au quotidien : avancer plus vite sur vos tâches, créer du contenu, répondre à vos clients et vendre. Elle est ouverte à tous les niveaux. Au moment de l\'inscription, vous choisissez votre option : en présentiel à Lomé ou en ligne sur Google Meet, en journée ou le soir (version courte).',
            'price' => 0,
            'category' => 'ai-basics',
            'formats' => json_encode(['onsite', 'live']),
            'image' => null,
            'sort_order' => (int) DB::table('formations')->max('sort_order') + 1,
            'is_featured' => false,
            'is_active' => false,
            'outcomes' => json_encode([
                'Utiliser ChatGPT, Claude et Perplexity pour avancer plus vite sur vos tâches',
                'Créer du contenu avec l\'aide de l\'IA',
                'Répondre à vos clients avec l\'aide de l\'IA',
                'Vous servir de l\'IA pour vendre',
            ], JSON_UNESCAPED_UNICODE),
            'audience' => json_encode([
                'Tous les niveaux : que vous découvriez l\'IA ou que vous l\'utilisiez déjà',
            ], JSON_UNESCAPED_UNICODE),
            'bonuses' => null,
            'event_date' => '2026-10-10',
            'event_location' => 'Adidogomé, Lycée moderne 2, Lomé',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $options = [
            ['Présentiel journée', '9 h à 16 h (pause de 13 h à 14 h)', 'onsite', 20],
            ['Présentiel soir', '18 h à 20 h (version courte, l\'essentiel)', 'onsite', 20],
            ['En ligne journée', '9 h à 16 h', 'online', 50],
            ['En ligne soir', '18 h à 20 h (version courte)', 'online', 50],
        ];

        foreach ($options as $position => [$label, $schedule, $mode, $capacity]) {
            DB::table('free_registration_options')->insert([
                'formation_id' => $formationId,
                'label' => $label,
                'schedule' => $schedule,
                'mode' => $mode,
                'capacity' => $capacity,
                'position' => $position + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->addChatGroup($formationId);
    }

    /**
     * Groupe de discussion des inscrits (créé comme pour toute nouvelle formation), seulement s'il manque.
     */
    private function addChatGroup(int $formationId): void
    {
        if (DB::table('chat_groups')->where('formation_id', $formationId)->exists()) {
            return;
        }

        $now = now();

        DB::table('chat_groups')->insert([
            'formation_id' => $formationId,
            'rules' => config('chat.default_rules'),
            'rules_updated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Données réelles (inscrits, groupe) : jamais effacées par un retour en arrière.
    }
};
