<?php

namespace App\Models;

use App\Payments\PaymentMethod;
use App\Payments\PaymentMethods;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Affilié : un inscrit qui a activé le programme d'affiliation (achat non obligatoire).
 *
 * @property int $id
 * @property int $user_id
 * @property string $code Code des liens (?ref=CODE).
 * @property Carbon $terms_accepted_at Acceptation des conditions du programme.
 * @property Carbon|null $suspended_at Suspendu par l'admin : liens inactifs, aucune nouvelle commission, aucun retrait.
 * @property string|null $suspension_reason
 * @property string|null $payout_country Coordonnées de retrait : pays (tg, bj, bf).
 * @property string|null $payout_method Opérateur : clé d'un moyen de paiement (config site.payments.methods).
 * @property string|null $payout_phone Numéro Mobile Money au format international.
 * @property string|null $payout_name Nom du titulaire du compte Mobile Money.
 * @property Carbon|null $payout_updated_at
 * @property Carbon|null $payout_changed_at Dernière modification de coordonnées déjà enregistrées.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, AffiliateClick> $clicks
 * @property-read Collection<int, AffiliateCommission> $commissions
 * @property-read Collection<int, AffiliateWithdrawal> $withdrawals
 * @property-read Collection<int, User> $referrals
 */
class Affiliate extends Model
{
    /** Caractères des codes : sans 0/O ni 1/I/L, pour éviter les confusions. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = ['user_id', 'code', 'terms_accepted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terms_accepted_at' => 'datetime',
            'suspended_at' => 'datetime',
            'payout_updated_at' => 'datetime',
            'payout_changed_at' => 'datetime',
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
     * @return HasMany<AffiliateClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    /**
     * @return HasMany<AffiliateCommission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    /**
     * @return HasMany<AffiliateWithdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(AffiliateWithdrawal::class);
    }

    /**
     * Inscrits arrivés par un lien de cet affilié.
     *
     * @return HasMany<User, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_affiliate_id');
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function hasPayoutDetails(): bool
    {
        return $this->payout_method !== null && $this->payout_phone !== null && filled($this->payout_name);
    }

    public function payoutMethod(): ?PaymentMethod
    {
        return PaymentMethods::find($this->payout_method);
    }

    public function payoutSummary(): string
    {
        if (! $this->hasPayoutDetails()) {
            return 'Non renseignées';
        }

        return ($this->payoutMethod()->name ?? $this->payout_method).' · '.PhoneNumber::format($this->payout_phone).' · '.$this->payout_name;
    }

    /**
     * Lien d'affiliation vers l'accueil ou vers une formation.
     */
    public function link(?Formation $formation = null): string
    {
        $url = $formation ? route('formations.show', $formation) : rtrim(route('home'), '/').'/';

        return $url.'?ref='.$this->code;
    }

    public static function generateCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < 7; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Code saisi dans un lien, sans tenir compte des majuscules ni des espaces.
     */
    public static function findByCode(?string $code): ?self
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '' || strlen($code) > 20 || ! ctype_alnum($code)) {
            return null;
        }

        return self::query()->where('code', $code)->first();
    }
}
