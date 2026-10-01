<?php

use App\Support\FormationIllustrations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Illustrations dessinées en code (SVG converti en WebP, 1600 × 1000) pour 16 formations, fichiers dans
 * public/images/formations/ (voir App\Support\FormationIllustrations). « Agents IA » n'y est pas : voir la
 * migration 2026_10_11_100000_add_agents_ia_illustration.
 *
 * Seules les formations SANS image sont remplies : une image ajoutée par Joseph dans l'admin n'est jamais
 * touchée. Aucun autre champ n'est modifié. Relancer la migration ne change plus rien. Le FormationSeeder
 * n'est pas concerné (il ne touche pas à la colonne « image »).
 */
return new class extends Migration
{
    public function up(): void
    {
        FormationIllustrations::fill(FormationIllustrations::NOW);
    }

    public function down(): void
    {
        foreach (FormationIllustrations::NOW as $slug) {
            DB::table('formations')->where('slug', $slug)->where('image', FormationIllustrations::file($slug))->update(['image' => null]);
        }
    }
};
