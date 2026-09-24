<?php

namespace App\LiveSessions;

use App\Enums\PurchaseStatus;
use App\Enums\RegistrationStatus;
use App\Models\LiveSession;
use App\Models\LiveSessionRegistration;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inscriptions aux sessions en présentiel : places limitées, liste d'attente, supplément éventuel.
 *
 * - Place libre : inscription confirmée (gratuite) ou place réservée (supplément à payer).
 * - Complet : liste d'attente. Dès qu'une place se libère, la première personne en attente est inscrite
 *   automatiquement et prévenue (avec un délai pour payer si un supplément est demandé).
 * - Supplément : la place reste réservée pendant la vérification du paiement, confirmée à la validation
 *   (ou tout de suite sous le seuil de vérification) et libérée si le paiement est refusé.
 */
class SessionRegistrar
{
    public function __construct(private SessionNotifier $notifier) {}

    public function register(User $user, LiveSession $session): LiveSessionRegistration
    {
        if (! $session->acceptsRegistrations()) {
            throw ValidationException::withMessages(['session' => 'Les inscriptions à cette session sont fermées.']);
        }

        if (! $user->isEnrolledIn($session->formation)) {
            throw ValidationException::withMessages(['session' => 'Cette session est réservée aux élèves de la formation.']);
        }

        return DB::transaction(function () use ($user, $session): LiveSessionRegistration {
            // Verrou sur la session : deux inscriptions simultanées ne peuvent pas prendre la même dernière place.
            $session = LiveSession::query()->lockForUpdate()->findOrFail($session->id);

            $registration = $session->registrations()->firstOrNew(['user_id' => $user->id]);

            if ($registration->exists && $registration->isActive()) {
                return $registration;
            }

            $registration->forceFill(['cancelled_at' => null, 'cancel_reason' => null, 'purchase_id' => null, 'attended_at' => null]);

            if ($session->seatsLeft() > 0) {
                $this->giveSeat($registration, $session);
            } else {
                $registration->forceFill([
                    'status' => RegistrationStatus::Waitlisted,
                    'waitlisted_at' => now(),
                    'reserved_until' => null,
                    'confirmed_at' => null,
                ])->save();
            }

            return $registration;
        });
    }

    /**
     * Annulation par l'élève, jusqu'au délai prévu avant la session. Un supplément déjà payé est
     * alors à rembourser : il apparaît dans la liste « Suppléments à rembourser » de la session.
     */
    public function cancelByStudent(LiveSessionRegistration $registration): void
    {
        $session = $registration->session;

        if ($registration->status === RegistrationStatus::Cancelled) {
            return;
        }

        if ($registration->paymentUnderReview()) {
            throw ValidationException::withMessages(['session' => 'Votre paiement est en cours de vérification : écrivez-nous sur WhatsApp pour annuler.']);
        }

        if ($registration->status !== RegistrationStatus::Waitlisted && now()->greaterThan($session->cancellationDeadline())) {
            throw ValidationException::withMessages(['session' => 'L\'annulation n\'est plus possible : elle doit être faite au plus tard '.config('live_sessions.cancel_deadline_hours').' heures avant la session.']);
        }

        $purchase = $registration->purchase;

        DB::transaction(function () use ($registration, $purchase): void {
            $this->cancel($registration, 'student');

            if ($purchase !== null && $purchase->status->grantsAccess() && $purchase->refund_due_at === null) {
                $purchase->forceFill(['refund_due_at' => now()])->save();
            }
        });
    }

    /**
     * Annule une inscription et, si elle occupait une place, la propose à la liste d'attente.
     */
    public function cancel(LiveSessionRegistration $registration, string $reason, bool $promote = true): void
    {
        $heldSeat = $registration->status->holdsSeat();

        $registration->forceFill([
            'status' => RegistrationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'reserved_until' => null,
        ])->save();

        if ($heldSeat && $promote) {
            $this->promoteWaitlist($registration->session);
        }
    }

    /**
     * Places libres : les premières personnes en attente sont inscrites automatiquement et prévenues.
     */
    public function promoteWaitlist(LiveSession $session): void
    {
        $promoted = DB::transaction(function () use ($session): array {
            $session = LiveSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->acceptsRegistrations()) {
                return [];
            }

            $promoted = [];

            while ($session->seatsLeft() > 0) {
                $next = $session->registrations()
                    ->where('status', RegistrationStatus::Waitlisted->value)
                    ->orderBy('waitlisted_at')
                    ->orderBy('id')
                    ->first();

                if ($next === null) {
                    break;
                }

                $this->giveSeat($next, $session);
                $promoted[] = $next;
            }

            return $promoted;
        });

        foreach ($promoted as $registration) {
            $this->notifier->send($registration->session, [$registration->user], 'seat_available');
        }
    }

    /**
     * Preuve de paiement du supplément envoyée : la place reste réservée pendant la vérification
     * (ou est confirmée tout de suite en accès provisoire, sous le seuil de vérification).
     */
    public function attachPayment(Purchase $purchase): void
    {
        $registration = $this->registrationFor($purchase);

        if ($registration === null) {
            return;
        }

        $registration->forceFill(['purchase_id' => $purchase->id, 'reserved_until' => null])->save();

        if ($purchase->status === PurchaseStatus::Provisional) {
            $this->confirm($registration);
        }
    }

    /**
     * Paiement du supplément validé : place confirmée.
     */
    public function confirmPayment(Purchase $purchase): void
    {
        $registration = $this->registrationFor($purchase);

        if ($registration !== null && $registration->isActive() && $this->paidBy($registration, $purchase)) {
            $registration->forceFill(['purchase_id' => $purchase->id])->save();
            $this->confirm($registration);
        }
    }

    /**
     * Paiement du supplément refusé : la place est libérée pour la liste d'attente.
     */
    public function releasePayment(Purchase $purchase): void
    {
        $registration = $this->registrationFor($purchase);

        if ($registration !== null && $registration->isActive() && $this->paidBy($registration, $purchase)) {
            $this->cancel($registration, 'payment_rejected');
        }
    }

    /**
     * Accès à la formation retiré (paiement refusé, remboursement) : inscriptions aux sessions à venir annulées.
     */
    public function cancelForAccessLoss(User $user, ?int $formationId, string $reason): void
    {
        LiveSessionRegistration::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', RegistrationStatus::Cancelled->value)
            ->whereHas('session', function ($query) use ($formationId): void {
                $query->where('ends_at', '>', now());

                if ($formationId !== null) {
                    $query->where('formation_id', $formationId);
                }
            })
            ->with('session')
            ->get()
            ->each(fn (LiveSessionRegistration $registration) => $this->cancel($registration, $reason));
    }

    /**
     * Places réservées dont le supplément n'a pas été payé à temps : libérées pour la liste d'attente.
     */
    public function releaseExpiredReservations(): int
    {
        $expired = LiveSessionRegistration::query()
            ->where('status', RegistrationStatus::Reserved->value)
            ->whereNull('purchase_id')
            ->where('reserved_until', '<', now())
            ->with(['session', 'user'])
            ->get();

        foreach ($expired as $registration) {
            $this->cancel($registration, 'payment_expired');
            $this->notifier->send($registration->session, [$registration->user], 'reservation_expired');
        }

        return $expired->count();
    }

    private function giveSeat(LiveSessionRegistration $registration, LiveSession $session): void
    {
        if ($session->requiresPayment()) {
            $registration->forceFill([
                'status' => RegistrationStatus::Reserved,
                'reserved_until' => now()->addHours((int) config('live_sessions.payment_hold_hours', 24)),
                'confirmed_at' => null,
            ])->save();

            return;
        }

        $registration->forceFill(['status' => RegistrationStatus::Confirmed, 'reserved_until' => null])->save();
        $this->confirm($registration);
    }

    private function confirm(LiveSessionRegistration $registration): void
    {
        $registration->forceFill([
            'status' => RegistrationStatus::Confirmed,
            'confirmed_at' => $registration->confirmed_at ?? now(),
            'reserved_until' => null,
        ])->save();
    }

    /**
     * L'inscription est-elle bien celle de ce paiement ? (Après une annulation remboursée puis une
     * nouvelle inscription, l'ancien paiement ne doit ni confirmer ni libérer la nouvelle place.)
     */
    private function paidBy(LiveSessionRegistration $registration, Purchase $purchase): bool
    {
        return $registration->purchase_id === $purchase->id;
    }

    private function registrationFor(Purchase $purchase): ?LiveSessionRegistration
    {
        if ($purchase->live_session_id === null || $purchase->user_id === null) {
            return null;
        }

        return LiveSessionRegistration::query()
            ->where('live_session_id', $purchase->live_session_id)
            ->where('user_id', $purchase->user_id)
            ->with('session')
            ->first();
    }
}
