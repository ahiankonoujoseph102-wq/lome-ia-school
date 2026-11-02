<?php

use App\Catalogue\CatalogueUpdate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue de novembre 2026 (voir App\Catalogue\CatalogueUpdate) : nouvelle formation complète en IA, nouveaux prix,
 * formation gratuite et offres d'accompagnement masquées. Aucune modification de structure ; tout en une transaction.
 *
 * Relançable sans doublon : la formation complète n'est créée que si son adresse n'existe pas (seul son groupe de
 * discussion est ajouté s'il manque). Les achats déjà faits gardent leur montant ; aucun accès ni groupe n'est retiré.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            foreach (CatalogueUpdate::PRICES as $slug => $price) {
                DB::table('formations')->where('slug', $slug)->update(['price' => $price, 'updated_at' => $now]);
            }

            DB::table('formations')->whereIn('slug', CatalogueUpdate::HIDDEN)->update(['is_active' => false, 'is_featured' => false, 'updated_at' => $now]);

            $id = DB::table('formations')->where('slug', CatalogueUpdate::COMPLETE_SLUG)->value('id');

            if ($id === null) {
                $complete = CatalogueUpdate::complete();

                $id = DB::table('formations')->insertGetId([
                    'title' => $complete['title'],
                    'slug' => CatalogueUpdate::COMPLETE_SLUG,
                    'kind' => 'course',
                    'subtitle' => $complete['subtitle'],
                    'description' => $complete['description'],
                    'price' => $complete['price'],
                    'category' => $complete['category'],
                    'formats' => json_encode(['recorded']),
                    'image' => null,
                    // En tête du catalogue.
                    'sort_order' => (int) DB::table('formations')->min('sort_order') - 10,
                    'is_featured' => true,
                    'is_active' => true,
                    'outcomes' => json_encode($complete['outcomes'], JSON_UNESCAPED_UNICODE),
                    'audience' => json_encode($complete['audience'], JSON_UNESCAPED_UNICODE),
                    'careers' => json_encode($complete['careers'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
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
        });
    }

    public function down(): void
    {
        // Données réelles (achats, groupes) : jamais effacées par un retour en arrière.
    }
};
