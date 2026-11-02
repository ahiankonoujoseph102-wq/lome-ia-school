{{--
    Logo du site (en-tête, pied de page).
    - « image » (par défaut) : le logo horizontal complet (config « site.brand.logo », modifiable dans l'admin),
      aussi lisible que le nom en texte à partir de 360 px de large, légèrement réduit en dessous.
    - « icon-text » : l'icône suivie du nom écrit en texte (utilisé si aucun logo n'est disponible).
    Les deux ne sont jamais recolorés par le mode sombre forcé de Chrome (image, texte clair sur fond sombre).
--}}
@props(['variant' => 'image', 'height' => 'h-7 min-[360px]:h-10', 'onLight' => false])

@php
    $logoUrl = \App\Support\Brand::logoUrl();
    $variant = $variant === 'image' && $logoUrl ? 'image' : 'icon-text';
    // Design clair : le logo (texte blanc) n'est jamais recoloré ; sur fond clair, l'icône suivie du nom en texte bleu nuit.
    if ($onLight && \App\Support\SiteDesign::isLight()) {
        $variant = 'icon-text';
    }
@endphp

<a href="{{ route('home') }}" {{ $attributes->class('group inline-flex shrink-0 items-center gap-2.5') }} aria-label="{{ config('app.name') }} : accueil">
    @if ($variant === 'image')
        <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="{{ $height }} w-auto" height="40" width="188">
    @else
        <img src="{{ \App\Support\Brand::icon('icon-192.png') }}" alt="" class="size-9 rounded-xl ring-1 ring-veil/15 transition group-hover:scale-105 min-[360px]:size-10" width="40" height="40">
        <span class="text-base leading-none font-bold tracking-tight whitespace-nowrap text-white min-[360px]:text-lg">
            Lomé <span class="text-gold-400 [[data-theme=light]_&]:text-[#d98a06]">IA</span> School
        </span>
    @endif
</a>
