<?php

namespace App\Chat;

use App\Enums\ChatRemovalReason;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qui fait partie de quel groupe.
 *
 * - Un élève rejoint le groupe d'une formation dès que l'accès lui est ouvert (provisoire ou validé),
 *   et le quitte immédiatement si l'accès est retiré ou si son compte est bloqué.
 * - L'admin et les modérateurs sont membres de tous les groupes.
 * - Un membre exclu par l'équipe ne revient pas tout seul, même avec un nouvel accès.
 */
class GroupMembership
{
    /**
     * Ajoute l'élève au groupe de la formation. Retourne null s'il n'y a pas sa place (exclu, compte bloqué).
     */
    public function add(User $user, Formation $formation): ?ChatGroupMember
    {
        if ($user->isBlocked()) {
            return null;
        }

        $group = ChatGroup::forFormation($formation);
        $member = $group->members()->where('user_id', $user->id)->first();

        if ($member === null) {
            return $this->join($group, $user);
        }

        if ($member->isExcluded() && ! $user->isStaff()) {
            return null;
        }

        if (! $member->isActive()) {
            $member->forceFill(['removed_at' => null, 'removal_reason' => null])->save();
        }

        return $member;
    }

    /**
     * Retire l'élève du groupe de la formation (l'équipe reste membre de tous les groupes).
     */
    public function remove(User $user, Formation $formation, ChatRemovalReason $reason = ChatRemovalReason::AccessRevoked): void
    {
        if ($user->isStaff()) {
            return;
        }

        $this->removeFrom(ChatGroupMember::query()
            ->where('user_id', $user->id)
            ->whereHas('group', fn ($query) => $query->where('formation_id', $formation->id)), $reason);
    }

    /**
     * Compte bloqué : retiré immédiatement de tous ses groupes.
     */
    public function removeEverywhere(User $user, ChatRemovalReason $reason = ChatRemovalReason::Blocked): void
    {
        $this->removeFrom(ChatGroupMember::query()->where('user_id', $user->id), $reason);
    }

    /**
     * Compte débloqué : retour dans les groupes des formations auxquelles il a encore accès
     * (jamais dans un groupe dont il a été exclu).
     */
    public function restoreAfterUnblock(User $user): void
    {
        ChatGroupMember::query()
            ->where('user_id', $user->id)
            ->where('removal_reason', ChatRemovalReason::Blocked->value)
            ->with('group')
            ->get()
            ->each(function (ChatGroupMember $member) use ($user): void {
                if ($user->isStaff() || $user->enrollments()->where('formation_id', $member->group->formation_id)->exists()) {
                    $member->forceFill(['removed_at' => null, 'removal_reason' => null])->save();
                }
            });

        foreach ($user->formations()->get() as $formation) {
            $this->add($user, $formation);
        }
    }

    /**
     * Appartenance active de ce compte au groupe, ou null s'il n'y a pas accès.
     * L'admin et les modérateurs sont ajoutés automatiquement au premier passage.
     */
    public function membershipFor(User $user, ChatGroup $group): ?ChatGroupMember
    {
        if ($user->isBlocked()) {
            return null;
        }

        $member = $group->members()->where('user_id', $user->id)->first();

        if ($user->isStaff()) {
            if ($member === null) {
                return $this->join($group, $user);
            }

            if (! $member->isActive()) {
                $member->forceFill(['removed_at' => null, 'removal_reason' => null])->save();
            }

            return $member;
        }

        return $member?->isActive() ? $member : null;
    }

    /**
     * Admin ou modérateur : membre de tous les groupes existants.
     */
    public function syncStaff(User $user): void
    {
        if (! $user->isStaff()) {
            return;
        }

        ChatGroup::query()->pluck('id')->each(function (int $groupId) use ($user): void {
            $group = ChatGroup::query()->findOrFail($groupId);
            $this->membershipFor($user, $group);
        });
    }

    /**
     * Rôle de modérateur retiré : il ne reste que dans les groupes des formations auxquelles il a accès.
     */
    public function syncAfterDemotion(User $user): void
    {
        ChatGroupMember::query()
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->with('group')
            ->get()
            ->each(function (ChatGroupMember $member) use ($user): void {
                if (! $user->enrollments()->where('formation_id', $member->group->formation_id)->exists()) {
                    $member->forceFill(['removed_at' => now(), 'removal_reason' => ChatRemovalReason::AccessRevoked])->save();
                }
            });
    }

    /**
     * Exclusion décidée par l'équipe.
     */
    public function exclude(ChatGroupMember $member): void
    {
        $member->forceFill(['removed_at' => now(), 'removal_reason' => ChatRemovalReason::Excluded])->save();
    }

    /**
     * Réintégration d'un membre exclu, seulement s'il a toujours accès à la formation.
     */
    public function reinstate(ChatGroupMember $member): bool
    {
        $user = $member->user;

        if ($user->isBlocked() || ! $user->enrollments()->where('formation_id', $member->group->formation_id)->exists()) {
            return false;
        }

        $member->forceFill(['removed_at' => null, 'removal_reason' => null])->save();

        return true;
    }

    /**
     * Nouveau membre : l'historique du groupe est visible, mais seuls les messages suivants comptent comme non lus.
     */
    private function join(ChatGroup $group, User $user): ChatGroupMember
    {
        return $group->members()->create([
            'user_id' => $user->id,
            'joined_at' => now(),
            'last_read_message_id' => $group->latestMessageId(),
        ]);
    }

    /**
     * @param  Builder<ChatGroupMember>  $query
     */
    private function removeFrom($query, ChatRemovalReason $reason): void
    {
        $query->whereNull('removed_at')->update([
            'removed_at' => now(),
            'removal_reason' => $reason->value,
            'updated_at' => now(),
        ]);
    }
}
