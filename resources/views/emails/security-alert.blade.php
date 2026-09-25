@php
    $rows = array_filter([
        'Changement' => $activity->action->label(),
        'Détail' => $activity->description,
        'Par' => $byName,
        'Quand' => $activity->created_at?->translatedFormat('l j F Y à H\hi').' (heure de Lomé)',
        'Adresse IP' => $ip ?: null,
    ]);
@endphp
@extends('emails.layout')

@section('title', 'Alerte de sécurité')
@section('heading', 'Alerte de sécurité')
@section('intro', 'Un réglage sensible vient d\'être modifié dans l\'administration de Lomé IA School.')

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:110px;vertical-align:top;">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
    <p style="margin:18px 0 0;"><strong>Ce n'est pas vous ?</strong> Changez immédiatement votre mot de passe, vérifiez les moyens de paiement et l'équipe dans l'administration, puis consultez le journal d'activité.</p>
    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('admin.activity.index') }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Voir le journal d'activité</a>
    </p>
@endsection
