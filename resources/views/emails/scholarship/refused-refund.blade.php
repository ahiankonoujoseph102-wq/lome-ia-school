@php
    use App\Support\Money;
    use App\Support\WhatsApp;
@endphp
@extends('emails.layout')

@section('title', 'Ta candidature')
@section('heading', 'Ta candidature n\'a pas été retenue')
@section('intro', 'Bourse « '.$formationTitle.' »')

@section('content')
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Bonjour {{ $application->firstName() }},</p>
    <p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#1e2340;">Merci pour ta candidature. Elle n'a pas été retenue cette fois-ci.</p>
    <p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#1e2340;">Comme annoncé, <strong>tes {{ Money::fcfa($application->amount) }} te sont remboursés</strong> par Mobile Money. Nous te contactons sur WhatsApp pour cela.</p>
    <p style="margin:0;text-align:center;">
        <a href="{{ WhatsApp::url() }}" style="display:inline-block;background:#25d366;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Écrire à l'école sur WhatsApp</a>
    </p>
@endsection
