{{--
    Visuel d'une formation. Si une image est renseignée (fichier dans public/images/formations/),
    elle est affichée ; sinon, un visuel de remplacement est dessiné aux couleurs de la catégorie.
--}}
@props(['formation', 'eager' => false, 'compact' => false])

@php
    [$from, $to] = $formation->category->gradient();
    $gradientId = 'cover-'.$formation->slug.'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
@endphp

<div {{ $attributes->class('relative overflow-hidden bg-night-800') }}>
    @if ($formation->image_url)
        <img
            src="{{ $formation->image_url }}"
            alt="{{ $formation->title }}"
            class="absolute inset-0 size-full object-cover"
            loading="{{ $eager ? 'eager' : 'lazy' }}"
            decoding="async"
            width="800"
            height="500"
        >
    @else
        <svg class="absolute inset-0 size-full" viewBox="0 0 400 250" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <defs>
                <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="{{ $from }}" />
                    <stop offset="1" stop-color="{{ $to }}" />
                </linearGradient>
                <radialGradient id="{{ $gradientId }}-glow" cx="0.85" cy="0.15" r="0.6">
                    <stop offset="0" stop-color="#ffffff" stop-opacity="0.35" />
                    <stop offset="1" stop-color="#ffffff" stop-opacity="0" />
                </radialGradient>
            </defs>
            <rect width="400" height="250" fill="url(#{{ $gradientId }})" />
            <rect width="400" height="250" fill="url(#{{ $gradientId }}-glow)" />
            <g fill="none" stroke="#ffffff" stroke-opacity="0.16">
                <circle cx="330" cy="200" r="70" />
                <circle cx="330" cy="200" r="110" />
                <circle cx="330" cy="200" r="150" />
            </g>
            <g fill="#ffffff" fill-opacity="0.5">
                <circle cx="40" cy="40" r="2" /><circle cx="70" cy="60" r="1.5" /><circle cx="110" cy="30" r="1.5" />
                <circle cx="60" cy="200" r="1.5" /><circle cx="150" cy="215" r="2" />
            </g>
            <path d="M0 250 L140 150 L220 200 L400 90 L400 250 Z" fill="#0b1030" fill-opacity="0.25" />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
            @if ($compact)
                <flux:icon :icon="$formation->category->icon()" class="size-7 text-white drop-shadow" />
            @else
                <span class="grid size-16 place-items-center rounded-2xl bg-white/15 text-white shadow-2xl ring-1 ring-white/30 backdrop-blur-md sm:size-20">
                    <flux:icon :icon="$formation->category->icon()" class="size-8 sm:size-10" />
                </span>
            @endif
        </div>
    @endif
</div>
