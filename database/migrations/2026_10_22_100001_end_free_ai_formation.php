<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La formation gratuite du samedi 10 octobre 2026 (« formation-gratuite-ia ») est terminée : son interrupteur
 * « Formation terminée » est allumé. Rien d'autre n'est touché : ni ses inscrits, ni ses options, ni son groupe,
 * ni le texte ou la destination du bouton (vides = « Découvrir l'accompagnement » vers /accompagnement).
 *
 * Relançable sans effet : seule une formation gratuite encore « non terminée » est modifiée ; adresse absente = rien.
 * Le FormationSeeder n'est jamais relancé.
 */
return new class extends Migration
{
    public const string SLUG = 'formation-gratuite-ia';

    public function up(): void
    {
        DB::table('formations')
            ->where('slug', self::SLUG)
            ->where('price', 0)
            ->where('free_ended', false)
            ->update(['free_ended' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Rien à défaire automatiquement : l'interrupteur se règle dans l'admin.
    }
};
