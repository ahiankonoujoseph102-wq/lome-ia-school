@php
    use App\Models\AssistantHandoff;
    use App\Support\PhoneNumber;

    $rows = array_filter([
        'Prénom' => $handoff->first_name,
        'Réponse souhaitée' => $handoff->channel === AssistantHandoff::EMAIL ? 'Par e-mail' : 'Sur WhatsApp',
        $handoff->channel === AssistantHandoff::EMAIL ? 'E-mail' : 'WhatsApp' => $handoff->channel === AssistantHandoff::EMAIL ? $handoff->email : PhoneNumber::format($handoff->phone),
        'Page d\'origine' => $handoff->page,
        'Formation' => $handoff->formation,
        'Reçue le' => $handoff->created_at?->translatedFormat('l j F Y à H \h i').' (heure de Lomé)',
    ]);
@endphp
@extends('emails.layout')

@section('title', 'Question transmise par Ama')
@section('heading', 'Question transmise par Ama')
@section('intro', $handoff->first_name.' souhaite une réponse personnelle de Coach Joseph.')

@section('content')
    <p style="margin:0 0 8px;font-size:13px;color:#7a8099;">Question</p>
    <p style="margin:0 0 22px;padding:14px 16px;background:#f7f7fb;border-radius:14px;font-size:15px;line-height:1.6;color:#1e2340;white-space:pre-line;">{{ $handoff->question }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6f2;border-radius:14px;">
        @foreach ($rows as $label => $value)
            <tr>
                <td style="padding:10px 14px;font-size:13px;color:#7a8099;width:130px;vertical-align:top;">{{ $label }}</td>
                <td style="padding:10px 14px;font-size:14px;color:#1e2340;font-weight:bold;word-break:break-word;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin:26px 0 0;text-align:center;">
        @if ($whatsappUrl)
            <a href="{{ $whatsappUrl }}" style="display:inline-block;background:#25d366;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Répondre sur WhatsApp</a>
        @else
            <span style="display:inline-block;font-size:14px;line-height:1.6;color:#4b5068;">Pour répondre, touchez simplement « Répondre » : votre réponse partira à {{ $handoff->email }}.</span>
        @endif
    </p>

    @if ($handoff->excerpt)
        <p style="margin:28px 0 8px;font-size:13px;color:#7a8099;">Extrait de la conversation avec Ama (numéros, adresses et codes retirés)</p>
        <p style="margin:0;padding:14px 16px;border:1px solid #e3e6f2;border-radius:14px;font-size:13px;line-height:1.6;color:#4b5068;white-space:pre-line;">{{ $handoff->excerpt }}</p>
    @endif

    <p style="margin:26px 0 0;text-align:center;">
        <a href="{{ route('admin.assistant.handoffs.index') }}" style="display:inline-block;color:#3b1c8c;font-weight:bold;font-size:14px;">Voir les demandes transmises</a>
    </p>
@endsection
