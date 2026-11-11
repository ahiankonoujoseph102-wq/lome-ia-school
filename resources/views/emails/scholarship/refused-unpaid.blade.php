@php
    use App\Support\Money;
    use App\Support\WhatsApp;
@endphp
@extends('emails.layout')

@section('title', 'Ta candidature')
@section('heading', 'Paiement introuvable')
@section('intro', 'Bourse « '.$formationTitle.' »')

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Bonjour {{ $application->firstName() }},</p>
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Nous n'avons pas trouvé ton paiement de {{ Money::fcfa($application->amount) }} sur notre compte {{ $application->payment_method_name }}. Ta candidature n'a donc pas été retenue et ta place est libérée.</p>
    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#1e2340;">Si tu as bien payé, écris-nous sur WhatsApp avec le message de confirmation de ton paiement : nous vérifions de nouveau.</p>
    <p style="margin:0;text-align:center;">
        <a href="{{ WhatsApp::url() }}" style="display:inline-block;background:#25d366;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Écrire à l'école sur WhatsApp</a>
    </p>
@endsection
