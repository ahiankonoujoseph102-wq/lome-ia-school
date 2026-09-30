<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * « Ce que vous allez apprendre » de la formation « Agents IA » (creation-agents-ia) suit désormais le programme
 * des 7 soirs fourni par Joseph (plus de mention « e-mail »).
 *
 * Seule la liste d'origine exacte (celle du FormationSeeder) est remplacée : une liste modifiée dans l'admin
 * n'est jamais touchée. Rien d'autre n'est modifié. Relancer la migration ne change plus rien.
 */
return new class extends Migration
{
    public const array OLD_OUTCOMES = [
        'Comprendre ce qu\'est un agent IA et ce qu\'il peut accomplir',
        'Concevoir un agent adapté à un besoin précis',
        'Donner des connaissances et des instructions fiables à votre agent',
        'Connecter votre agent à des outils (WhatsApp, e-mail, documents)',
        'Proposer la création d\'agents IA comme service aux entreprises',
    ];

    public const array NEW_OUTCOMES = [
        'Comprendre ce qu\'est un agent IA et choisir votre projet d\'agent',
        'Écrire les instructions d\'un agent : rôle, ton, règles et limites',
        'Créer un agent avec Gemini (Gems) et lui donner des documents',
        'Créer un agent avec Claude (Projets), le tester et le corriger',
        'Créer un agent qui répond aux clients sur WhatsApp Business',
        'Proposer vos services d\'agents IA : trouver des clients et fixer vos prix',
        'Réaliser un projet final et présenter votre agent',
    ];

    public function up(): void
    {
        $formation = DB::table('formations')->where('slug', 'creation-agents-ia')->first(['id', 'outcomes']);

        if ($formation === null || json_decode((string) $formation->outcomes, true) !== self::OLD_OUTCOMES) {
            return;
        }

        DB::table('formations')->where('id', $formation->id)->update([
            'outcomes' => json_encode(self::NEW_OUTCOMES, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
