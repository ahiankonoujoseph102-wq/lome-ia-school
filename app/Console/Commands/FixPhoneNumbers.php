<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;

/**
 * Répare les numéros WhatsApp enregistrés avec l'ancienne normalisation, qui retirait le 0 initial
 * (Bénin, Burkina Faso, Côte d'Ivoire…), et complète les anciens numéros béninois à 8 chiffres.
 */
class FixPhoneNumbers extends Command
{
    protected $signature = 'app:fix-phone-numbers {--dry-run : Affiche les corrections sans les enregistrer}';

    protected $description = 'Répare les numéros WhatsApp mal formés (0 initial perdu, ancien format du Bénin)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;
        $conflicts = 0;

        User::query()->whereNotNull('phone')->orderBy('id')->each(function (User $user) use ($dryRun, &$fixed, &$conflicts): void {
            $repaired = PhoneNumber::repair((string) $user->phone);

            if ($repaired === null) {
                return;
            }

            if (User::query()->where('phone', $repaired)->whereKeyNot($user->id)->exists()) {
                $conflicts++;
                $this->components->warn("{$user->email} : {$user->phone} devient {$repaired}, déjà utilisé par un autre compte, non modifié.");

                return;
            }

            $this->components->info("{$user->email} : {$user->phone} devient {$repaired}");
            $fixed++;

            if (! $dryRun) {
                $user->forceFill(['phone' => $repaired])->save();
            }
        });

        $this->components->info(($dryRun ? 'Simulation : ' : '').$fixed.' numéro(s) corrigé(s), '.$conflicts.' conflit(s).');

        return self::SUCCESS;
    }
}
