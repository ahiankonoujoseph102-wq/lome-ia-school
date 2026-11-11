<?php

namespace App\Models;

use App\Payments\ProofImageProcessor;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Candidature à la bourse « Créer des agents IA », envoyée sans compte avec la capture du paiement des frais
 * d'inscription (App\Scholarships\ScholarshipSubmitter). Statuts : « reserved » (place réservée, paiement à vérifier
 * par Joseph), « validated » (paiement vérifié : compte créé, accès et groupe ouverts), « refused » (place libérée ;
 * à rembourser si le paiement avait bien été reçu).
 *
 * @property int $id
 * @property string $campaign
 * @property string $name
 * @property string $phone
 * @property string $email
 * @property string $gender
 * @property int $age
 * @property string $city
 * @property string $level
 * @property string $tools
 * @property string $device
 * @property string $motivation
 * @property string $first_agent
 * @property bool $available
 * @property int $amount
 * @property string $payment_method
 * @property string $payment_method_name
 * @property string|null $payment_number
 * @property string|null $country
 * @property string $proof_path
 * @property string $proof_hash
 * @property string $status
 * @property bool $refund_due
 * @property Carbon|null $refunded_at
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property int|null $user_id
 * @property int|null $purchase_id
 * @property Carbon|null $created_at
 * @property-read User|null $user
 * @property-read User|null $decider
 */
class ScholarshipApplication extends Model
{
    use Prunable;

    /** Candidatures non validées (et leur capture) effacées après ce nombre de mois ; les validées restent avec l'achat. */
    public const int RETENTION_MONTHS = 12;

    public const string RESERVED = 'reserved';

    public const string VALIDATED = 'validated';

    public const string REFUSED = 'refused';

    /** Statuts qui occupent une place. */
    public const array HOLDS_SEAT = [self::RESERVED, self::VALIDATED];

    public const array GENDERS = ['female' => 'Femme', 'male' => 'Homme'];

    public const array LEVELS = [
        'beginner' => 'Débutant',
        'uses_ai' => 'J\'utilise déjà ChatGPT ou un autre outil IA',
        'built' => 'J\'ai déjà créé quelque chose avec l\'IA',
    ];

    public const array DEVICES = ['computer' => 'J\'ai un ordinateur', 'smartphone' => 'Seulement un smartphone'];

    protected $fillable = [
        'campaign', 'name', 'phone', 'email', 'gender', 'age', 'city', 'level', 'tools', 'device', 'motivation',
        'first_agent', 'available', 'amount', 'payment_method', 'payment_method_name', 'payment_number', 'country',
        'proof_path', 'proof_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'available' => 'boolean',
            'amount' => 'integer',
            'refund_due' => 'boolean',
            'refunded_at' => 'datetime',
            'decided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeHoldingSeat(Builder $query): Builder
    {
        return $query->whereIn('status', self::HOLDS_SEAT);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('status', '!=', self::VALIDATED)->where('created_at', '<', now()->subMonths(self::RETENTION_MONTHS));
    }

    protected function pruning(): void
    {
        Storage::disk(ProofImageProcessor::DISK)->delete($this->proof_path);
    }

    public function isReserved(): bool
    {
        return $this->status === self::RESERVED;
    }

    public function isValidated(): bool
    {
        return $this->status === self::VALIDATED;
    }

    public function isRefused(): bool
    {
        return $this->status === self::REFUSED;
    }

    public function genderLabel(): string
    {
        return self::GENDERS[$this->gender] ?? $this->gender;
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? $this->level;
    }

    public function deviceLabel(): string
    {
        return self::DEVICES[$this->device] ?? $this->device;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::RESERVED => 'Place réservée : paiement à vérifier',
            self::VALIDATED => 'Validée',
            default => $this->refund_due ? ($this->refunded_at ? 'Refusée, remboursée' : 'Refusée : à rembourser') : 'Refusée',
        };
    }

    public function firstName(): string
    {
        return (string) (preg_split('/\s+/u', trim($this->name))[0] ?? $this->name);
    }

    public function phoneLabel(): string
    {
        return PhoneNumber::format($this->phone);
    }

    /**
     * Lien wa.me vers la personne, avec un message prêt à envoyer (ou sans message).
     */
    public function whatsappUrl(?string $message = null): string
    {
        $digits = preg_replace('/\D+/', '', $this->phone) ?? '';

        return 'https://wa.me/'.$digits.($message !== null ? '?text='.rawurlencode($message) : '');
    }
}
