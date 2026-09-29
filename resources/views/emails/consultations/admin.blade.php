@php
    use App\Consultations\ConsultationTime;
    use App\Support\PhoneNumber;

    [$title, $intro] = match ($kind) {
        'booked' => ['Nouvelle consultation', $consultation->name.($consultation->requester_type->isOrganization() && $consultation->organization_name ? ' ('.$consultation->organization_name.')' : '').' a réservé une consultation gratuite.'],
        'moved' => ['Consultation déplacée', $consultation->name.' a déplacé sa consultation.'],
        'cancelled' => ['Consultation annulée', $consultation->name.' a annulé sa consultation.'],
        default => ['Consultation', $consultation->name],
    };
    $rows = array_filter([
        'Quand' => ConsultationTime::full($consultation),
        'Nom' => $consultation->name,
        'WhatsApp' => PhoneNumber::format($consultation->phone),
        'E-mail' => $consultation->email,
        'Profil' => $consultation->requester_type->label(),
        'Structure' => $consultation->requester_type->isOrganization() ? $consultation->organization_name : null,
        'Besoin' => $consultation->topic,
        'Formation' => $consultation->formation?->title,
        'Compte élève' => $consultation->user ? 'Oui ('.$consultation->user->email.')' : 'Non',
    ]);
@endphp
@extends('emails.layout')

@section('title', $title)
@section('heading', $title)
@section('intro', $intro)

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:110px;vertical-align:top;">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('admin.consultations.index') }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Voir les consultations</a>
    </p>
@endsection
