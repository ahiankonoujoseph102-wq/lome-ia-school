<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Illustrations dessinées en code (SVG converti en WebP, 1600 × 1000) pour trois formations :
 * « Agents IA », « ChatGPT & Claude » et « Ebooks et livres pro ». Fichiers dans public/images/formations/
 * (sources : resources/illustrations/formations/, script : scripts/build-formation-illustrations.sh).
 *
 * Seules les formations SANS image sont remplies : une image ajoutée par Joseph dans l'admin n'est jamais
 * touchée. Aucun autre champ n'est modifié. Relancer la migration ne change plus rien. Le FormationSeeder
 * n'est pas concerné (il ne touche pas à la colonne « image »).
 */
return new class extends Migration
{
    /** @var array<string, string> adresse (slug) de la formation => fichier de public/images/formations/ */
    public const array ILLUSTRATIONS = [
        'creation-agents-ia' => 'creation-agents-ia.webp',
        'chatgpt-claude-initiation-ia' => 'chatgpt-claude-initiation-ia.webp',
        'ebooks-livres-professionnels-ia' => 'ebooks-livres-professionnels-ia.webp',
    ];

    public function up(): void
    {
        foreach (self::ILLUSTRATIONS as $slug => $file) {
            if (! is_file(public_path('images/formations/'.$file))) {
                continue;
            }

            DB::table('formations')
                ->where('slug', $slug)
                ->where(fn ($query) => $query->whereNull('image')->orWhere('image', ''))
                ->update(['image' => $file, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::ILLUSTRATIONS as $slug => $file) {
            DB::table('formations')->where('slug', $slug)->where('image', $file)->update(['image' => null]);
        }
    }
};
