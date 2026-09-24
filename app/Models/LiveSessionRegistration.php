<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inscription d'un élève à une session en présentiel (ou place en liste d'attente).
 *
 * @property int $id
 * @property int $live_session_id
 * @property int $user_id
 * @property RegistrationStatus $status
 * @property int|null $purchase_id Paiement du supplément.
 * @property Carbon|null $reserved_until Place réservée en attendant la preuve de paiement du supplément.
 * @property Carbon|null $waitlisted_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason student, access_revoked, blocked, payment_rejected, payment_expired, session_cancelled
 * @property Carbon|null $attended_at Présent le jour de la session (feuille de présence).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LiveSession $session
 * @property-read User $user
 * @property-read Purchase|null $purchase
 */
class LiveSessionRegistration extends Model
{
    protected $fillable = ['live_session_id', 'user_id', 'status', 'purchase_id', 'reserved_until', 'waitlisted_at', 'confirmed_at', 'cancelled_at', 'cancel_reason', 'attended_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'reserved_until' => 'datetime',
            'waitlisted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LiveSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function isActive(): bool
    {
        return $this->status !== RegistrationStatus::Cancelled;
    }

    /**
     * Place réservée, supplément pas encore payé (preuve à envoyer).
     */
    public function awaitsPayment(): bool
    {
        return $this->status === RegistrationStatus::Reserved && $this->purchase_id === null;
    }

    /**
     * Place réservée, preuve de paiement en cours de vérification.
     */
    public function paymentUnderReview(): bool
    {
        return $this->status === RegistrationStatus::Reserved && $this->purchase_id !== null;
    }

    /**
     * Rang dans la liste d'attente (1 = première personne).
     */
    public function waitlistPosition(): ?int
    {
        if ($this->status !== RegistrationStatus::Waitlisted) {
            return null;
        }

        return self::query()
            ->where('live_session_id', $this->live_session_id)
            ->where('status', RegistrationStatus::Waitlisted->value)
            ->where(fn ($query) => $query->where('waitlisted_at', '<', $this->waitlisted_at)
                ->orWhere(fn ($query) => $query->where('waitlisted_at', $this->waitlisted_at)->where('id', '<', $this->id)))
            ->count() + 1;
    }
}
