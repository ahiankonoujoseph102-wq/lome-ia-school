@extends('emails.layout')

@section('title', 'Paiement refusé')
@section('heading', 'Votre paiement n\'a pas pu être validé')
@section('intro', 'Nous n\'avons pas pu confirmer la réception de votre paiement pour « '.$purchase->item_label.' ».')

@section('content')
    <p style="margin:0;"><strong>Motif :</strong> {{ $purchase->rejection_reason }}</p>
    <p style="margin:12px 0 0;">{{ $purchase->isSessionSupplement() ? 'Votre place réservée pour la session a été libérée.' : 'L\'accès à la formation a été retiré.' }} Si vous pensez qu'il s'agit d'une erreur, répondez à cet e-mail avec votre SMS de confirmation : nous vérifierons à nouveau.</p>
    @include('emails.payments.details')
@endsection
