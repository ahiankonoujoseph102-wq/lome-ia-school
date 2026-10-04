{{--
    Visage d'Ama : jeune femme africaine souriante, dessin animé simple aux couleurs du site (aucune image, aucune vidéo,
    aucune bibliothèque). Calques de couleur découpés par des masques SVG (voir App\Support\AmaFace : un simple SVG serait
    recoloré par le mode sombre forcé de Chrome). Trois propositions de coiffure (« style » : a, b, c).
    Le dessin ne bouge que par ses attributs (aucun script ici, mouvements dans site.css, bloc « Visage d'Ama ») :
    - data-blink  : yeux fermés (clignement posé par le script toutes les 3 à 6 secondes) ;
    - data-wave   : petit signe de la main (avec la bulle de présentation) ;
    - data-state  : listening | thinking | speaking | idle (mode appel) ;
    - data-talking, data-beat : la bouche bouge pendant que la synthèse vocale du navigateur lit ;
    - data-enter  : le dessin se trace en doré puis se remplit (grande version du mode appel, « large ») ;
    - data-still  : aucun mouvement (moins d'animations demandé ou économie de données).
    « zoom » : cadrage resserré sur le visage (petits formats).
--}}
@props(['style' => 'a', 'large' => false, 'zoom' => false])

@php
    $style = \App\Support\AmaFace::style($style);
    $layers = \App\Support\AmaFace::styleLayers($style);
@endphp

{!! \App\Support\AmaFace::stylesOnce($style) !!}
<span {{ $attributes->class(['ama-face', 'ama-face-large' => $large, 'ama-face-zoom' => $zoom]) }} data-style="{{ $style }}" aria-hidden="true">
    @if ($large)
        <svg class="ama-ink" viewBox="0 0 200 200" fill="none" stroke="#f5a70b" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" focusable="false">
            <path pathLength="1" d="M61 90a39 45 0 1 0 78 0a39 45 0 1 0 -78 0"/>
            <path pathLength="1" d="M18 200C20 170 52 158 86 154L100 170L114 154C148 158 180 170 182 200"/>
            <path pathLength="1" d="{{ \App\Support\AmaFace::INK[$style] }}"/>
            <path pathLength="1" d="M84 85a5.5 7 0 1 0 0.1 0M116 85a5.5 7 0 1 0 0.1 0M82 112Q100 128 118 112"/>
        </svg>
    @endif
    <span class="ama-paint">
        <span class="ama-bust">
            <i class="ama-l-blouse"></i><i class="ama-l-neck"></i><i class="ama-l-collar"></i>
            <span class="ama-head">
                @foreach ($layers['back'] as $layer)<i class="ama-l-{{ $layer }}"></i>@endforeach
                <i class="ama-l-ears"></i><i class="ama-l-earrings"></i><i class="ama-l-skin"></i><i class="ama-l-cheeks"></i><i class="ama-l-nose"></i>
                @foreach ($layers['front'] as $layer)<i class="ama-l-{{ $layer }}"></i>@endforeach
                <i class="ama-brows ama-l-brows"></i>
                <span class="ama-eyes"><span class="ama-look"><i class="ama-l-eyes"></i><i class="ama-l-glints"></i></span></span>
                <i class="ama-smile ama-l-smile"></i>
                <span class="ama-mouth"><i class="ama-l-mouth"></i><i class="ama-l-tongue"></i></span>
            </span>
        </span>
        @if ($large)
            <span class="ama-dots"><i class="ama-l-bubble"></i><i class="ama-l-dots"></i></span>
        @endif
        <span class="ama-hand"><i class="ama-l-sleeve"></i><i class="ama-l-palm"></i></span>
    </span>
</span>
