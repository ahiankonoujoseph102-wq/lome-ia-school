<?php

namespace App\Chat;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Étiquettes à côté du nom dans les groupes, décidées uniquement par le rôle du compte (jamais par un texte
 * saisi par l'élève) :
 * - « Formateur principal » (doré) : le compte principal de l'école, celui de Joseph ;
 * - « Administrateur » (violet) : les autres administrateurs ;
 * - « Équipe » : les modérateurs ; aucune étiquette pour un élève.
 *
 * Compte principal : celui choisi dans l'admin (Équipe > Étiquettes dans les groupes) ; tant qu'aucun n'est
 * choisi, l'administrateur dont l'adresse est celle du responsable du site (site.legal.owner.email), sinon le
 * plus ancien administrateur. Libellés modifiables au même endroit (clé site.chat_badges).
 */
class ChatBadges
{
    public const string PRINCIPAL = 'principal';

    public const string ADMIN = 'admin';

    public const string TEAM = 'team';

    public const string TEAM_LABEL = 'Équipe';

    public const int LABEL_MAX_LENGTH = 30;

    /**
     * Titres qu'aucun nom de compte ne peut contenir (en plus des deux libellés réglés dans l'admin).
     *
     * @var list<string>
     */
    public const array RESERVED_WORDS = ['formateur', 'formatrice', 'administrateur', 'administratrice', 'admin', 'moderateur', 'moderatrice', 'equipe'];

    /**
     * Sorte d'étiquette du compte : principal, admin, team ou null (élève).
     */
    public static function kind(User $user): ?string
    {
        return match ($user->role) {
            UserRole::Admin => $user->id === self::principalId() ? self::PRINCIPAL : self::ADMIN,
            UserRole::Moderator => self::TEAM,
            default => null,
        };
    }

    public static function label(User $user): ?string
    {
        return match (self::kind($user)) {
            self::PRINCIPAL => self::principalLabel(),
            self::ADMIN => self::adminLabel(),
            self::TEAM => self::TEAM_LABEL,
            default => null,
        };
    }

    /**
     * Classes de la pastille (style stable face au mode sombre forcé : fond translucide, texte clair, contour).
     */
    public static function classes(?string $kind): string
    {
        return match ($kind) {
            self::PRINCIPAL => 'chat-badge chat-badge-principal',
            self::ADMIN => 'chat-badge chat-badge-admin',
            default => 'chat-badge chat-badge-team',
        };
    }

    public static function principalLabel(): string
    {
        return self::setting('principal', 'Formateur principal');
    }

    public static function adminLabel(): string
    {
        return self::setting('admin', 'Administrateur');
    }

    /**
     * Identifiant du compte principal (null s'il n'existe aucun administrateur).
     */
    public static function principalId(): ?int
    {
        $chosen = config('site.chat_badges.principal_user_id');

        return self::resolvePrincipal(is_numeric($chosen) ? (int) $chosen : null, Str::lower((string) config('site.legal.owner.email')));
    }

    /**
     * Compte choisi dans l'admin seulement s'il est (encore) administrateur ; sinon l'adresse du responsable
     * du site ; sinon le plus ancien administrateur. Une seule recherche par requête et par réglage.
     */
    private static function resolvePrincipal(?int $chosen, string $ownerEmail): ?int
    {
        return once(function () use ($chosen, $ownerEmail): ?int {
            $admins = User::query()->where('role', UserRole::Admin->value);

            if ($chosen !== null && (clone $admins)->whereKey($chosen)->exists()) {
                return $chosen;
            }

            if ($ownerEmail !== '' && ($owner = (clone $admins)->where('email', $ownerEmail)->value('id')) !== null) {
                return (int) $owner;
            }

            $first = (clone $admins)->orderBy('id')->value('id');

            return $first === null ? null : (int) $first;
        });
    }

    /**
     * Le nom contient-il un titre réservé à l'équipe (« Formateur », « Administrateur », libellés de l'admin…) ?
     * Comparaison sans accents ni majuscules, mot entier (un nom comme « Adminata » reste possible).
     */
    public static function imitates(string $name): bool
    {
        $normalized = ' '.trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($name)))).' ';

        $titles = [...self::RESERVED_WORDS, self::principalLabel(), self::adminLabel()];

        foreach ($titles as $title) {
            $title = trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($title))));

            if ($title !== '' && str_contains($normalized, ' '.$title.' ')) {
                return true;
            }
        }

        return false;
    }

    private static function setting(string $key, string $default): string
    {
        $value = config('site.chat_badges.'.$key);
        $value = is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', $value)) : '';

        return $value !== '' ? $value : $default;
    }
}
