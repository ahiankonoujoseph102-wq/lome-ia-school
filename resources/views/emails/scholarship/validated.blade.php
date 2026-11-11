@extends('emails.layout')

@section('title', 'Tu es retenu(e)')
@section('heading', 'Félicitations, tu es retenu(e) !')
@section('intro', 'Bourse « '.$formationTitle.' »')

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Bonjour {{ $application->firstName() }},</p>
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Ton paiement est vérifié : tu fais partie des personnes retenues pour la formation « {{ $formationTitle }} ». Ton accès est ouvert et tu as été ajouté(e) au groupe de la formation.</p>
    @if ($newAccount)
        <p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#1e2340;">Ton compte Lomé IA School est créé avec cette adresse ({{ $application->email }}). Touche le bouton ci-dessous pour choisir ton mot de passe, puis commence la formation.</p>
        <p style="margin:0;text-align:center;">
            <a href="{{ $accessUrl }}" style="display:inline-block;background:#f5c542;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Choisir mon mot de passe</a>
        </p>
        <p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#7a8099;text-align:center;">Ce bouton fonctionne pendant {{ \App\Mail\ScholarshipMail::ACCESS_LINK_DAYS }} jours. Ensuite, utilise « Mot de passe oublié » sur la page de connexion, avec cette adresse e-mail.</p>
    @else
        <p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#1e2340;">Tu as déjà un compte Lomé IA School avec cette adresse ({{ $application->email }}) : connecte-toi comme d'habitude pour commencer.</p>
        <p style="margin:0;text-align:center;">
            <a href="{{ route('login') }}" style="display:inline-block;background:#f5c542;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Me connecter</a>
        </p>
    @endif
@endsection
