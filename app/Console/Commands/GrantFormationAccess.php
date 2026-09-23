<?php

namespace App\Console\Commands;

use App\Enums\EnrollmentSource;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Donne (ou retire) manuellement l'accès d'une formation à un élève :
 * utile pour les tests et pour les paiements reçus en dehors de la plateforme.
 */
class GrantFormationAccess extends Command
{
    protected $signature = 'app:grant-access
        {email : Adresse e-mail de l\'élève}
        {formation : Slug de la formation (ex. chatgpt-claude-initiation-ia)}
        {--revoke : Retirer l\'accès au lieu de le donner}';

    protected $description = 'Donner ou retirer manuellement l\'accès d\'une formation à un élève';

    public function handle(): int
    {
        $user = User::where('email', (string) $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('Aucun compte ne correspond à cette adresse e-mail.');

            return self::FAILURE;
        }

        $formation = Formation::where('slug', (string) $this->argument('formation'))->first();

        if (! $formation) {
            $this->components->error('Aucune formation ne correspond à ce slug.');

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $deleted = $user->enrollments()->where('formation_id', $formation->id)->delete();
            $deleted
                ? $this->components->info("Accès à « {$formation->title} » retiré pour {$user->email}.")
                : $this->components->warn("{$user->email} n'avait pas accès à « {$formation->title} ».");

            return self::SUCCESS;
        }

        if ($user->isEnrolledIn($formation)) {
            $this->components->warn("{$user->email} a déjà accès à « {$formation->title} ».");

            return self::SUCCESS;
        }

        $user->enroll($formation, EnrollmentSource::Admin);
        $this->components->info("Accès à « {$formation->title} » donné à {$user->email}.");

        return self::SUCCESS;
    }
}
