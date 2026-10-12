<?php

namespace App\Newsletters;

use App\Enums\NewsletterRecipientStatus;
use App\Enums\NewsletterStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Newsletter;
use App\Models\NewsletterRecipient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Met en file d'attente les prochains e-mails des envois groupés, dans la limite du jour
 * (App\Newsletters\NewsletterQuota). Lancé chaque minute par le planificateur (commande app:send-newsletters),
 * et tout de suite au lancement ou à la reprise d'un envoi. Le reste attend le lendemain, sans rien relancer.
 */
class NewsletterDispatcher
{
    /**
     * Après ce délai, un e-mail resté « en file » (travail perdu, file arrêtée) est remis en file ; un e-mail resté
     * « en cours » (envoi interrompu au milieu) est classé en échec et jamais renvoyé (il est peut-être parti).
     */
    public const int STUCK_MINUTES = 30;

    /**
     * Nombre d'e-mails mis en file d'attente.
     */
    public function run(): int
    {
        $lock = Cache::lock('newsletters:dispatch', 120);

        if (! $lock->get()) {
            return 0;
        }

        try {
            $this->recoverStuck();
            $ids = $this->reserve(min(NewsletterQuota::BATCH, NewsletterQuota::availableToday()));
        } finally {
            $lock->release();
        }

        foreach ($ids as $id) {
            SendNewsletterEmail::dispatch($id);
        }

        $this->completeFinished();

        return count($ids);
    }

    /**
     * Passe les prochains destinataires de « en attente » à « en file » (plus ancien envoi d'abord).
     *
     * @return list<int>
     */
    private function reserve(int $budget): array
    {
        if ($budget <= 0) {
            return [];
        }

        return DB::transaction(function () use ($budget): array {
            $ids = NewsletterRecipient::query()
                ->where('status', NewsletterRecipientStatus::Pending->value)
                ->where(fn ($query) => $query->whereNull('retry_at')->orWhere('retry_at', '<=', now()))
                ->whereHas('newsletter', fn ($query) => $query->where('status', NewsletterStatus::Sending->value))
                ->orderBy('newsletter_id')
                ->orderBy('id')
                ->limit($budget)
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $reserved = [];

            foreach ($ids as $id) {
                $updated = NewsletterRecipient::query()->whereKey($id)->where('status', NewsletterRecipientStatus::Pending->value)
                    ->update(['status' => NewsletterRecipientStatus::Queued->value, 'queued_at' => now(), 'updated_at' => now()]);

                if ($updated > 0) {
                    $reserved[] = $id;
                }
            }

            return $reserved;
        });
    }

    private function recoverStuck(): void
    {
        $limit = now()->subMinutes(self::STUCK_MINUTES);

        NewsletterRecipient::query()
            ->where('status', NewsletterRecipientStatus::Queued->value)
            ->where('queued_at', '<', $limit)
            ->update(['status' => NewsletterRecipientStatus::Pending->value, 'queued_at' => null, 'updated_at' => now()]);

        NewsletterRecipient::query()
            ->where('status', NewsletterRecipientStatus::Sending->value)
            ->where('updated_at', '<', $limit)
            ->update([
                'status' => NewsletterRecipientStatus::Failed->value,
                'last_error' => 'Envoi interrompu au milieu : jamais renvoyé, pour ne pas risquer un double envoi.',
                'updated_at' => now(),
            ]);
    }

    private function completeFinished(): void
    {
        $manager = app(NewsletterManager::class);

        Newsletter::query()->where('status', NewsletterStatus::Sending->value)->get()
            ->each(fn (Newsletter $newsletter) => $manager->completeIfDone($newsletter));
    }
}
