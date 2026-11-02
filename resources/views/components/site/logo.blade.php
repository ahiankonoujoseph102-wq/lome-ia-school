{{--
    Logo du site (en-tête, pied de page).
    - « image » (par défaut) : le logo horizontal complet (config « site.brand.logo », modifiable dans l'admin),
      aussi lisible que le nom en texte à partir de 360 px de large, légèrement réduit en dessous.
    - « icon-text » : l'icône suivie du nom écrit en texte (utilisé si aucun logo n'est disponible).
    Les deux ne sont jamais recolorés par le mode sombre forcé de Chrome (image, texte clair sur fond sombre).
--}}
@props(['variant' => 'image', 'height' => 'h-7 min-[360px]:h-10'])

@php
    $logoUrl = \App\Support\Brand::logoUrl();
    $variant = $variant === 'image' && $logoUrl ? 'image' : 'icon-text';
@endphp

<a href="{{ route('home') }}" {{ $attributes->class('group inline-flex shrink-0 items-center gap-2.5') }} aria-label="{{ config('app.name') }} : accueil">
    @if ($variant === 'image')
        <picture>
            @if ($logoWebp = \App\Support\Brand::logoWebpUrl())
                <source srcset="{{ $logoWebp }}" type="image/webp">
            @endif
            <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="{{ $height }} w-auto" height="40" width="188">
        </picture>
    @else
        <img src="{{ \App\Support\Brand::icon('icon-192.png') }}" alt="" class="size-10 rounded-xl ring-1 ring-veil/15 transition group-hover:scale-105" width="40" height="40">
        <span class="text-lg leading-none font-bold tracking-tight whitespace-nowrap text-white">
            Lomé <span class="text-gold-400">IA</span> School
        </span>
    @endif
</a>
