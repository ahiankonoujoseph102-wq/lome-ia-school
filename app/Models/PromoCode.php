<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Code promo : réduction en pourcentage ou montant fixe, sur toutes les formations ou certaines,
 * avec dates de validité et nombres maximums d'utilisations (au total et par élève).
 *
 * @property int $id
 * @property string $code En majuscules, sans espaces.
 * @property string|null $description
 * @property string $type « percent » ou « fixed »
 * @property int $value Pourcentage, ou montant en FCFA.
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $max_uses
 * @property int|null $max_uses_per_user
 * @property bool $all_formations
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read EloquentCollection<int, Formation> $formations
 * @property-read EloquentCollection<int, Purchase> $purchases
 */
class PromoCode extends Model
{
    /**
     * @var array<string, string>
     */
    public const array TYPES = [
        'percent' => 'Pourcentage',
        'fixed' => 'Montant fixe (FCFA)',
    ];

    protected $fillable = ['code', 'description', 'type', 'value', 'starts_at', 'ends_at', 'max_uses', 'max_uses_per_user', 'all_formations', 'is_active'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['all_formations' => true, 'is_active' => true];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'all_formations' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Formation, $this>
     */
    public function formations(): BelongsToMany
    {
        return $this->belongsToMany(Formation::class, 'formation_promo_code');
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Utilisations qui comptent : achats envoyés et pas refusés ni remboursés.
     *
     * @return HasMany<Purchase, $this>
     */
    public function uses(): HasMany
    {
        return $this->purchases()->whereIn('status', [PurchaseStatus::Provisional, PurchaseStatus::Pending, PurchaseStatus::Validated]);
    }

    public static function normalize(?string $code): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', (string) $code));
    }

    public function appliesTo(Formation $formation): bool
    {
        return $this->all_formations || $this->formations->contains('id', $formation->id);
    }

    /**
     * Remise en FCFA pour un prix donné (arrondie à l'inférieur, jamais plus que le prix).
     */
    public function discountFor(int $price): int
    {
        $discount = $this->type === 'percent' ? intdiv($price * $this->value, 100) : $this->value;

        return max(0, min($price, $discount));
    }

    public function valueLabel(): string
    {
        return $this->type === 'percent' ? '-'.$this->value.' %' : '-'.Money::fcfa($this->value);
    }

    /**
     * État affiché dans l'admin.
     */
    public function stateLabel(): string
    {
        return match (true) {
            ! $this->is_active => 'Inactif',
            $this->starts_at?->isFuture() ?? false => 'Programmé',
            $this->ends_at?->isPast() ?? false => 'Expiré',
            default => 'Actif',
        };
    }
}
