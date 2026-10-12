<?php

namespace App\Jobs;

use App\Enums\NewsletterRecipientStatus;
use App\Enums\NewsletterStatus;
use App\Mail\NewsletterMail;
use App\Models\NewsletterRecipient;
use App\Newsletters\NewsletterRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Un e-mail d'un envoi groupé pour une personne.
 *
 * Jamais deux fois : la ligne du destinataire passe de « en file » à « en cours » sous verrou avant l'envoi ;
 * un deuxième travail pour la même ligne (file relancée) ne trouve plus « en file » et s'arrête. Un échec du
 * fournisseur remet la ligne « en attente » avec une date de nouvel essai (15 min, puis 2 h), puis « échec »
 * après le dernier essai. Les nouveaux essais sont décidés ici, pas par la file d'attente (un seul essai par travail).
 */
class SendNewsletterEmail implements ShouldQueue
{
    use Queueable;

    public const int MAX_ATTEMPTS = 3;

    /**
     * Minutes avant le nouvel essai, selon le nombre d'essais déjà faits.
     *
     * @var array<int, int>
     */
    public const array RETRY_MINUTES = [1 => 15, 2 => 120];

    public int $tries = 1;

    public function __construct(public int $recipientId) {}

    public function handle(): void
    {
        $recipient = DB::transaction(function (): ?NewsletterRecipient {
            $recipient = NewsletterRecipient::query()->whereKey($this->recipientId)->lockForUpdate()->first();

            if ($recipient === null || $recipient->status !== NewsletterRecipientStatus::Queued) {
                return null;
            }

            $newsletter = $recipient->newsletter;

            // Envoi mis en pause : l'e-mail repart à la reprise. Annulé : il ne partira jamais.
            if ($newsletter->status === NewsletterStatus::Paused) {
                $recipient->forceFill(['status' => NewsletterRecipientStatus::Pending, 'queued_at' => null])->save();

                return null;
            }

            if ($newsletter->status !== NewsletterStatus::Sending) {
                $recipient->forceFill(['status' => NewsletterRecipientStatus::Cancelled])->save();

                return null;
            }

            // Compte supprimé, bloqué, adresse plus confirmée ou désabonnement depuis le lancement : rien n'est envoyé.
            $user = $recipient->user;

            if ($user === null || ! NewsletterRecipients::accepts($user, $newsletter->audience)) {
                $recipient->forceFill(['status' => NewsletterRecipientStatus::Skipped])->save();

                return null;
            }

            $recipient->forceFill(['status' => NewsletterRecipientStatus::Sending, 'attempts' => $recipient->attempts + 1])->save();

            return $recipient;
        });

        if ($recipient === null) {
            return;
        }

        try {
            Mail::to($recipient->user)->send(new NewsletterMail($recipient->newsletter, $recipient->user));
        } catch (Throwable $exception) {
            report($exception);

            $last = $recipient->attempts >= self::MAX_ATTEMPTS;

            $recipient->forceFill([
                'status' => $last ? NewsletterRecipientStatus::Failed : NewsletterRecipientStatus::Pending,
                'queued_at' => null,
                'retry_at' => $last ? null : now()->addMinutes(self::RETRY_MINUTES[$recipient->attempts] ?? 120),
                'last_error' => Str::limit($exception->getMessage(), 250),
            ])->save();

            return;
        }

        $recipient->forceFill(['status' => NewsletterRecipientStatus::Sent, 'sent_at' => now(), 'retry_at' => null, 'last_error' => null])->save();
    }
}
