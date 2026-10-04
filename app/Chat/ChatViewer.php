<?php

namespace App\Chat;

use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\User;

/**
 * Ce que la personne connectée peut faire dans un groupe (écrire, supprimer, épingler, modérer…).
 */
class ChatViewer
{
    public function __construct(
        public readonly User $user,
        public readonly ChatGroupMember $member,
        public readonly ChatGroup $group,
    ) {}

    public function isStaff(): bool
    {
        return $this->user->isStaff();
    }

    public function canModerate(): bool
    {
        return $this->user->isStaff();
    }

    public function canPost(): bool
    {
        return $this->postBlockedReason() === null;
    }

    /**
     * Pourquoi la personne ne peut pas écrire en ce moment (null si elle peut écrire).
     */
    public function postBlockedReason(): ?string
    {
        if ($this->isStaff()) {
            return null;
        }

        if ($this->member->isMuted()) {
            return 'Vous êtes en sourdine jusqu\'au '.$this->member->muted_until?->translatedFormat('j F à H\hi').' : vous pouvez lire le groupe, mais pas écrire.';
        }

        if ($this->group->announcements_only) {
            return 'Seule l\'équipe peut écrire dans ce groupe pour le moment (mode annonces).';
        }

        return null;
    }

    public function owns(ChatMessage $message): bool
    {
        return $message->user_id === $this->user->id;
    }

    public function canDelete(ChatMessage $message): bool
    {
        return ! $message->isDeleted() && ($this->isStaff() || $this->owns($message));
    }

    public function canPin(ChatMessage $message): bool
    {
        return ! $message->isDeleted() && $this->isStaff();
    }

    /**
     * Réagir par emoji : membres actifs (un membre retiré n'arrive jamais ici), sauf en sourdine.
     * Le mode « annonces seulement » n'empêche pas de réagir. Jamais sur un message supprimé.
     */
    public function canReact(ChatMessage $message): bool
    {
        return ! $message->isDeleted() && ($this->isStaff() || ! $this->member->isMuted());
    }

    public function canReport(ChatMessage $message): bool
    {
        return ! $message->isDeleted() && ! $this->owns($message) && ! $this->isStaff();
    }

    /**
     * L'équipe peut mettre en sourdine ou exclure l'auteur d'un message (jamais un autre membre de l'équipe).
     */
    public function canModerateAuthorOf(ChatMessage $message): bool
    {
        return $this->isStaff() && ! $this->owns($message) && ! $message->author->isStaff();
    }
}
