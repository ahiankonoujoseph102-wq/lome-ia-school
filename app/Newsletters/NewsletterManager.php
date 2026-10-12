<?php

namespace App\Newsletters;

use App\Enums\NewsletterRecipientStatus;
use App\Enums\NewsletterStatus;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lancement, pause, reprise et annulation d'un envoi groupé.
 *
 * Au lancement, la liste des destinataires est figée (une ligne par personne, index unique : jamais deux
 * e-mails pour la même personne), puis les e-mails partent progressivement (App\Newsletters\NewsletterDispatcher).
 */
class NewsletterManager
{
    public function __construct(private NewsletterDispatcher $dispatcher) {}

    /**
     * Lance un brouillon. Renvoie le nombre de destinataires, ou null si l'envoi n'est plus un brouillon
     * (double toucher) ou si personne ne peut le recevoir.
     */
    public function start(Newsletter $newsletter, User $by): ?int
    {
        $count = DB::transaction(function () use ($newsletter, $by): ?int {
            $locked = Newsletter::query()->whereKey($newsletter->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isDraft()) {
                return null;
            }

            $now = now();

            NewsletterRecipients::query($locked->audience)->select('id')->chunkById(500, function ($users) use ($locked, $now): void {
                DB::table('newsletter_recipients')->insertOrIgnore($users->map(fn (User $user): array => [
                    'newsletter_id' => $locked->id,
                    'user_id' => $user->id,
                    'status' => NewsletterRecipientStatus::Pending->value,
                    'attempts' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

            $count = $locked->recipients()->count();

            if ($count === 0) {
                return 0;
            }

            $locked->forceFill([
                'status' => NewsletterStatus::Sending,
                'recipients_count' => $count,
                'started_at' => $now,
                'user_id' => $locked->user_id ?? $by->id,
            ])->save();

            return $count;
        });

        if ($count) {
            // Premiers e-mails tout de suite (dans la limite du jour), la suite chaque minute par le planificateur.
            $this->dispatcher->run();
        }

        $newsletter->refresh();

        return $count ?: null;
    }

    public function pause(Newsletter $newsletter): bool
    {
        return $this->transition($newsletter, NewsletterStatus::Sending, NewsletterStatus::Paused);
    }

    public function resume(Newsletter $newsletter): bool
    {
        $done = $this->transition($newsletter, NewsletterStatus::Paused, NewsletterStatus::Sending);

        if ($done) {
            $this->dispatcher->run();
        }

        return $done;
    }

    /**
     * Annule un envoi en cours ou en pause : les e-mails pas encore partis ne partiront jamais.
     */
    public function cancel(Newsletter $newsletter): bool
    {
        return DB::transaction(function () use ($newsletter): bool {
            $locked = Newsletter::query()->whereKey($newsletter->id)->lockForUpdate()->first();

            if ($locked === null || ! in_array($locked->status, [NewsletterStatus::Sending, NewsletterStatus::Paused], true)) {
                return false;
            }

            $locked->forceFill(['status' => NewsletterStatus::Cancelled, 'cancelled_at' => now()])->save();

            // « sending » n'est pas touché : l'e-mail est peut-être déjà parti.
            $locked->recipients()
                ->whereIn('status', [NewsletterRecipientStatus::Pending->value, NewsletterRecipientStatus::Queued->value])
                ->update(['status' => NewsletterRecipientStatus::Cancelled->value, 'updated_at' => now()]);

            $newsletter->refresh();

            return true;
        });
    }

    /**
     * Un envoi dont tous les e-mails sont partis, abandonnés ou en échec définitif est terminé.
     */
    public function completeIfDone(Newsletter $newsletter): void
    {
        if ($newsletter->status !== NewsletterStatus::Sending) {
            return;
        }

        if (! $newsletter->recipients()->whereIn('status', NewsletterRecipientStatus::open())->exists()) {
            Newsletter::query()->whereKey($newsletter->id)->where('status', NewsletterStatus::Sending->value)
                ->update(['status' => NewsletterStatus::Completed->value, 'completed_at' => now(), 'updated_at' => now()]);
        }
    }

    private function transition(Newsletter $newsletter, NewsletterStatus $from, NewsletterStatus $to): bool
    {
        $changed = Newsletter::query()->whereKey($newsletter->id)->where('status', $from->value)
            ->update(['status' => $to->value, 'updated_at' => now()]) > 0;

        $newsletter->refresh();

        return $changed;
    }
}
