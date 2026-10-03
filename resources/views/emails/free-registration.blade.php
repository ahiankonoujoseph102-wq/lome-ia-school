@php
    $formation = $registration->formation;
    $option = $registration->option;
    $rows = array_filter([
        'Formation' => $formation->title,
        'Option' => $option?->label,
        'Date' => $formation->eventDateLabel(),
        'Horaires' => $option?->schedule,
        'Adresse' => $option?->isOnsite() ? $formation->event_location : null,
        'En ligne' => $option && ! $option->isOnsite() ? 'Sur Google Meet' : null,
    ]);
@endphp
@extends('emails.layout')

@section('title', 'Inscription confirmée')
@section('heading', 'Votre inscription est confirmée')
@section('intro', 'Bonjour '.$firstName.', c\'est noté : vous êtes inscrit(e) à la formation gratuite « '.$formation->title.' ».')

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:90px;vertical-align:top;">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    @if ($option && ! $option->isOnsite())
        <p style="margin:18px 0 0;font-size:14px;">Le lien Google Meet sera publié dans le groupe des inscrits.</p>
    @endif

    <p style="margin:18px 0 0;font-size:14px;">Vous avez aussi rejoint le groupe de discussion des inscrits : les informations pratiques y sont publiées.</p>

    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('chat.show', $formation) }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">
            Ouvrir le groupe des inscrits
        </a>
    </p>

    <p style="margin:22px 0 0;font-size:13px;line-height:1.5;color:#7a8099;">
        Vous ne pouvez plus venir ? Annulez votre inscription depuis
        <a href="{{ route('formations.show', $formation) }}#inscription" style="color:#6d28d9;">la page de la formation</a> : votre place sera libérée pour quelqu'un d'autre.
    </p>
@endsection
