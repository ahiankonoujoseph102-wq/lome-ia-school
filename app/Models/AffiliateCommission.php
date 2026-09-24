<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ligne du compte d'un affilié : commission sur une vente validée, ou déduction (montant négatif)
 * quand un achat est remboursé après le versement de sa commission.
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int|null $purchase_id
 * @property CommissionType $type
 * @property int $base_amount Prix payé, en FCFA.
 * @property int $rate Pourcentage appliqué.
 * @property int $amount Montant en FCFA (négatif pour une déduction).
 * @property CommissionStatus $status
 * @property Carbon|null $available_at Date à laquelle la commission devient disponible.
 * @property int|null $affiliate_withdrawal_id Retrait qui contient cette ligne (en cours ou payé).
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason purchase_rejected, purchase_refunded
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read Purchase|null $purchase
 * @property-read AffiliateWithdrawal|null $withdrawal
 */
class AffiliateCommission extends Model
{
    protected $fillable = ['affiliate_id', 'purchase_id', 'type', 'base_amount', 'rate', 'amount', 'status', 'available_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CommissionType::class,
            'status' => CommissionStatus::class,
            'base_amount' => 'integer',
            'rate' => 'integer',
            'amount' => 'integer',
            'available_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<AffiliateWithdrawal, $this>
     */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(AffiliateWithdrawal::class, 'affiliate_withdrawal_id');
    }

    public function isReversal(): bool
    {
        return $this->type === CommissionType::Reversal;
    }

    /**
     * Ce qui a été vendu, sans jamais rien dire de l'acheteur.
     */
    public function itemLabel(): string
    {
        $purchase = $this->purchase;

        if ($purchase === null) {
            return 'Achat supprimé';
        }

        $label = $purchase->isSessionSupplement() ? 'Supplément de session · '.$purchase->formation->title : $purchase->formation->title;

        return $this->isReversal() ? 'Déduction (remboursement) · '.$label : $label;
    }

    public function formattedAmount(): string
    {
        return ($this->amount < 0 ? '− ' : '').Money::fcfa(abs($this->amount));
    }
}
