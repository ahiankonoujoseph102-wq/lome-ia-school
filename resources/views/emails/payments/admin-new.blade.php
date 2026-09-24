@extends('emails.layout')

@section('title', 'Nouveau paiement à vérifier')
@section('heading', 'Nouveau paiement à vérifier')
@section('intro')
    {{ $purchase->status->label() }} · {{ $purchase->formatted_amount }} · {{ $purchase->payment_method_name }}
@endsection

@section('content')
    @if ($purchase->viaRepresentative())
        <p style="margin:0 0 12px;padding:10px 14px;background:#fff7e6;border-radius:12px;color:#8a5a00;"><strong>À confirmer avec le représentant</strong> ({{ $purchase->paymentMethod()?->countryName() }}).</p>
    @endif
    @include('emails.payments.details', ['extra' => [
        'Élève' => $purchase->user?->name.' · '.$purchase->user?->email,
        'Numéro qui a payé' => $purchase->payer_phone,
        'Nom de l\'expéditeur' => $purchase->payer_name,
    ]])
    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('admin.payments.index') }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Vérifier le paiement</a>
    </p>
@endsection
