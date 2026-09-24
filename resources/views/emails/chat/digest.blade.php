@extends('emails.layout')

@section('title', 'Messages non lus')
@section('heading', 'Des messages vous attendent')
@section('intro', 'Bonjour '.$firstName.', voici ce qui s\'est dit dans vos groupes depuis votre dernière visite.')

@section('content')
    @foreach ($groups as $group)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border:1px solid #e3e6f2;border-radius:16px;">
            <tr>
                <td style="padding:16px 18px;">
                    <p style="margin:0;font-size:16px;font-weight:bold;color:#0b1030;">{{ $group['title'] }}</p>
                    <p style="margin:4px 0 0;font-size:14px;color:#6d28d9;font-weight:bold;">{{ $group['count'] }} {{ $group['count'] > 1 ? 'messages non lus' : 'message non lu' }}</p>
                    @foreach ($group['previews'] as $preview)
                        <p style="margin:8px 0 0;font-size:14px;line-height:1.5;color:#4a5070;">{{ $preview }}</p>
                    @endforeach
                    <p style="margin:14px 0 0;">
                        <a href="{{ $group['url'] }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:15px;text-decoration:none;padding:11px 22px;border-radius:999px;">Ouvrir le groupe</a>
                    </p>
                </td>
            </tr>
        </table>
    @endforeach

    <p style="margin:22px 0 0;font-size:13px;line-height:1.5;color:#7a8099;">
        Vous recevez cet e-mail au plus une fois par jour, seulement quand des messages vous attendent.
        <a href="{{ route('student.notifications') }}" style="color:#6d28d9;">Modifier mes notifications</a>
    </p>
@endsection
