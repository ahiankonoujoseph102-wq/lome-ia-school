{{-- Explication du statut d'un achat, pour l'élève. --}}
@php use App\Enums\PurchaseStatus; @endphp

@switch($purchase->status)
    @case(PurchaseStatus::Provisional)
        Votre formation est ouverte. Votre paiement sera vérifié sous peu.
        @break
    @case(PurchaseStatus::Pending)
        Votre accès sera ouvert dès que votre paiement aura été vérifié, {{ config('site.payments.verification_hours') }}. Vous serez prévenu par e-mail.
        @break
    @case(PurchaseStatus::Validated)
        Paiement confirmé le {{ $purchase->verified_at?->translatedFormat('j F Y') }}. Votre accès est définitif.
        @break
    @case(PurchaseStatus::Rejected)
        Paiement refusé{{ $purchase->rejection_reason ? ' : '.$purchase->rejection_reason : '' }}. L'accès à la formation a été retiré.
        @break
    @case(PurchaseStatus::Refunded)
        Achat remboursé le {{ $purchase->refunded_at?->translatedFormat('j F Y') }}.
        @break
@endswitch
