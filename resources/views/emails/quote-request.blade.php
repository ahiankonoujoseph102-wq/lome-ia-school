@php
    $rows = array_filter([
        'Nom' => $quote->name,
        'Structure' => $quote->organization,
        'Besoin' => $quote->serviceLabel(),
        'E-mail' => $quote->email,
        'WhatsApp' => $quote->phoneLabel(),
        'Reçue le' => $quote->created_at?->translatedFormat('l j F Y à H \h i').' (heure de Lomé)',
    ]);
@endphp
@extends('emails.layout')

@section('title', 'Nouvelle demande de devis')
@section('heading', 'Nouvelle demande de devis')
@section('intro', $quote->name.' souhaite un devis : '.$quote->serviceLabel().'.')

@section('content')
    <p style="margin:0 0 8px;font-size:13px;color:#7a8099;">Message</p>
    <p style="margin:0 0 22px;padding:14px 16px;background:#f7f7fb;border-radius:14px;font-size:15px;line-height:1.6;color:#1e2340;white-space:pre-line;">{{ $quote->message }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:130px;vertical-align:top;">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;word-break:break-word;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:26px 0 0;text-align:center;font-size:14px;line-height:1.6;color:#4b5068;">Pour répondre par e-mail, touchez simplement « Répondre ».</p>
    @if ($quote->whatsappUrl())
        <p style="margin:16px 0 0;text-align:center;">
            <a href="{{ $quote->whatsappUrl() }}" style="display:inline-block;background:#25d366;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Répondre sur WhatsApp</a>
        </p>
    @endif

    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('admin.quotes.index') }}" style="display:inline-block;color:#3b1c8c;font-weight:bold;font-size:14px;">Voir les demandes de devis</a>
    </p>
@endsection
