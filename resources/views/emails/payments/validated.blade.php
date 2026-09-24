@extends('emails.layout')

@section('title', 'Paiement validé')
@section('heading', 'Votre paiement est validé')
@section('intro', 'Votre accès à la formation « '.$purchase->formation->title.' » est définitif. Voici votre reçu.')

@section('content')
    @include('emails.payments.details', ['extra' => ['Date de validation' => $purchase->verified_at?->translatedFormat('j F Y')]])
    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('student.formations.show', $purchase->formation) }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Ouvrir ma formation</a>
    </p>
    <p style="margin:14px 0 0;text-align:center;font-size:14px;">
        <a href="{{ route('student.purchases.receipt', $purchase) }}" style="color:#6d28d9;">Voir et imprimer mon reçu</a>
    </p>
@endsection
