{{--
    Grande photo d'illustration en haut d'une page de formation ou d'offre (App\Support\FormationPhotos) :
    voile sombre aux couleurs du site pour que le titre et les boutons posés dessus restent lisibles ;
    la photo se fond dans le fond de la page vers le bas. Chargée tout de suite (première image visible).
--}}
<div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[30rem] overflow-hidden sm:h-[36rem] lg:h-full" data-test="formation-photo-hero-image">
    <img
        src="{{ $photo['src'] }}"
        srcset="{{ $photo['srcset'] }}"
        sizes="100vw"
        alt="{{ $photo['alt'] }}"
        class="photo-hero-image absolute inset-0 size-full object-cover"
        loading="eager"
        fetchpriority="high"
        decoding="async"
        width="1600"
        height="1000"
    >
    <span class="photo-hero-veil absolute inset-0" aria-hidden="true"></span>
</div>
