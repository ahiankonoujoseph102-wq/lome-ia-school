@php
    use App\Scholarships\ScholarshipCampaign;
    use App\Support\Money;

    $closesAt = ScholarshipCampaign::closesAt();
@endphp
@extends('emails.layout')

@section('title', 'Candidature reçue')
@section('heading', 'Candidature reçue')
@section('intro', 'Bourse « '.$formationTitle.' »')

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Bonjour {{ $application->firstName() }},</p>
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Nous avons bien reçu ta candidature et la capture de ton paiement de {{ Money::fcfa($application->amount) }}. <strong>Ta place est réservée</strong> pendant que nous vérifions ton paiement.</p>
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Tu recevras un e-mail dès que c'est fait{{ $closesAt ? ', au plus tard après la clôture ('.$closesAt->translatedFormat('l j F à H \h i').', heure de Lomé)' : '' }}. Si tu es retenu(e), cet e-mail contiendra ton accès à la formation et au groupe.</p>
    <p style="margin:0;font-size:15px;line-height:1.6;color:#1e2340;">Si tu n'es pas retenu(e), tes {{ Money::fcfa($application->amount) }} te sont remboursés.</p>
@endsection
