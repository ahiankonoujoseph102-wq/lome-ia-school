<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Changement de clé APP_KEY sans rien casser.
 *
 * Certaines données sont chiffrées avec APP_KEY : double authentification (secret et codes de
 * récupération, chiffrés par Fortify) et copie du lien de gestion des consultations. Procédure :
 *
 * 1. APP_KEY = nouvelle clé, APP_PREVIOUS_KEYS = ancienne clé, puis déploiement ;
 * 2. php artisan app:reencrypt-data (les données sont relues avec l'ancienne clé, réécrites avec la nouvelle) ;
 * 3. APP_PREVIOUS_KEYS vidée, puis déploiement : l'ancienne clé ne sert plus à rien.
 *
 * Relançable sans risque : une donnée déjà chiffrée avec la nouvelle clé est simplement réécrite.
 */
class ReencryptData extends Command
{
    protected $signature = 'app:reencrypt-data';

    protected $description = 'Re-chiffre avec la clé APP_KEY actuelle les données chiffrées avec une ancienne clé (APP_PREVIOUS_KEYS)';

    public function handle(): int
    {
        $failures = 0;

        // Fortify chiffre le secret et les codes de récupération avec encrypt() (valeur sérialisée).
        $users = 0;

        DB::table('users')
            ->where(fn ($query) => $query->whereNotNull('two_factor_secret')->orWhereNotNull('two_factor_recovery_codes'))
            ->orderBy('id')
            ->each(function (object $user) use (&$users, &$failures): void {
                $values = [];

                foreach (['two_factor_secret', 'two_factor_recovery_codes'] as $column) {
                    if ($user->{$column} === null) {
                        continue;
                    }

                    try {
                        $values[$column] = Crypt::encrypt(Crypt::decrypt($user->{$column}));
                    } catch (DecryptException) {
                        $failures++;
                        $this->components->error("Compte n° {$user->id} : {$column} illisible (ancienne clé absente de APP_PREVIOUS_KEYS ?).");
                    }
                }

                if ($values !== []) {
                    DB::table('users')->where('id', $user->id)->update($values);
                    $users++;
                }
            });

        // Lien de gestion des consultations : cast « encrypted » (chaîne, sans sérialisation).
        $consultations = 0;

        DB::table('consultations')->whereNotNull('manage_token')->orderBy('id')->each(function (object $consultation) use (&$consultations, &$failures): void {
            try {
                $token = Crypt::decryptString($consultation->manage_token);
            } catch (DecryptException) {
                $failures++;
                $this->components->error("Consultation n° {$consultation->id} : lien de gestion illisible.");

                return;
            }

            DB::table('consultations')->where('id', $consultation->id)->update(['manage_token' => Crypt::encryptString($token)]);
            $consultations++;
        });

        $this->components->info("Double authentification re-chiffrée pour {$users} compte(s), liens de {$consultations} consultation(s) re-chiffrés.");

        if ($failures > 0) {
            $this->components->error("{$failures} donnée(s) illisible(s) : ne retirez pas l'ancienne clé de APP_PREVIOUS_KEYS avant d'avoir corrigé.");

            return self::FAILURE;
        }

        $this->components->info('Terminé : vous pouvez vider APP_PREVIOUS_KEYS et redéployer.');

        return self::SUCCESS;
    }
}
