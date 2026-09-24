<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Payments\PaymentMethod;
use App\Payments\PaymentMethods;
use App\Support\Money;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Achat d'une formation par un élève.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $formation_id
 * @property int $amount Montant en FCFA.
 * @property string $currency
 * @property PaymentGateway $gateway
 * @property string|null $payment_method Clé du moyen de paiement (config site.payments.methods).
 * @property string|null $payment_method_name
 * @property string|null $payment_number
 * @property string|null $payment_beneficiary
 * @property string|null $country
 * @property string|null $payer_phone
 * @property string|null $payer_name
 * @property string|null $transaction_id
 * @property string|null $proof_path Fichier sur le disque privé « payment_proofs ».
 * @property string|null $proof_hash
 * @property PurchaseStatus $status
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $rejection_reason
 * @property Carbon|null $refunded_at
 * @property string|null $affiliate_code Étape 8.
 * @property int|null $discount_amount
 * @property string|null $gateway_reference Référence de la passerelle (FedaPay, plus tard).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Formation $formation
 * @property-read User|null $verifier
 * @property-read Enrollment|null $enrollment
 * @property-read string $formatted_amount
 * @property-read string $receipt_number
 */
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'formation_id',
        'amount',
        'currency',
        'gateway',
        'payment_method',
        'payment_method_name',
        'payment_number',
        'payment_beneficiary',
        'country',
        'payer_phone',
        'payer_name',
        'transaction_id',
        'proof_path',
        'proof_hash',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'discount_amount' => 'integer',
            'gateway' => PaymentGateway::class,
            'status' => PurchaseStatus::class,
            'verified_at' => 'datetime',
            'refunded_at' => 'datetime',
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
     * Admin qui a validé ou refusé le paiement.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Accès ouvert par cet achat.
     *
     * @return HasOne<Enrollment, $this>
     */
    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    /**
     * Paiements à vérifier par l'équipe.
     *
     * @param  Builder<Purchase>  $query
     */
    #[Scope]
    protected function awaitingVerification(Builder $query): void
    {
        $query->whereIn('status', PurchaseStatus::unverified());
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::get(fn (): string => Money::fcfa($this->amount));
    }

    /**
     * Numéro du reçu, stable : LIS-année de l'achat-numéro.
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function receiptNumber(): Attribute
    {
        return Attribute::get(fn (): string => sprintf('LIS-%s-%05d', ($this->created_at ?? now())->format('Y'), $this->id));
    }

    public function paymentMethod(): ?PaymentMethod
    {
        return PaymentMethods::find($this->payment_method);
    }

    /**
     * Payé à un représentant (Burkina Faso, Bénin) : à confirmer avec lui.
     */
    public function viaRepresentative(): bool
    {
        return (bool) $this->paymentMethod()?->viaRepresentative();
    }
}
