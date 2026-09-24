<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demande de retrait des commissions par Mobile Money. Les frais d'envoi réels, saisis par
 * l'admin au moment du paiement, sont déduits du montant demandé.
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int $amount Montant demandé, en FCFA.
 * @property int|null $fee Frais d'envoi Mobile Money réels.
 * @property int|null $net_amount Montant reçu par l'affilié (demandé − frais).
 * @property WithdrawalStatus $status
 * @property string $payout_country
 * @property string $payout_method
 * @property string $payout_method_name
 * @property string $payout_phone
 * @property string $payout_name
 * @property string|null $transaction_id
 * @property string|null $rejection_reason
 * @property int|null $processed_by
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read Collection<int, AffiliateCommission> $commissions
 * @property-read User|null $processor
 */
class AffiliateWithdrawal extends Model
{
    protected $fillable = ['affiliate_id', 'amount', 'status', 'payout_country', 'payout_method', 'payout_method_name', 'payout_phone', 'payout_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'amount' => 'integer',
            'fee' => 'integer',
            'net_amount' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return HasMany<AffiliateCommission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === WithdrawalStatus::Requested;
    }

    public function formattedPhone(): string
    {
        return PhoneNumber::format($this->payout_phone);
    }
}
