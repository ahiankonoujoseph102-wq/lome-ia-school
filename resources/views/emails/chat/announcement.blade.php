@extends('emails.layout')

@section('title', 'Annonce importante')
@section('heading', 'Annonce importante')
@section('intro', 'Bonjour '.$firstName.', '.$authorName.' a publié une annonce dans le groupe « '.$groupTitle.' ».')

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border:1px solid #e3e6f2;border-radius:16px;">
        <tr>
            <td style="padding:16px 18px;">
                <p style="margin:0;font-size:13px;font-weight:bold;color:#6d28d9;text-transform:uppercase;letter-spacing:0.04em;">{{ $groupTitle }}</p>
                <div style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#1e2340;white-space:normal;">{{ \App\Chat\MessageText::toHtml($body) }}</div>
            </td>
        </tr>
    </table>

    <p style="margin:0;">
        <a href="{{ $url }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:15px;text-decoration:none;padding:12px 24px;border-radius:999px;">Ouvrir le groupe</a>
    </p>

    <p style="margin:22px 0 0;font-size:13px;line-height:1.5;color:#7a8099;">
        Vous recevez cet e-mail car vous êtes inscrit à ce groupe sur Lomé IA School.
    </p>
@endsection
