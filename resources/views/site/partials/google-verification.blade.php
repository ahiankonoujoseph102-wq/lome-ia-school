{{-- Vérification de Google Search Console : code saisi dans l'admin (Réglages du site > Référencement Google). --}}
@if ($googleVerification = \App\Support\SearchConsole::code())
    <meta name="google-site-verification" content="{{ $googleVerification }}">
@endif
