<?php

namespace App\Models;

use App\Enums\ConsultationAttendance;
use App\Enums\ConsultationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Rendez-vous de consultation gratuite (30 minutes, en direct en ligne par appel vidéo), réservé sans compte.
 *
 * @property int $id
 * @property int|null $user_id Compte élève correspondant (même numéro WhatsApp ou même e-mail).
 * @property string $name
 * @property string $phone Numéro WhatsApp au format international.
 * @property string $email
 * @property string $topic Besoin ou sujet.
 * @property int|null $formation_id Formation qui l'intéresse.
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $slot_key Créneau réservé (vide si annulé).
 * @property ConsultationStatus $status
 * @property Carbon|null $cancelled_at
 * @property string|null $cancelled_by visitor, admin
 * @property string|null $cancellation_message Message de l'admin au visiteur.
 * @property string $manage_token Jeton du lien de gestion (chiffré en base).
 * @property string $manage_token_hash
 * @property ConsultationAttendance|null $attendance
 * @property string|null $admin_notes Notes privées de l'admin.
 * @property int|null $recommended_formation_id
 * @property Carbon|null $recommendation_sent_at
 * @property Carbon|null $day_reminder_sent_at
 * @property Carbon|null $hour_reminder_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Formation|null $formation
 * @property-read Formation|null $recommendedFormation
 */
class Consultation extends Model
{
    protected $fillable = ['user_id', 'name', 'phone', 'email', 'topic', 'formation_id', 'starts_at', 'ends_at', 'status'];

    protected $hidden = ['manage_token', 'manage_token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ConsultationStatus::class,
            'attendance' => ConsultationAttendance::class,
            'cancelled_at' => 'datetime',
            'recommendation_sent_at' => 'datetime',
            'day_reminder_sent_at' => 'datetime',
            'hour_reminder_sent_at' => 'datetime',
            'manage_token' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Formation, $this>
     */
    public function formation(): BelongsTo
    {
        return $this->belongsTo(Formation::class);
    }

    /**
     * @return BelongsTo<Formation, $this>
     */
    public function recommendedFormation(): BelongsTo
    {
        return $this->belongsTo(Formation::class, 'recommended_formation_id');
    }

    /**
     * Rendez-vous confirmés pas encore terminés.
     *
     * @param  Builder<Consultation>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('status', ConsultationStatus::Booked)->where('ends_at', '>', now());
    }

    /**
     * Nouveau jeton du lien de gestion (renvoyé en clair une seule fois, pour l'adresse).
     */
    public function issueManageToken(): string
    {
        $token = Str::random(48);
        $this->forceFill(['manage_token' => $token, 'manage_token_hash' => self::hashToken($token)]);

        return $token;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return strlen($token) === 48 ? self::query()->where('manage_token_hash', self::hashToken($token))->first() : null;
    }

    public static function slotKey(CarbonInterface $start): string
    {
        return $start->copy()->utc()->format('Y-m-d H:i');
    }

    public function manageUrl(): string
    {
        return route('consultation.manage', $this->manage_token);
    }

    public function isBooked(): bool
    {
        return $this->status === ConsultationStatus::Booked;
    }

    public function isCancelled(): bool
    {
        return $this->status === ConsultationStatus::Cancelled;
    }

    public function isPast(): bool
    {
        return $this->ends_at->isPast();
    }

    /**
     * Le visiteur peut encore déplacer ou annuler (jusqu'au délai prévu avant le rendez-vous).
     */
    public function canBeChangedByVisitor(): bool
    {
        return $this->isBooked() && now()->lt($this->changeDeadline());
    }

    public function changeDeadline(): CarbonInterface
    {
        return $this->starts_at->copy()->subHours(ConsultationSettings::current()->manage_deadline_hours);
    }
}
