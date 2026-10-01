<?php

use App\Support\FormationIllustrations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Illustration (robot) de « Agents IA » : posée seulement à partir du 8 octobre 2026 (heure de Lomé), quand le
 * direct est terminé, et seulement si la formation n'a toujours pas d'image.
 *
 * Une migration ne s'exécute qu'une fois : si le site est mis à jour avant cette date, elle ne fait rien. C'est
 * alors la tâche planifiée « app:apply-formation-illustrations » (chaque nuit) qui pose l'image dès le 8 octobre.
 * Les deux passent par FormationIllustrations::applyAgentsWhenDue(), qui ne pose l'image qu'une seule fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        FormationIllustrations::applyAgentsWhenDue();
    }

    public function down(): void
    {
        DB::table('formations')
            ->where('slug', FormationIllustrations::AGENTS_SLUG)
            ->where('image', FormationIllustrations::file(FormationIllustrations::AGENTS_SLUG))
            ->update(['image' => null]);
    }
};
