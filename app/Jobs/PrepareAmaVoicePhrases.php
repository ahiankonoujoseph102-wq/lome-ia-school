<?php

namespace App\Jobs;

use App\Assistant\NaturalVoice;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Voix naturelle d'Ama : prépare en arrière-plan les phrases écrites à l'avance (accueil du mode appel en « Bonjour » et
 * « Bonsoir », au revoir, demande de répéter, messages de limite, « C'est transmis ! ») pour la voix et le modèle
 * choisis. Chacune est fabriquée une seule fois par Google, puis gardée en mémoire : les visiteurs l'entendent ensuite
 * sans aucune demande à Google.
 *
 * Lancée quand l'admin enregistre les réglages de l'assistante et chaque heure par le planificateur (sans effet quand
 * tout est prêt). Jamais plus d'une phrase quand il ne reste que deux demandes libres dans la minute : la dernière est
 * toujours laissée aux visiteurs. Ce qui reste est repris une minute plus tard.
 */
class PrepareAmaVoicePhrases implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 600;

    /**
     * Phrases fabriquées au plus par passage (une minute de file d'attente).
     */
    public const int PER_RUN = 3;

    public function handle(NaturalVoice $voice): void
    {
        $missing = 0;

        for ($i = 0; $i < self::PER_RUN; $i++) {
            $before = $voice->missingPreparedTexts();
            $missing = $voice->prepareNext();

            // Arrêt : voix éteinte, quota du jour épuisé, refus de Google ; ou tout est prêt.
            if ($missing === null || $missing === 0) {
                return;
            }

            // Rien de fabriqué (minute presque pleine) : on reprend plus tard.
            if ($missing >= count($before)) {
                break;
            }
        }

        if ($missing > 0) {
            self::dispatch()->delay(now()->addMinute());
        }
    }
}
