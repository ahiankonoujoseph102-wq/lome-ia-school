<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Chat\ChatBadges;
use App\Chat\GroupMembership;
use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin « Équipe » : donner ou retirer le rôle de modérateur à un compte existant.
 */
class ModeratorController extends Controller
{
    /**
     * Changement de rôle : mot de passe redemandé, e-mail d'alerte et journal d'activité.
     *
     * @var array<string, string>
     */
    private const array PASSWORD_MESSAGES = [
        'current_password.required' => 'Confirmez avec votre mot de passe.',
        'current_password.current_password' => 'Mot de passe incorrect.',
    ];

    public function index(): View
    {
        return view('site.admin.moderators', [
            'moderators' => User::query()->where('role', UserRole::Moderator->value)->orderBy('name')->get(),
            'admins' => User::query()->where('role', UserRole::Admin->value)->orderBy('name')->get(),
            'principalId' => ChatBadges::principalId(),
        ]);
    }

    /**
     * Étiquettes des groupes : compte principal (« Formateur principal ») et libellés. Mot de passe redemandé ;
     * un changement de compte principal est un changement de rôle (alerte par e-mail et journal).
     */
    public function updateBadges(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('badges', [
            'principal_user_id' => ['required', 'integer', Rule::exists(User::class, 'id')->where('role', UserRole::Admin->value)],
            'principal_label' => ['required', 'string', 'max:'.ChatBadges::LABEL_MAX_LENGTH],
            'admin_label' => ['required', 'string', 'max:'.ChatBadges::LABEL_MAX_LENGTH],
            'current_password' => ['required', 'current_password'],
        ], [
            'principal_user_id.required' => 'Choisissez le compte du formateur principal.',
            'principal_user_id.exists' => 'Ce compte n\'est pas administrateur.',
            'principal_label.required' => 'Saisissez l\'étiquette du formateur principal.',
            'principal_label.max' => 'Étiquette trop longue : '.ChatBadges::LABEL_MAX_LENGTH.' caractères au maximum.',
            'admin_label.required' => 'Saisissez l\'étiquette des administrateurs.',
            'admin_label.max' => 'Étiquette trop longue : '.ChatBadges::LABEL_MAX_LENGTH.' caractères au maximum.',
        ] + self::PASSWORD_MESSAGES);

        $principalLabel = trim((string) preg_replace('/\s+/u', ' ', (string) $validated['principal_label']));
        $adminLabel = trim((string) preg_replace('/\s+/u', ' ', (string) $validated['admin_label']));

        if (mb_strtolower($principalLabel) === mb_strtolower($adminLabel)) {
            throw ValidationException::withMessages(['admin_label' => 'Les deux étiquettes doivent être différentes.'])->errorBag('badges');
        }

        $previous = ChatBadges::principalId();
        $principal = User::query()->findOrFail((int) $validated['principal_user_id']);

        SiteSettings::save(['site.chat_badges' => [
            'principal_user_id' => $principal->id,
            'principal' => $principalLabel,
            'admin' => $adminLabel,
        ]]);

        if ($previous !== $principal->id) {
            ActivityLog::record(AdminAction::RoleChanged, $principal->name.' ('.$principal->email.') est maintenant le compte principal (« '.$principalLabel.' ») dans les groupes.', $principal);
        }

        ActivityLog::record(AdminAction::SettingsUpdated, 'Étiquettes des groupes : « '.$principalLabel.' » et « '.$adminLabel.' ».');

        return back()->with('status', 'Étiquettes enregistrées : elles sont déjà visibles dans les groupes.');
    }

    public function store(Request $request, GroupMembership $membership): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->validate([
            'email' => ['required', 'email'],
            'current_password' => ['required', 'current_password'],
        ], [
            'email.required' => 'Saisissez l\'adresse e-mail du compte.',
            'email.email' => 'Adresse e-mail invalide.',
        ] + self::PASSWORD_MESSAGES)['email']));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'Aucun compte n\'utilise cette adresse. La personne doit d\'abord créer son compte sur le site.']);
        }

        if ($user->isAdmin()) {
            throw ValidationException::withMessages(['email' => 'Ce compte est administrateur : il a déjà tous les droits.']);
        }

        if ($user->isBlocked()) {
            throw ValidationException::withMessages(['email' => 'Ce compte est bloqué : débloquez-le avant de lui donner un rôle.']);
        }

        $user->forceFill(['role' => UserRole::Moderator])->save();
        $membership->syncStaff($user);

        ActivityLog::record(AdminAction::RoleChanged, $user->name.' ('.$user->email.') nommé modérateur.', $user);

        return back()->with('status', $user->name.' est maintenant modérateur (badge « Équipe ») dans tous les groupes.');
    }

    public function destroy(Request $request, User $user, GroupMembership $membership): RedirectResponse
    {
        abort_unless($user->isModerator(), 404);

        $request->validateWithBag('demote'.$user->id, ['current_password' => ['required', 'current_password']], self::PASSWORD_MESSAGES);

        $user->forceFill(['role' => UserRole::Student])->save();
        $membership->syncAfterDemotion($user);

        ActivityLog::record(AdminAction::RoleChanged, $user->name.' ('.$user->email.') n\'est plus modérateur.', $user);

        return back()->with('status', $user->name.' n\'est plus modérateur. Il reste membre des groupes de ses formations.');
    }
}
