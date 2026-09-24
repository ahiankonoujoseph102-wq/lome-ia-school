<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Enums\SessionFormat;
use App\Enums\SessionPriceType;
use App\Support\Money;
use Carbon\CarbonInterface;
use Database\Factories\LiveSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Session ponctuelle d'une formation : en direct (Google Meet) ou en présentiel à Lomé.
 *
 * @property int $id
 * @property int $formation_id
 * @property int|null $created_by
 * @property SessionFormat $format
 * @property string|null $title
 * @property string|null $description
 * @property Carbon $starts_at Heure de Lomé (GMT).
 * @property int $duration_minutes
 * @property Carbon $ends_at
 * @property string|null $meet_url Lien Google Meet (en direct) : jamais affiché aux non-acheteurs.
 * @property string|null $location Lieu (présentiel).
 * @property int|null $capacity Nombre de places (présentiel).
 * @property SessionPriceType $price_type
 * @property int|null $supplement_amount Supplément en FCFA (présentiel).
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property int|null $announcement_message_id Annonce épinglée dans le groupe de la formation.
 * @property Carbon|null $day_reminder_sent_at
 * @property Carbon|null $hour_reminder_sent_at
 * @property Carbon|null $announcement_unpinned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Formation $formation
 * @property-read User|null $creator
 * @property-read ChatMessage|null $announcement
 * @property-read Collection<int, LiveSessionRegistration> $registrations
 */
class LiveSession extends Model
{
    /** @use HasFactory<LiveSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'formation_id',
        'created_by',
        'format',
        'title',
        'description',
        'starts_at',
        'duration_minutes',
        'meet_url',
        'location',
        'capacity',
        'price_type',
        'supplement_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => SessionFormat::class,
            'price_type' => SessionPriceType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'supplement_amount' => 'integer',
            'cancelled_at' => 'datetime',
            'day_reminder_sent_at' => 'datetime',
            'hour_reminder_sent_at' => 'datetime',
            'announcement_unpinned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Fin de la session calculée une fois pour toutes (recherches « à venir » et « terminées »).
        static::saving(function (self $session): void {
            $session->ends_at = $session->starts_at->copy()->addMinutes($session->duration_minutes);
        });
    }

    /**
     * @return BelongsTo<Formation, $this>
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'announcement_message_id');
    }

    /**
     * @return HasMany<LiveSessionRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(LiveSessionRegistration::class);
    }

    /**
     * Sessions non annulées et pas encore terminées, la plus proche en premier.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at')->where('ends_at', '>', now())->orderBy('starts_at');
    }

    /**
     * Sessions terminées ou annulées, la plus récente en premier.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNotNull('cancelled_at')->orWhere('ends_at', '<=', now()))->orderByDesc('starts_at');
    }

    public function isOnline(): bool
    {
        return $this->format === SessionFormat::Online;
    }

    public function isOnsite(): bool
    {
        return $this->format === SessionFormat::Onsite;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function hasStarted(): bool
    {
        return $this->starts_at->isPast();
    }

    public function isFinished(): bool
    {
        return $this->ends_at->isPast();
    }

    public function isUpcoming(): bool
    {
        return ! $this->isCancelled() && ! $this->isFinished();
    }

    /**
     * Inscriptions encore ouvertes : présentiel, pas annulée, pas encore commencée.
     */
    public function acceptsRegistrations(): bool
    {
        return $this->isOnsite() && ! $this->isCancelled() && ! $this->hasStarted();
    }

    public function requiresPayment(): bool
    {
        return $this->isOnsite() && $this->price_type === SessionPriceType::Supplement && (int) $this->supplement_amount > 0;
    }

    public function formattedSupplement(): string
    {
        return Money::fcfa((int) $this->supplement_amount);
    }

    public function priceLabel(): string
    {
        return $this->requiresPayment()
            ? 'Supplément de '.$this->formattedSupplement()
            : SessionPriceType::Included->label();
    }

    public function seatsTaken(): int
    {
        return $this->registrations()->whereIn('status', RegistrationStatus::seatHolding())->count();
    }

    public function seatsLeft(): int
    {
        return max(0, (int) $this->capacity - $this->seatsTaken());
    }

    public function isFull(): bool
    {
        return $this->isOnsite() && $this->seatsLeft() === 0;
    }

    public function waitlistCount(): int
    {
        return $this->registrations()->where('status', RegistrationStatus::Waitlisted->value)->count();
    }

    /**
     * Titre affiché : celui choisi, sinon « Session en direct » / « Session en présentiel ».
     */
    public function displayTitle(): string
    {
        return filled($this->title)
            ? (string) $this->title
            : ($this->isOnline() ? 'Session en direct' : 'Session en présentiel');
    }

    /**
     * Le bouton « Rejoindre la session » est actif quelques minutes avant le début et jusqu'à la fin.
     */
    public function canJoinNow(): bool
    {
        return $this->isOnline()
            && ! $this->isCancelled()
            && filled($this->meet_url)
            && now()->greaterThanOrEqualTo($this->joinOpensAt())
            && now()->lessThan($this->ends_at);
    }

    public function joinOpensAt(): CarbonInterface
    {
        return $this->starts_at->copy()->subMinutes((int) config('live_sessions.join_window_minutes', 15));
    }

    /**
     * Limite pour annuler une inscription en présentiel.
     */
    public function cancellationDeadline(): CarbonInterface
    {
        return $this->starts_at->copy()->subHours((int) config('live_sessions.cancel_deadline_hours', 24));
    }

    public function durationLabel(): string
    {
        $hours = intdiv($this->duration_minutes, 60);
        $minutes = $this->duration_minutes % 60;

        $label = match (true) {
            $hours === 0 => $minutes.' min',
            $minutes === 0 => $hours.' h',
            default => $hours.' h '.str_pad((string) $minutes, 2, '0', STR_PAD_LEFT),
        };

        // Espaces insécables : « 1 h 30 » reste sur une seule ligne.
        return str_replace(' ', "\u{00A0}", $label);
    }
}
