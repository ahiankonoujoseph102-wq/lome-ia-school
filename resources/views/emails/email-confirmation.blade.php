@extends('emails.layout')

@section('title', 'Confirmez votre adresse e-mail')
@section('heading', 'Confirmez votre adresse e‑mail')
@section('intro', 'Bonjour '.$user->first_name.', il ne reste qu\'une étape pour sécuriser votre compte Lomé IA School.')

@section('content')
    <p style="margin:0;">Saisissez ce code sur la page « Confirmez votre e-mail » :</p>
    <p style="margin:18px 0 0;text-align:center;">
        <span style="display:inline-block;background:#f3f0ff;border:1px solid #ddd6fe;border-radius:14px;padding:14px 22px;font-family:'Courier New',Courier,monospace;font-size:32px;font-weight:bold;letter-spacing:8px;color:#1e2340;" data-test="confirmation-code">{{ $code }}</span>
    </p>
    <p style="margin:22px 0 0;text-align:center;">Ou confirmez en un clic :</p>
    <p style="margin:12px 0 0;text-align:center;">
        <a href="{{ $url }}" style="display:inline-block;background:#fbbf24;color:#0b1030;font-weight:bold;font-size:16px;text-decoration:none;padding:14px 28px;border-radius:999px;">Confirmer mon adresse e-mail</a>
    </p>
    <p style="margin:22px 0 0;font-size:14px;line-height:1.6;color:#4b5068;">
        Le code et le bouton sont valables {{ $minutes }} minutes et ne servent qu'une fois.
        Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail.
    </p>
    <p style="margin:12px 0 0;font-size:13px;line-height:1.6;color:#7a7f99;">
        Le bouton ne fonctionne pas ? Copiez ce lien dans votre navigateur :<br>
        <span style="word-break:break-all;">{{ $url }}</span>
    </p>
@endsection
