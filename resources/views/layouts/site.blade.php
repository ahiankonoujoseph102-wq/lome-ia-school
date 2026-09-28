@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'jsonLd' => [],
    'stickyCta' => false,
    'whatsappMessage' => null,
    'noindex' => false,
    'variant' => 'marketing',
])

@php
    $siteName = config('app.name');
    $fullTitle = $title ? $title.' | '.$siteName : $siteName.' | Formations en intelligence artificielle à Lomé et en ligne';
    $description ??= 'Formations pratiques en intelligence artificielle, en français : ChatGPT, création visuelle et vidéo, sites web, automatisation, agents IA. Paiement par Mobile Money.';
    $image ??= route('og.site');
    $canonical = url()->current();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-20">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        {{-- Site toujours sombre : empêche le navigateur d'appliquer son propre mode sombre ou clair. --}}
        <meta name="color-scheme" content="only dark">
        {{--
            Mode sombre forcé de Chrome (réglage fréquent sur Tecno, Infinix, Xiaomi, Samsung) : quand le téléphone
            est en mode clair, Chrome recolore quand même les textes foncés et les fonds clairs, malgré « only dark ».
            On le détecte (méthode documentée par Google : la couleur système « canvas » n'est plus blanche) et la
            classe « force-dark » active une compensation exacte dans site.css. Test : SiteColorSchemeTest.
        --}}
        <script>
            (() => {
                const root = document.documentElement;
                const probe = document.createElement('div');
                probe.style.cssText = 'display:none;background-color:canvas;color-scheme:light';
                root.appendChild(probe);
                const autoDark = getComputedStyle(probe).backgroundColor === 'rgb(18, 18, 18)';
                probe.remove();
                if (!autoDark || !window.matchMedia) return;
                const phoneDark = window.matchMedia('(prefers-color-scheme: dark)');
                const apply = () => root.classList.toggle('force-dark', !phoneDark.matches);
                apply();
                phoneDark.addEventListener?.('change', apply);
            })();
        </script>

        <title>{{ $fullTitle }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">
        <meta name="theme-color" content="#0b1030">
        @if ($noindex || in_array($variant, ['student', 'chat'], true) || config('app.test_banner'))
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- Aperçus de partage (WhatsApp, Facebook, LinkedIn) --}}
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:locale" content="fr_FR">
        <meta property="og:type" content="{{ $type }}">
        <meta property="og:title" content="{{ $title ?? $fullTitle }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $image }}">
        <meta property="og:image:type" content="{{ $image === route('og.site') ? \App\Support\Brand::shareImageType() : 'image/png' }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $title ?? $siteName }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title ?? $fullTitle }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $image }}">

        <link rel="icon" href="{{ \App\Support\Brand::icon('favicon.ico') }}" sizes="48x48">
        <link rel="icon" href="{{ \App\Support\Brand::icon('favicon-32.png') }}" type="image/png" sizes="32x32">
        <link rel="icon" href="{{ \App\Support\Brand::icon('favicon-16.png') }}" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="{{ \App\Support\Brand::icon('apple-touch-icon.png') }}" sizes="180x180">
        {{-- Site installable sur l'écran d'accueil (nécessaire aux notifications sur iPhone) --}}
        <link rel="manifest" href="{{ route('pwa.manifest') }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Lomé IA">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @vite(['resources/css/site.css', 'resources/js/site.js'])
        {{ $head ?? '' }}

        @foreach ($jsonLd as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endforeach
    </head>
    <body @class([
        'font-sans',
        'min-h-screen overflow-x-clip' => $variant !== 'chat',
        'flex h-dvh flex-col overflow-hidden' => $variant === 'chat',
        'pb-24 lg:pb-0' => $stickyCta || $variant === 'student',
    ])>
        @include('site.partials.test-banner')

        @if ($variant === 'chat')
            {{-- Conversation d'un groupe : plein écran (sous le bandeau de test éventuel), sans en-tête ni pied de page du site. --}}
            <div class="min-h-0 flex-1">
                {{ $slot }}
            </div>
            {{ $after ?? '' }}
        @else
            <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-full focus:bg-veil focus:px-4 focus:py-2 focus:text-night-950">Aller au contenu</a>

            @include('site.partials.header')

            @if ($variant === 'student')
                @include('site.student.partials.nav')
            @endif

            <main id="contenu">
                @include('site.partials.admin-bar')
                @include('site.partials.admin-meet-alert')
                {{ $slot }}
            </main>

            @if ($variant === 'student')
                @include('site.student.partials.footer')
            @else
                @include('site.partials.footer')
            @endif

            @include('site.partials.whatsapp-button', ['whatsappMessage' => $whatsappMessage])

            {{ $after ?? '' }}
        @endif
    </body>
</html>
