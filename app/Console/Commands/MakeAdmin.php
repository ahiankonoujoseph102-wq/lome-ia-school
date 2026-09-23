<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Crée un compte administrateur ou promeut un compte existant.
 * Aucun mot de passe n'est écrit dans le code : il est saisi au moment de la commande.
 */
class MakeAdmin extends Command
{
    protected $signature = 'app:make-admin
        {email : Adresse e-mail du compte}
        {--name= : Nom complet (création uniquement)}
        {--phone= : Numéro WhatsApp au format international (création uniquement)}';

    protected $description = 'Créer un compte administrateur ou donner le rôle admin à un compte existant';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->components->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            if ($user->isAdmin()) {
                $this->components->info("{$user->email} est déjà administrateur.");

                return self::SUCCESS;
            }

            $user->forceFill(['role' => UserRole::Admin])->save();
            $this->components->info("{$user->email} est maintenant administrateur.");

            return self::SUCCESS;
        }

        return $this->createAdmin($email);
    }

    private function createAdmin(string $email): int
    {
        $name = (string) ($this->option('name') ?: text(label: 'Nom complet', required: true));

        $phoneInput = (string) ($this->option('phone') ?? '');
        $phone = $phoneInput === '' ? null : PhoneNumber::normalize($phoneInput);

        if ($phoneInput !== '' && $phone === null) {
            $this->components->error('Numéro WhatsApp invalide. Exemple : +22890123456');

            return self::FAILURE;
        }

        if ($phone !== null && Validator::make(['phone' => $phone], ['phone' => Rule::unique(User::class)])->fails()) {
            $this->components->error('Ce numéro WhatsApp est déjà utilisé par un autre compte.');

            return self::FAILURE;
        }

        $generated = false;

        if ($this->input->isInteractive()) {
            $plainPassword = password(
                label: 'Mot de passe (8 caractères minimum)',
                required: true,
                validate: fn (string $value): ?string => strlen($value) < 8 ? 'Le mot de passe doit contenir au moins 8 caractères.' : null,
            );
        } else {
            $plainPassword = Str::password(16);
            $generated = true;
        }

        $user = new User(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => $plainPassword]);
        $user->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();

        $this->components->info("Compte administrateur créé pour {$email}.");

        if ($generated) {
            $this->components->warn("Mot de passe provisoire (affiché une seule fois, à changer dès la première connexion) : {$plainPassword}");
        }

        return self::SUCCESS;
    }
}
