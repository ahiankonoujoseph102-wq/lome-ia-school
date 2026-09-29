<?php

namespace App\Consultations;

use App\Enums\ConsultationRequesterType;
use App\Enums\ConsultationStatus;
use App\Mail\ConsultationAdminMail;
use App\Mail\ConsultationMail;
use App\Models\Consultation;
use App\Models\ConsultationSettings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Réservation, déplacement et annulation des consultations gratuites.
 *
 * - Le créneau doit être libre au moment de l'enregistrement (verrou + index unique : deux personnes ne
 *   peuvent jamais réserver le même créneau).
 * - Une seule réservation à venir par numéro WhatsApp et par adresse e-mail.
 * - Rattachée au compte élève qui a le même numéro WhatsApp ou la même adresse e-mail.
 */
class ConsultationBooker
{
    /**
     * @param  array{name: string, phone: string, email: string, requester_type: ConsultationRequesterType, organization_name: string|null, topic: string, formation_id: int|null}  $data
     * @return array{0: Consultation, 1: string} Le rendez-vous et le jeton de son lien de gestion.
     */
    public function book(array $data, CarbonImmutable $start): array
    {
        try {
            [$consultation, $token] = DB::transaction(function () use ($data, $start): array {
                $settings = $this->lock();

                if (! (new SlotFinder($settings))->isAvailable($start)) {
                    throw ValidationException::withMessages(['starts_at' => 'Ce créneau n\'est plus disponible. Choisissez-en un autre.']);
                }

                $this->ensureNoUpcomingBooking($data['phone'], $data['email']);

                $consultation = new Consultation([
                    ...$data,
                    'user_id' => $this->matchingUser($data['phone'], $data['email'])?->id,
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($settings->duration_minutes),
                    'status' => ConsultationStatus::Booked,
                ]);
                $consultation->forceFill(['slot_key' => Consultation::slotKey($start)]);
                $token = $consultation->issueManageToken();
                $consultation->save();

                return [$consultation, $token];
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['starts_at' => 'Ce créneau vient d\'être réservé par quelqu\'un d\'autre. Choisissez-en un autre.']);
        }

        Mail::to($consultation->email)->send(new ConsultationMail($consultation, 'confirmed'));
        Mail::to($this->adminEmail())->send(new ConsultationAdminMail($consultation, 'booked'));

        return [$consultation, $token];
    }

    public function move(Consultation $consultation, CarbonImmutable $start, bool $byVisitor = true): Consultation
    {
        if ($byVisitor && ! $consultation->canBeChangedByVisitor()) {
            throw ValidationException::withMessages(['starts_at' => $this->deadlineMessage()]);
        }

        try {
            $consultation = DB::transaction(function () use ($consultation, $start): Consultation {
                $settings = $this->lock();

                if (! (new SlotFinder($settings))->isAvailable($start, $consultation->id)) {
                    throw ValidationException::withMessages(['starts_at' => 'Ce créneau n\'est plus disponible. Choisissez-en un autre.']);
                }

                $consultation->forceFill([
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($settings->duration_minutes),
                    'slot_key' => Consultation::slotKey($start),
                    'day_reminder_sent_at' => null,
                    'hour_reminder_sent_at' => null,
                ])->save();

                return $consultation;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['starts_at' => 'Ce créneau vient d\'être réservé par quelqu\'un d\'autre. Choisissez-en un autre.']);
        }

        Mail::to($consultation->email)->send(new ConsultationMail($consultation, 'moved'));
        Mail::to($this->adminEmail())->send(new ConsultationAdminMail($consultation, 'moved'));

        return $consultation;
    }

    /**
     * Annulation par le visiteur (jusqu'au délai prévu) ou par l'admin (avec un message au visiteur).
     */
    public function cancel(Consultation $consultation, string $by, ?string $message = null): void
    {
        if (! $consultation->isBooked()) {
            return;
        }

        if ($by === 'visitor' && ! $consultation->canBeChangedByVisitor()) {
            throw ValidationException::withMessages(['consultation' => $this->deadlineMessage()]);
        }

        $consultation->forceFill([
            'status' => ConsultationStatus::Cancelled,
            'slot_key' => null,
            'cancelled_at' => now(),
            'cancelled_by' => $by,
            'cancellation_message' => filled($message) ? trim((string) $message) : null,
        ])->save();

        Mail::to($consultation->email)->send(new ConsultationMail($consultation, 'cancelled'));

        if ($by === 'visitor') {
            Mail::to($this->adminEmail())->send(new ConsultationAdminMail($consultation, 'cancelled'));
        }
    }

    public function deadlineMessage(): string
    {
        return 'Le rendez-vous ne peut plus être déplacé ni annulé en ligne : c\'est possible jusqu\'à '.ConsultationSettings::current()->manage_deadline_hours.' heures avant. Écrivez-nous sur WhatsApp.';
    }

    /**
     * Verrou sur la ligne des réglages : les réservations sont traitées l'une après l'autre.
     */
    private function lock(): ConsultationSettings
    {
        $settings = ConsultationSettings::current();

        return ConsultationSettings::query()->lockForUpdate()->findOrFail($settings->id);
    }

    private function ensureNoUpcomingBooking(string $phone, string $email): void
    {
        $existing = Consultation::query()
            ->upcoming()
            ->where(fn ($query) => $query->where('phone', $phone)->orWhere('email', $email))
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages(['email' => 'Vous avez déjà une consultation prévue le '.ConsultationTime::short($existing).'. Utilisez le lien reçu par e-mail pour la déplacer ou l\'annuler.']);
        }
    }

    private function matchingUser(string $phone, string $email): ?User
    {
        return User::query()->where('phone', $phone)->first()
            ?? User::query()->where('email', strtolower($email))->first();
    }

    private function adminEmail(): string
    {
        return (string) config('site.payments.admin_email');
    }
}
