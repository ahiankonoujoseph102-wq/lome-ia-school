@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'type' => 'website',
    'jsonLd' => [],
    'stickyCta' => false,
    'whatsappMessage' => null,
])

@php
    $siteName = config('app.name');
    $fullTitle = $title ? $title.' | '.$siteName : $siteName.' | Formations en intelligence artificielle à Lomé et en ligne';
    $description ??= 'Formations pratiques en intelligence artificielle, en français : ChatGPT, création visuelle et vidéo, sites web, automatisation, agents IA. Paiement Mobile Money et accès immédiat.';
    $image ??= route('og.site');
    $canonical = url()->current();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-20">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <script>document.documentElement.classList.add('js')</script>

        <title>{{ $fullTitle }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">
        <meta name="theme-color" content="#0b1030">

        {{-- Aperçus de partage (WhatsApp, Facebook, LinkedIn) --}}
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:locale" content="fr_FR">
        <meta property="og:type" content="{{ $type }}">
        <meta property="og:title" content="{{ $title ?? $fullTitle }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $image }}">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $title ?? $siteName }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title ?? $fullTitle }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ $image }}">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/css/site.css', 'resources/js/site.js'])

        @foreach ($jsonLd as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endforeach
    </head>
    <body @class(['min-h-screen overflow-x-clip font-sans', 'pb-24 lg:pb-0' => $stickyCta])>
        <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-night-950">Aller au contenu</a>

        @include('site.partials.header')

        <main id="contenu">
            {{ $slot }}
        </main>

        @include('site.partials.footer')

        @include('site.partials.whatsapp-button', ['raised' => $stickyCta, 'whatsappMessage' => $whatsappMessage])

        {{ $after ?? '' }}
    </body>
</html>
