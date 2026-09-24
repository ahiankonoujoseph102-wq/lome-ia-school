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
 * @property int|null $live_session_id Supplément d'une session en présentiel (sinon : achat de la formation).
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
 * @property Carbon|null $refund_due_at Supplément à rembourser : inscription annulée par l'élève dans le délai.
 * @property string|null $affiliate_code Code de l'affilié dont le lien a amené l'achat (dernier lien cliqué, 30 jours).
 * @property int|null $discount_amount
 * @property string|null $gateway_reference Référence de la passerelle (FedaPay, plus tard).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Formation $formation
 * @property-read LiveSession|null $liveSession
 * @property-read string $item_label Ce qui a été payé : titre de la formation, ou session et formation.
 * @property-read User|null $verifier
 * @property-read Enrollment|null $enrollment
 * @property-read Affiliate|null $affiliate
 * @property-read AffiliateCommission|null $affiliateCommission
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
        'live_session_id',
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
        'affiliate_code',
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
            'refund_due_at' => 'datetime',
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
     * Session en présentiel dont ce paiement règle le supplément.
     *
     * @return BelongsTo<LiveSession, $this>
     */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    /**
     * Affilié dont le lien a amené cet achat.
     *
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_code', 'code');
    }

    /**
     * Commission d'affiliation sur cet achat (créée à la validation du paiement).
     *
     * @return HasOne<AffiliateCommission, $this>
     */
    public function affiliateCommission(): HasOne
    {
        return $this->hasOne(AffiliateCommission::class)->where('type', 'sale');
    }

    /**
     * Paiement du supplément d'une session en présentiel (et non achat d'une formation).
     */
    public function isSessionSupplement(): bool
    {
        return $this->live_session_id !== null;
    }

    /**
     * Intitulé de la ligne « Formation » ou « Session » sur les écrans, e-mails et reçus.
     */
    public function itemHeading(): string
    {
        return $this->isSessionSupplement() ? 'Session' : 'Formation';
    }

    /**
     * @return Attribute<string, never>
     */
    protected function itemLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $session = $this->liveSession;

            return $session === null
                ? $this->formation->title
                : 'Supplément · '.$session->displayTitle().' du '.$session->starts_at->translatedFormat('j F Y').' · '.$this->formation->title;
        });
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
     * Suppléments de session à rembourser : session annulée par l'école, ou inscription annulée
     * par l'élève dans le délai autorisé. Un élève absent sans avoir annulé n'est pas remboursé.
     *
     * @param  Builder<Purchase>  $query
     */
    #[Scope]
    protected function refundDue(Builder $query): void
    {
        $query->whereNotNull('live_session_id')
            ->whereIn('status', PurchaseStatus::accessGranting())
            ->where(fn (Builder $query) => $query
                ->whereNotNull('refund_due_at')
                ->orWhereHas('liveSession', fn (Builder $query) => $query->whereNotNull('cancelled_at')));
    }

    /**
     * Motif du remboursement d'un supplément, affiché dans la liste de l'admin.
     */
    public function refundReason(): string
    {
        if ($this->liveSession?->isCancelled()) {
            return 'Session annulée par l\'école';
        }

        return 'Inscription annulée par l\'élève'.($this->refund_due_at ? ' le '.$this->refund_due_at->translatedFormat('j F à H\hi') : '');
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
