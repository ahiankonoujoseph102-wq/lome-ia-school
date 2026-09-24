<?php

namespace App\Console\Commands;

use App\Mail\ChatDigestMail;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail récapitulatif des messages non lus (lancé chaque jour par le cron, voir routes/console.php).
 *
 * Au plus un e-mail par jour et par élève, seulement s'il y a des messages non lus qui n'ont pas
 * déjà été annoncés, jamais pour un groupe dont les notifications sont coupées.
 */
class SendChatDigests extends Command
{
    protected $signature = 'app:send-chat-digests';

    protected $description = 'Envoyer l\'e-mail récapitulatif des messages non lus des groupes';

    public function handle(): int
    {
        $sent = 0;

        User::query()
            ->where('chat_digest_enabled', true)
            ->whereNull('blocked_at')
            ->where(fn ($query) => $query->whereNull('chat_digest_sent_at')->orWhere('chat_digest_sent_at', '<', now()->subHours(20)))
            ->whereHas('chatMemberships', fn ($query) => $query->whereNull('removed_at')->where('notifications_muted', false))
            ->chunkById(100, function (Collection $users) use (&$sent): void {
                foreach ($users as $user) {
                    /** @var User $user */
                    $sent += $this->sendTo($user) ? 1 : 0;
                }
            });

        $this->components->info($sent.' e-mail(s) récapitulatif(s) mis en file d\'attente.');

        return self::SUCCESS;
    }

    private function sendTo(User $user): bool
    {
        $groups = [];
        $updates = [];

        $memberships = $user->chatMemberships()
            ->whereNull('removed_at')
            ->where('notifications_muted', false)
            ->with('group.formation')
            ->get();

        foreach ($memberships as $member) {
            /** @var ChatGroupMember $member */
            $threshold = max((int) $member->last_read_message_id, (int) $member->last_digested_message_id);

            $query = ChatMessage::query()
                ->where('chat_group_id', $member->chat_group_id)
                ->where('id', '>', $threshold)
                ->where('user_id', '!=', $user->id)
                ->whereNull('deleted_at');

            $count = (clone $query)->count();

            if ($count === 0) {
                continue;
            }

            $latest = (clone $query)->with('author')->orderByDesc('id')->limit(3)->get();

            $groups[] = [
                'title' => $member->group->formation->title,
                'url' => route('chat.show', $member->group->formation),
                'count' => $count,
                'previews' => array_values($latest->reverse()->map(fn (ChatMessage $message): string => $message->author->first_name.' : '.$message->preview(100))->all()),
            ];
            $updates[$member->id] = (int) $latest->first()?->id;
        }

        if ($groups === []) {
            return false;
        }

        Mail::to($user)->queue(new ChatDigestMail($user->first_name, $groups));

        foreach ($updates as $memberId => $lastId) {
            ChatGroupMember::query()->whereKey($memberId)->update(['last_digested_message_id' => $lastId]);
        }

        $user->forceFill(['chat_digest_sent_at' => now()])->save();

        return true;
    }
}
