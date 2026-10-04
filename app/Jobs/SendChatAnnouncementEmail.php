<?php

namespace App\Jobs;

use App\Mail\ChatAnnouncementMail;
use App\Models\ChatEmailAnnouncement;
use App\Models\ChatGroupMember;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Un e-mail d'annonce pour un membre (un travail par destinataire : un échec ne bloque pas les autres).
 * Chaque issue est comptée sur l'annonce : partis, échecs, abandonnés.
 */
class SendChatAnnouncementEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public int $announcementId, public int $userId) {}

    public function handle(): void
    {
        $announcement = ChatEmailAnnouncement::query()->with(['group.formation', 'message.author'])->find($this->announcementId);

        if ($announcement === null) {
            return;
        }

        $user = User::query()->find($this->userId);
        $message = $announcement->message;
        $stillMember = $user !== null && ChatGroupMember::query()
            ->where('chat_group_id', $announcement->chat_group_id)
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->exists();

        // Message supprimé, membre parti ou bloqué entre-temps : l'e-mail n'est pas envoyé.
        if ($user === null || $message === null || $message->isDeleted() || ! $stillMember || $user->isBlocked() || ! $user->hasConfirmedEmail()) {
            $announcement->increment('skipped');

            return;
        }

        Mail::to($user)->send(new ChatAnnouncementMail(
            firstName: $user->first_name,
            groupTitle: $announcement->group->formation->title,
            authorName: $message->author->name,
            body: (string) $message->body,
            url: route('chat.show', $announcement->group->formation),
        ));

        $announcement->increment('sent');
    }

    public function failed(?Throwable $exception): void
    {
        ChatEmailAnnouncement::query()->whereKey($this->announcementId)->increment('failed');
    }
}
