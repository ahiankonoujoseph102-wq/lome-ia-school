{{--
    Fond animé (accueil et haut du catalogue ; réglage « Fond animé » de l'admin, App\Support\AnimatedBackground).
    Uniquement du CSS (transform et opacité) : halo lumineux (aurore), réseau de points et de lignes, icônes génériques.
    Aucune image ni vidéo : motifs dessinés par des masques SVG de quelques octets dans site.css.
    Mouvement coupé si « moins d'animations », « économie de données » (en-tête Save-Data ou navigateur),
    mis en pause hors de l'écran et onglet masqué (resources/js/animated-background.js).
    Couleurs sous le seuil de Chrome : rien n'est recoloré par le mode sombre forcé.
--}}
@php
    use App\Support\AnimatedBackground;

    // Positions fixes, choisies pour ne jamais passer sur le texte ni les boutons : sur téléphone, seulement dans la
    // bande vide du haut (et du bas sur l'accueil) ; dès les écrans moyens, sur les marges.
    $short = $short ?? false;
    $floating = [
        ['phone', 'left-[5%] top-1.5 size-7 sm:left-[46%] sm:top-[4%] sm:size-8', '--icon-time: 25s; --icon-delay: -18s;'],
        ['chat', 'right-[5%] top-1.5 size-7 sm:right-[6%] sm:top-[10%] sm:size-10', '--icon-time: 23s; --icon-delay: -9s;'],
        ['robot', ($short ? 'max-sm:hidden ' : '').'left-[4%] bottom-1 size-7 sm:bottom-auto sm:left-[5%] sm:top-[14%] sm:size-11', '--icon-time: 19s; --icon-delay: -3s;'],
        ['gear', ($short ? 'max-sm:hidden ' : '').'right-[4%] bottom-1 size-7 sm:bottom-auto sm:right-[4%] sm:top-[58%] sm:size-10', '--icon-time: 27s; --icon-delay: -6s;'],
        ['book', 'max-sm:hidden left-[9%] top-[72%] size-9', '--icon-time: 21s; --icon-delay: -14s;'],
        ['bolt', 'max-sm:hidden right-[22%] top-[86%] size-8', '--icon-time: 17s; --icon-delay: -11s;'],
    ];
@endphp

<div
    class="anim-bg pointer-events-none absolute inset-0 -z-10"
    aria-hidden="true"
    data-animated-bg
    data-test="animated-background"
    @if (AnimatedBackground::saveData(request())) data-still @endif
>
    <div class="anim-layer" data-depth="0.12">
        <span class="anim-aurora anim-aurora-violet"></span>
        <span class="anim-aurora anim-aurora-blue"></span>
        <span class="anim-aurora anim-aurora-gold"></span>
    </div>
    <div class="anim-layer anim-fade" data-depth="0.22">
        <span class="anim-network"></span>
    </div>
    <div class="anim-layer" data-depth="0.35">
        @foreach ($floating as [$icon, $place, $timing])
            <span class="anim-icon anim-icon-{{ $icon }} {{ $place }}" style="{{ $timing }}" data-icon="{{ $icon }}"></span>
        @endforeach
    </div>
</div>
