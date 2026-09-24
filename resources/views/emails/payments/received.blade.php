@extends('emails.layout')

@section('title', 'Preuve de paiement reçue')
@section('heading', 'Merci '.$purchase->user?->first_name.', nous avons bien reçu votre preuve de paiement')
@section('intro')
    @if ($purchase->status === \App\Enums\PurchaseStatus::Provisional)
        {{ $purchase->isSessionSupplement() ? 'Votre place pour la session est confirmée.' : 'Votre formation est déjà ouverte.' }} Votre paiement sera vérifié sous peu.
    @else
        {{ $purchase->isSessionSupplement() ? 'Votre place est réservée : elle sera confirmée' : 'Votre accès sera ouvert' }} dès que votre paiement aura été vérifié, {{ config('site.payments.verification_hours') }}.
    @endif
@endsection

@section('content')
    @include('emails.payments.details')
    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('student.purchases.show', $purchase) }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Suivre mon achat</a>
    </p>
@endsection
