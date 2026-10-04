<?php

namespace App\Chat;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Jobs\SendChatAnnouncementEmail;
use App\Mail\MailQuota;
use App\Models\ChatEmailAnnouncement;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Annonce importante envoyée aussi par e-mail aux membres d'un groupe (administrateurs seulement).
 *
 * Règles : membres présents à l'adresse confirmée et non bloqués (jamais l'auteur) ; au plus une annonce par
 * e-mail toutes les 10 minutes par groupe ; jamais au point d'entamer la réserve d'e-mails gardée pour les
 * codes de confirmation, inscriptions et paiements (App\Mail\MailQuota). Refusée, l'annonce reste publiée
 * dans le groupe. Envoi en file d'attente : un travail par destinataire, compté (partis, échecs).
 */
class EmailAnnouncements
{
    public const int COOLDOWN_MINUTES = 10;

    /**
     * Administrateurs (dont le formateur principal) ayant activé la double authentification, comme pour l'admin.
     */
    public static function canUse(User $user): bool
    {
        return $user->isAdmin() && $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * Identifiants des membres qui recevront l'e-mail.
     *
     * @return Collection<int, int>
     */
    public function recipientIds(ChatGroup $group, User $author): Collection
    {
        return ChatGroupMember::query()
            ->where('chat_group_id', $group->id)
            ->whereNull('removed_at')
            ->where('user_id', '!=', $author->id)
            ->whereHas('user', fn ($query) => $query->whereNotNull('email_verified_at')->whereNull('blocked_at'))
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id);
    }

    /**
     * Vérification avant l'envoi (aperçu de la confirmation et contrôle au moment de publier).
     *
     * @return array{allowed: bool, recipients: int, reason: string|null}
     */
    public function check(ChatGroup $group, User $author): array
    {
        $recipients = $this->recipientIds($group, $author)->count();

        return ['allowed' => ($reason = $this->refusal($group, $author, $recipients)) === null, 'recipients' => $recipients, 'reason' => $reason];
    }

    /**
     * Envoie l'annonce déjà publiée par e-mail, ou explique pourquoi l'e-mail est refusé.
     *
     * @return array{status: string, recipients: int, reason: string|null}
     */
    public function send(ChatMessage $message, User $author): array
    {
        $group = $message->group;

        if (! filled($message->body)) {
            return ['status' => 'refused', 'recipients' => 0, 'reason' => 'Envoi par e-mail impossible : l\'annonce doit contenir un texte. Le message est publié dans le groupe, sans e-mail.'];
        }

        // Verrou sur le groupe : deux envois simultanés (double toucher) ne partent jamais tous les deux.
        [$announcement, $ids, $reason] = DB::transaction(function () use ($group, $message, $author): array {
            ChatGroup::query()->whereKey($group->id)->lockForUpdate()->first();

            $ids = $this->recipientIds($group, $author);
            $reason = $this->refusal($group, $author, $ids->count());

            if ($reason !== null) {
                return [null, $ids, $reason];
            }

            $announcement = ChatEmailAnnouncement::query()->create([
                'chat_group_id' => $group->id,
                'chat_message_id' => $message->id,
                'user_id' => $author->id,
                'recipients' => $ids->count(),
            ]);

            return [$announcement, $ids, null];
        });

        if ($announcement === null) {
            return ['status' => 'refused', 'recipients' => $ids->count(), 'reason' => $reason.' Le message est publié dans le groupe, sans e-mail.'];
        }

        foreach ($ids as $userId) {
            SendChatAnnouncementEmail::dispatch($announcement->id, $userId);
        }

        ActivityLog::record(
            AdminAction::ChatEmailAnnouncement,
            'Annonce du groupe « '.$group->formation->title.' » envoyée par e-mail à '.$ids->count().' '.($ids->count() > 1 ? 'membres' : 'membre').'.',
            $announcement,
            by: $author,
        );

        return ['status' => 'queued', 'recipients' => $ids->count(), 'reason' => null];
    }

    private function refusal(ChatGroup $group, User $author, int $recipients): ?string
    {
        if (! self::canUse($author)) {
            return 'L\'envoi par e-mail est réservé aux administrateurs.';
        }

        if ($recipients === 0) {
            return 'Aucun membre de ce groupe n\'a encore d\'adresse e-mail confirmée.';
        }

        $last = ChatEmailAnnouncement::query()
            ->where('chat_group_id', $group->id)
            ->where('created_at', '>', now()->subMinutes(self::COOLDOWN_MINUTES))
            ->latest('id')
            ->first();

        if ($last !== null) {
            return 'Une annonce de ce groupe a déjà été envoyée par e-mail à '.$last->created_at->timezone('Africa/Lome')->format('H \h i')
                .' : une seule annonce par e-mail toutes les '.self::COOLDOWN_MINUTES.' minutes, pour éviter un envoi en double.';
        }

        $available = MailQuota::availableForAnnouncements();

        if ($recipients > $available) {
            return 'Envoi par e-mail refusé : il faudrait '.$recipients.' e-mails, mais il n\'en reste que '.$available.' aujourd\'hui'
                .' sans toucher à la réserve de '.MailQuota::reserve().' e-mails gardée pour les codes de confirmation, les inscriptions et les paiements'
                .' (déjà envoyés aujourd\'hui : '.MailQuota::sentToday().' sur '.MailQuota::dailyLimit().'). Réessayez demain.';
        }

        return null;
    }
}
