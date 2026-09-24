<?php

namespace App\LiveSessions;

use App\Enums\RegistrationStatus;
use App\Jobs\SendPushNotification;
use App\Mail\LiveSessionMail;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Prévient les élèves concernés par une session : e-mail et notification sur téléphone (file d'attente).
 *
 * - Rappels : respectent les réglages de l'élève (e-mail des sessions, notifications sur téléphone).
 * - Modification importante, annulation, place libérée : e-mail toujours envoyé (information importante),
 *   notification sur téléphone si l'élève les a gardées activées.
 */
class SessionNotifier
{
    /**
     * Élèves concernés : tous ceux qui ont accès à la formation (en direct), les inscrits (présentiel).
     *
     * @return Collection<int, User>
     */
    public function audience(LiveSession $session, bool $includeWaitlist = false): Collection
    {
        if ($session->isOnline()) {
            return User::query()
                ->whereNull('blocked_at')
                ->whereHas('enrollments', fn ($query) => $query->where('formation_id', $session->formation_id))
                ->get();
        }

        $statuses = RegistrationStatus::seatHolding();

        if ($includeWaitlist) {
            $statuses[] = RegistrationStatus::Waitlisted->value;
        }

        return User::query()
            ->whereNull('blocked_at')
            ->whereHas('liveSessionRegistrations', fn ($query) => $query->where('live_session_id', $session->id)->whereIn('status', $statuses))
            ->get();
    }

    /**
     * @param  iterable<User>  $users
     * @param  list<string>  $changes
     */
    public function send(LiveSession $session, iterable $users, string $kind, array $changes = []): int
    {
        $users = collect($users)->filter(fn (User $user): bool => ! $user->isBlocked())->values();
        $isReminder = str_starts_with($kind, 'reminder_');

        foreach ($users as $user) {
            if ($isReminder && ! $user->session_email_enabled) {
                continue;
            }

            Mail::to($user)->queue(new LiveSessionMail($session, $kind, $user->first_name, $changes));
        }

        $ids = array_values($users->map(fn (User $user): int => $user->id)->all());

        if ($ids !== []) {
            SendPushNotification::dispatch($ids, $this->pushPayload($session, $kind));
        }

        return $users->count();
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    private function pushPayload(LiveSession $session, string $kind): array
    {
        $when = SessionTime::short($session);

        $body = match ($kind) {
            'reminder_day' => 'Demain : '.$session->displayTitle().', '.$when.' (heure de Lomé).',
            'reminder_hour' => 'Dans 1 heure : '.$session->displayTitle().($session->isOnline() ? '. Le lien Google Meet est dans votre espace.' : ', '.$session->location.'.'),
            'changed' => 'La session a été modifiée : '.$when.' (heure de Lomé).',
            'cancelled' => 'La session du '.$when.' est annulée.',
            'seat_available' => $session->requiresPayment()
                ? 'Une place s\'est libérée pour vous : payez le supplément pour la confirmer.'
                : 'Une place s\'est libérée : vous êtes inscrit(e) !',
            'reservation_expired' => 'Votre place réservée a été libérée (supplément non payé à temps).',
            default => $session->displayTitle(),
        };

        return [
            'title' => $session->formation->title,
            'body' => $body,
            'url' => route('student.sessions.show', $session),
            'tag' => 'session-'.$session->id,
        ];
    }
}
