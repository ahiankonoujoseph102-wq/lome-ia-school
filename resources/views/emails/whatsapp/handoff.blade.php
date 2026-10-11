@php
    $rows = array_filter([
        'Client' => $contact->profile_name,
        'WhatsApp' => $contact->phone(),
        'Raison' => $contact->needs_human_reason,
        'Depuis le' => $contact->needs_human_at?->translatedFormat('l j F Y à H \h i').' (heure de Lomé)',
    ]);
@endphp
@extends('emails.layout')

@section('title', 'Un client WhatsApp attend l\'équipe')
@section('heading', 'Un client WhatsApp attend l\'équipe')
@section('intro', 'L\'agent WhatsApp s\'est mis en pause pour ce client : à vous de lui répondre.')

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:110px;vertical-align:top;">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;word-break:break-word;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('admin.whatsapp.show', $contact) }}" style="display:inline-block;background:#25d366;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Voir et répondre dans l'admin</a>
    </p>

    @if ($excerpt !== '')
        <p style="margin:28px 0 8px;font-size:13px;color:#7a8099;">Derniers messages</p>
        <p style="margin:0;padding:14px 16px;border:1px solid #e3e6f2;border-radius:14px;font-size:13px;line-height:1.6;color:#4b5068;white-space:pre-line;">{{ $excerpt }}</p>
    @endif

    <p style="margin:22px 0 0;font-size:13px;line-height:1.6;color:#4b5068;">WhatsApp n'autorise une réponse libre que dans les 24 heures qui suivent le dernier message du client.</p>
@endsection
