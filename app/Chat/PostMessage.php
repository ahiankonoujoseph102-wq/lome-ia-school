<?php

namespace App\Chat;

use App\Enums\ChatMessageType;
use App\Events\Chat\ChatGroupChanged;
use App\Jobs\SendChatPushNotifications;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Publie un message : pièce jointe enregistrée en privé, diffusion immédiate aux membres connectés,
 * puis notifications sur téléphone préparées dans la file d'attente (traitée par le cron).
 */
class PostMessage
{
    public function __construct(
        private AttachmentStore $attachments,
        private ChatRealtime $realtime,
    ) {}

    public function handle(ChatGroup $group, ChatGroupMember $member, User $author, ChatMessageType $type, ?string $body, ?UploadedFile $file = null, ?int $replyToId = null, ?int $duration = null): ChatMessage
    {
        $attachment = $file !== null && $type->hasAttachment()
            ? $this->attachments->store($file, $type, $group->id)
            : [];

        try {
            $message = DB::transaction(function () use ($group, $member, $author, $type, $body, $attachment, $replyToId, $duration): ChatMessage {
                $replyTo = $replyToId
                    ? $group->messages()->whereKey($replyToId)->whereNull('deleted_at')->value('id')
                    : null;

                $message = $group->messages()->create([
                    'user_id' => $author->id,
                    'type' => $type,
                    'body' => filled($body) ? trim((string) $body) : null,
                    'reply_to_id' => $replyTo,
                    'duration_seconds' => $type === ChatMessageType::Voice ? min($duration ?? 0, (int) config('chat.attachments.voice.max_seconds')) : null,
                    ...$attachment,
                ]);

                $group->forceFill(['last_message_at' => $message->created_at])->save();
                $member->forceFill(['last_read_message_id' => $message->id, 'last_seen_at' => now()])->save();

                return $message;
            });
        } catch (Throwable $exception) {
            if (isset($attachment['attachment_path'])) {
                Storage::disk(ChatMessage::DISK)->delete($attachment['attachment_path']);
            }

            throw $exception;
        }

        $this->realtime->notify($group, ChatGroupChanged::MESSAGE_CREATED, $message->id);
        SendChatPushNotifications::dispatch($message->id);

        return $message;
    }
}
