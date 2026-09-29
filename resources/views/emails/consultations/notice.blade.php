@php
    use App\Consultations\ConsultationTime;
    use App\Models\ConsultationSettings;
    use Illuminate\Support\Str;

    $firstName = Str::of($consultation->name)->trim()->explode(' ')->first();
    $meetUrl = ConsultationSettings::current()->meet_url;
    $recommended = $consultation->recommendedFormation;
    [$title, $heading, $intro] = match ($kind) {
        'confirmed' => ['Consultation confirmée', 'Votre consultation est confirmée', 'Bonjour '.$firstName.', merci pour votre réservation. Voici les informations de votre consultation gratuite de 30 minutes.'],
        'reminder_day' => ['Rappel', 'Rendez-vous '.($consultation->starts_at->isToday() ? 'aujourd\'hui' : 'demain'), 'Bonjour '.$firstName.', petit rappel : votre consultation gratuite a lieu '.($consultation->starts_at->isToday() ? 'aujourd\'hui' : 'demain').'.'],
        'reminder_hour' => ['Rappel', 'C\'est dans 1 heure', 'Bonjour '.$firstName.', votre consultation gratuite commence dans 1 heure.'],
        'moved' => ['Consultation déplacée', 'Votre consultation a été déplacée', 'Bonjour '.$firstName.', voici le nouvel horaire de votre consultation gratuite.'],
        'cancelled' => ['Consultation annulée', 'Votre consultation est annulée', 'Bonjour '.$firstName.', votre consultation gratuite du '.ConsultationTime::short($consultation).' est annulée.'],
        'recommendation' => ['Recommandation', 'La formation que nous vous recommandons', 'Bonjour '.$firstName.', merci pour notre échange. Voici la formation qui correspond le mieux à ce dont nous avons parlé.'],
        default => ['Consultation', 'Votre consultation gratuite', 'Bonjour '.$firstName.'.'],
    };
    $showDetails = in_array($kind, ['confirmed', 'reminder_day', 'reminder_hour', 'moved'], true);
    $rows = $showDetails ? array_filter([
        'Date' => ConsultationTime::day($consultation),
        'Heure' => ucfirst(ConsultationTime::range($consultation)).(ConsultationTime::neighbours($consultation) !== '' ? ' · '.ConsultationTime::neighbours($consultation) : ''),
        'Durée' => '30 minutes, en direct en ligne par appel vidéo',
        'Lien Google Meet' => $meetUrl,
    ]) : [];
@endphp
@extends('emails.layout')

@section('title', $title)
@section('heading', $heading)
@section('intro', $intro)

@section('content')
    @if ($rows !== [])
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
            @foreach ($rows as $label => $value)
                <tr>
                    <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:110px;vertical-align:top;">{{ $label }}</td>
                    <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;word-break:break-all;">
                        @if ($label === 'Lien Google Meet')
                            <a href="{{ $value }}" style="color:#6d28d9;">{{ $value }}</a>
                        @else
                            {{ $value }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
        <p style="margin:18px 0 0;"><strong>Pour bien se préparer :</strong> installez-vous dans un endroit calme, vérifiez votre connexion internet et rejoignez l'appel à l'heure, depuis votre téléphone ou votre ordinateur.</p>
        @if (! $meetUrl)
            <p style="margin:14px 0 0;">Le lien de l'appel vidéo vous sera envoyé par e-mail avant le rendez-vous.</p>
        @endif
        @if ($meetUrl)
            <p style="margin:26px 0 0;text-align:center;">
                <a href="{{ $meetUrl }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Rejoindre l'appel vidéo</a>
            </p>
        @endif
        <p style="margin:18px 0 0;text-align:center;font-size:14px;">
            <a href="{{ $consultation->manageUrl() }}" style="color:#6d28d9;">Voir, déplacer ou annuler mon rendez-vous</a>
        </p>
        <p style="margin:8px 0 0;text-align:center;font-size:14px;">
            <a href="{{ \App\Consultations\ConsultationCalendar::googleUrl($consultation, $meetUrl) }}" style="color:#6d28d9;">Ajouter à Google Agenda</a>
            · <a href="{{ route('consultation.calendar', $consultation->manage_token) }}" style="color:#6d28d9;">Autre agenda (fichier .ics)</a>
        </p>
        <p style="margin:10px 0 0;text-align:center;font-size:12px;color:#7a8099;">Déplacement ou annulation possible jusqu'à {{ ConsultationSettings::current()->manage_deadline_hours }} heures avant le rendez-vous.</p>
    @elseif ($kind === 'cancelled')
        @if ($consultation->cancellation_message)
            <p style="margin:0 0 14px;"><strong>Message de Lomé IA School :</strong> {{ $consultation->cancellation_message }}</p>
        @endif
        <p style="margin:0 0 14px;">Vous pouvez réserver un autre créneau quand vous le souhaitez.</p>
        <p style="margin:26px 0 0;text-align:center;">
            <a href="{{ route('consultation') }}#reserver" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Réserver un autre créneau</a>
        </p>
    @elseif ($kind === 'recommendation' && $recommended)
        <p style="margin:0 0 14px;"><strong>{{ $recommended->title }}</strong></p>
        @if ($recommended->subtitle)
            <p style="margin:0 0 14px;">{{ $recommended->subtitle }}</p>
        @endif
        <p style="margin:0 0 14px;">Vous trouverez sur la page de la formation son contenu, son prix et la façon de la payer par Mobile Money. N'hésitez pas à nous écrire sur WhatsApp si vous avez une question.</p>
        <p style="margin:26px 0 0;text-align:center;">
            <a href="{{ route('formations.show', $recommended) }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Voir la formation</a>
        </p>
    @endif
@endsection
