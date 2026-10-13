{{--
    Visuel d'une formation, dans cet ordre : image envoyée dans l'admin, photo d'illustration
    (App\Support\FormationPhotos, avec un voile aux couleurs du site), illustration dessinée
    (public/images/formations/), sinon visuel de remplacement dessiné aux couleurs de la catégorie.
--}}
@props([
    'formation',
    'eager' => false,
    'compact' => false,
    'sizes' => '(min-width: 1024px) 384px, (min-width: 640px) 50vw, 100vw',
])

@php
    [$from, $to] = $formation->category->gradient();
    $photo = \App\Support\FormationPhotos::for($formation);
@endphp

<div {{ $attributes->class('relative overflow-hidden bg-night-800') }}>
    @if ($photo)
        <img
            src="{{ $photo['src'] }}"
            srcset="{{ $photo['srcset'] }}"
            sizes="{{ $compact ? '96px' : $sizes }}"
            alt="{{ $photo['alt'] }}"
            class="cover-photo absolute inset-0 size-full object-cover"
            loading="{{ $eager ? 'eager' : 'lazy' }}"
            @if ($eager) fetchpriority="high" @endif
            decoding="async"
            width="800"
            height="500"
            data-test="formation-photo"
        >
        @unless ($compact)
            <span class="cover-photo-veil pointer-events-none absolute inset-0" aria-hidden="true"></span>
        @endunless
    @elseif ($formation->image_url)
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
        {{-- Dessin en CSS (et non en SVG) : le mode sombre forcé de Chrome ne le recolore pas (voir site.css). --}}
        <div class="cover-art absolute inset-0" style="--cover-from: {{ $from }}; --cover-to: {{ $to }}" aria-hidden="true">
            <span class="cover-glow"></span>
            <span class="cover-ring" style="--cover-ring: 35%"></span>
            <span class="cover-ring" style="--cover-ring: 55%"></span>
            <span class="cover-ring" style="--cover-ring: 75%"></span>
            <span class="cover-dot" style="left: 10%; top: 16%; --cover-dot: 4px"></span>
            <span class="cover-dot" style="left: 17.5%; top: 24%; --cover-dot: 3px"></span>
            <span class="cover-dot" style="left: 27.5%; top: 12%; --cover-dot: 3px"></span>
            <span class="cover-dot" style="left: 15%; top: 80%; --cover-dot: 3px"></span>
            <span class="cover-dot" style="left: 37.5%; top: 86%; --cover-dot: 4px"></span>
            <span class="cover-mountain"></span>
        </div>
        <div class="absolute inset-0 flex items-center justify-center">
            @if ($compact)
                <flux:icon :icon="$formation->category->icon()" class="size-7 text-white drop-shadow" />
            @else
                <span class="grid size-16 place-items-center rounded-2xl bg-veil/15 text-white shadow-2xl ring-1 ring-veil/30 backdrop-blur-md sm:size-20">
                    <flux:icon :icon="$formation->category->icon()" class="size-8 sm:size-10" />
                </span>
            @endif
        </div>
    @endif
</div>
