<?php

namespace App\Console\Commands;

use App\Enums\ChatMessageType;
use App\Models\ChatMessage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime les notes vocales et les vidéos des groupes après la durée de conservation
 * (config/chat.php, « media_retention_months »). Le message reste avec la mention « Fichier expiré » ;
 * les textes, images et documents ne sont jamais touchés. Lancé chaque nuit par le cron.
 */
class ExpireChatMedia extends Command
{
    protected $signature = 'app:expire-chat-media';

    protected $description = 'Supprimer les notes vocales et vidéos des groupes arrivées au bout de leur durée de conservation';

    public function handle(): int
    {
        $months = (int) config('chat.media_retention_months', 12);

        if ($months <= 0) {
            $this->components->info('Conservation illimitée : aucun fichier supprimé.');

            return self::SUCCESS;
        }

        $expired = 0;

        ChatMessage::query()
            ->whereIn('type', [ChatMessageType::Voice->value, ChatMessageType::Video->value])
            ->whereNotNull('attachment_path')
            ->where('created_at', '<', now()->subMonths($months))
            ->chunkById(200, function (Collection $messages) use (&$expired): void {
                foreach ($messages as $message) {
                    /** @var ChatMessage $message */
                    Storage::disk(ChatMessage::DISK)->delete((string) $message->attachment_path);

                    $message->forceFill([
                        'attachment_path' => null,
                        'attachment_size' => null,
                        'attachment_expired_at' => now(),
                    ])->saveQuietly();

                    $expired++;
                }
            });

        $this->components->info($expired.' fichier(s) expiré(s) supprimé(s).');

        return self::SUCCESS;
    }
}
