@php
    use App\LiveSessions\SessionTime;

    $formation = $session->formation;
    [$title, $heading, $intro] = match ($kind) {
        'reminder_day' => ['Rappel', 'Rendez-vous demain', 'Bonjour '.$firstName.', petit rappel : la session de la formation « '.$formation->title.' » a lieu demain.'],
        'reminder_hour' => ['Rappel', 'C\'est dans 1 heure', 'Bonjour '.$firstName.', la session de la formation « '.$formation->title.' » commence dans 1 heure.'],
        'changed' => ['Session modifiée', 'La session a été modifiée', 'Bonjour '.$firstName.', la session de la formation « '.$formation->title.' » a changé. Voici les nouvelles informations.'],
        'cancelled' => ['Session annulée', 'La session est annulée', 'Bonjour '.$firstName.', la session de la formation « '.$formation->title.' » a été annulée. Nous sommes désolés pour ce changement.'],
        'seat_available' => ['Place libérée', 'Une place s\'est libérée pour vous', 'Bonjour '.$firstName.', vous étiez en liste d\'attente : une place s\'est libérée et elle est pour vous.'],
        'reservation_expired' => ['Place libérée', 'Votre place réservée a été libérée', 'Bonjour '.$firstName.', nous n\'avons pas reçu la preuve de paiement du supplément à temps : votre place a été libérée pour la liste d\'attente.'],
        default => [$session->displayTitle(), $session->displayTitle(), 'Bonjour '.$firstName.'.'],
    };
    $rows = array_filter([
        'Formation' => $formation->title,
        'Session' => $session->displayTitle().' · '.$session->format->longLabel(),
        'Date' => SessionTime::dayTitle($session),
        'Heure' => ucfirst(SessionTime::range($session)).(SessionTime::neighbours($session) !== '' ? ' · '.SessionTime::neighbours($session) : ''),
        'Durée' => $session->durationLabel(),
        'Lieu' => $session->isOnsite() ? $session->location : null,
        'Tarif' => $session->isOnsite() ? $session->priceLabel() : null,
    ]);
@endphp
@extends('emails.layout')

@section('title', $title)
@section('heading', $heading)
@section('intro', $intro)

@section('content')
    @if ($kind === 'changed' && $changes !== [])
        <p style="margin:0 0 14px;"><strong>Ce qui change :</strong> {{ implode(', ', $changes) }}.</p>
    @endif
    @if ($kind === 'cancelled')
        @if ($session->cancellation_reason)
            <p style="margin:0 0 14px;"><strong>Motif :</strong> {{ $session->cancellation_reason }}</p>
        @endif
        @if ($session->requiresPayment())
            <p style="margin:0 0 14px;">Si vous avez payé le supplément, il vous sera remboursé : notre équipe vous contacte.</p>
        @endif
    @endif
    @if ($kind === 'seat_available' && $session->requiresPayment())
        <p style="margin:0 0 14px;">Pour confirmer votre place, payez le supplément de {{ $session->formattedSupplement() }} dans les {{ config('live_sessions.payment_hold_hours') }} heures. Sans paiement, la place sera proposée à la personne suivante.</p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:90px;vertical-align:top;">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    @if ($session->isOnline() && $kind !== 'cancelled' && $session->meet_url)
        <p style="margin:18px 0 0;font-size:14px;">Lien Google Meet : <a href="{{ $session->meet_url }}" style="color:#6d28d9;">{{ $session->meet_url }}</a></p>
    @endif

    @unless ($kind === 'cancelled')
        <p style="margin:26px 0 0;text-align:center;">
            <a href="{{ route('student.sessions.show', $session) }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">
                {{ $kind === 'seat_available' && $session->requiresPayment() ? 'Payer et confirmer ma place' : 'Voir la session' }}
            </a>
        </p>
    @endunless

    @if (in_array($kind, ['reminder_day', 'reminder_hour'], true))
        <p style="margin:22px 0 0;font-size:13px;line-height:1.5;color:#7a8099;">
            Vous recevez ce rappel car vous avez accès à cette formation.
            <a href="{{ route('student.notifications') }}" style="color:#6d28d9;">Modifier mes notifications</a>
        </p>
    @endif
@endsection
