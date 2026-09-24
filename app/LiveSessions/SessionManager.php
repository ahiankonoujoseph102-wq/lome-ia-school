<?php

namespace App\LiveSessions;

use App\Chat\ChatRealtime;
use App\Chat\GroupMembership;
use App\Chat\MessagePins;
use App\Chat\PostMessage;
use App\Enums\ChatMessageType;
use App\Enums\RegistrationStatus;
use App\Events\Chat\ChatGroupChanged;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Création, modification et annulation des sessions par l'équipe, avec l'annonce épinglée dans le groupe
 * de la formation et les notifications aux élèves concernés.
 */
class SessionManager
{
    /**
     * Champs dont la modification prévient les élèves, avec leur libellé.
     */
    private const array IMPORTANT = [
        'starts_at' => 'la date ou l\'heure',
        'duration_minutes' => 'la durée',
        'location' => 'le lieu',
        'meet_url' => 'le lien Google Meet',
    ];

    public function __construct(
        private SessionNotifier $notifier,
        private SessionRegistrar $registrar,
        private PostMessage $post,
        private MessagePins $pins,
        private GroupMembership $membership,
        private ChatRealtime $realtime,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $by): LiveSession
    {
        $session = DB::transaction(fn (): LiveSession => LiveSession::query()->create([...$data, 'created_by' => $by->id]));
        $session->load('formation');

        $this->announce($session, $by);

        return $session;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string> Ce qui a changé d'important (vide si rien).
     */
    public function update(LiveSession $session, array $data, User $by): array
    {
        $session->fill($data);
        $changes = array_values(array_filter(
            self::IMPORTANT,
            fn (string $field): bool => $session->isDirty($field),
            ARRAY_FILTER_USE_KEY,
        ));
        $capacityChanged = $session->isDirty('capacity');
        $session->save();

        $this->refreshAnnouncement($session);

        if ($capacityChanged) {
            $this->registrar->promoteWaitlist($session);
        }

        if ($changes !== [] && $session->isUpcoming()) {
            $this->notifier->send($session, $this->notifier->audience($session, includeWaitlist: true), 'changed', $changes);
        }

        return $changes;
    }

    public function cancel(LiveSession $session, ?string $reason, User $by): void
    {
        if ($session->isCancelled()) {
            return;
        }

        $session->forceFill([
            'cancelled_at' => now(),
            'cancellation_reason' => filled($reason) ? trim((string) $reason) : null,
        ])->save();

        // Liste des élèves prévenus, avant que leurs inscriptions ne soient annulées.
        $audience = $this->notifier->audience($session, includeWaitlist: true);

        $session->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->get()
            ->each(fn ($registration) => $this->registrar->cancel($registration, 'session_cancelled', promote: false));

        $this->unpinAnnouncement($session);
        $this->postInGroup($session, $by, '❌ Session annulée : '.$session->displayTitle().' du '.SessionTime::short($session).' (heure de Lomé).'
            .($session->cancellation_reason ? "\nMotif : ".$session->cancellation_reason : ''));

        $this->notifier->send($session, $audience, 'cancelled');
    }

    /**
     * Sessions terminées : l'annonce est désépinglée du groupe.
     */
    public function unpinFinished(): int
    {
        $sessions = LiveSession::query()
            ->whereNotNull('announcement_message_id')
            ->whereNull('announcement_unpinned_at')
            ->where('ends_at', '<=', now())
            ->get();

        $sessions->each(fn (LiveSession $session) => $this->unpinAnnouncement($session));

        return $sessions->count();
    }

    /**
     * Texte de l'annonce publiée dans le groupe de la formation.
     */
    public function announcementText(LiveSession $session): string
    {
        $lines = [
            ($session->isOnline() ? '📅 Session en direct sur Google Meet' : '📍 Session en présentiel à Lomé').' : '.$session->displayTitle(),
            SessionTime::full($session).' · Durée : '.$session->durationLabel(),
        ];

        if ($session->isOnline()) {
            $lines[] = 'Lien Google Meet : '.$session->meet_url;
        } else {
            $lines[] = 'Lieu : '.$session->location;
            $lines[] = 'Places : '.$session->capacity.' · Tarif : '.$session->priceLabel();
            $lines[] = 'Inscription obligatoire : '.route('student.sessions.show', $session);
        }

        if (filled($session->description)) {
            $lines[] = '';
            $lines[] = trim((string) $session->description);
        }

        return implode("\n", $lines);
    }

    private function announce(LiveSession $session, User $by): void
    {
        $message = $this->postInGroup($session, $by, $this->announcementText($session));

        if ($message === null) {
            return;
        }

        $this->pins->pin($message, $by);
        $session->forceFill(['announcement_message_id' => $message->id])->save();
    }

    private function refreshAnnouncement(LiveSession $session): void
    {
        $message = $session->announcement;

        if ($message === null || $message->isDeleted()) {
            return;
        }

        $message->forceFill(['body' => $this->announcementText($session)])->save();
        $this->realtime->notify($message->group, ChatGroupChanged::MESSAGE_UPDATED, $message->id);
    }

    private function unpinAnnouncement(LiveSession $session): void
    {
        if ($session->announcement !== null) {
            $this->pins->unpin($session->announcement);
        }

        $session->forceFill(['announcement_unpinned_at' => now()])->save();
    }

    private function postInGroup(LiveSession $session, User $by, string $text): ?ChatMessage
    {
        $group = ChatGroup::forFormation($session->formation);
        $member = $this->membership->membershipFor($by, $group);

        if ($member === null) {
            return null;
        }

        return $this->post->handle($group, $member, $by, ChatMessageType::Text, $text);
    }
}
