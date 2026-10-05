{{--
    Vidéo d'accueil (verticale 9:16), entre le haut de page et le catalogue. Réglages : admin > Vidéo d'accueil.
    Section courte sur téléphone : aperçu de 10 rem qui s'agrandit au toucher.
    Section absente tant qu'il n'y a pas de lien YouTube valide (ou si l'interrupteur est éteint).
    Image d'aperçu : celle envoyée depuis l'admin (9:16), sinon la miniature YouTube entière sur un fond flouté.
    Seule l'image d'aperçu est chargée : le lecteur (youtube-nocookie.com) n'est ajouté qu'au toucher du bouton lecture.
    Sans JavaScript, le bouton est un simple lien vers YouTube. Aucune transcription ni sous-titre n'est affiché.
    Style stable face au mode sombre forcé : fonds foncés, contours dessinés en ombre, icône blanche, image jamais recolorée.
--}}
@php
    use App\Support\HomeVideo;

    $homeVideoId = HomeVideo::videoId();
    $homeVideoTiktok = HomeVideo::tiktokUrl();
    $homeVideoPoster = HomeVideo::posterUrl();
    $homeVideoCatalogue = $featured->isNotEmpty() ? '#catalogue' : route('formations.index');
@endphp

@if ($homeVideoId)
    <section id="video-accueil" class="section !py-6 sm:!py-14" data-test="home-video">
        {{-- Section courte sur téléphone (environ 500 px de haut) pour que le catalogue reste proche. --}}
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <h2 class="text-xl leading-snug font-bold tracking-tight text-balance text-white sm:text-4xl">{{ HomeVideo::title() }}</h2>
            <p class="mt-1.5 text-sm text-pretty text-slate-400 sm:mt-4 sm:text-lg">{{ HomeVideo::subtitle() }}</p>
        </div>

        {{-- Aperçu étroit sur téléphone (10 rem) ; le cadre s'agrandit seulement quand le visiteur touche lecture
             (ou si la vidéo est illisible, pour que le message tienne). 20 rem dès les écrans moyens. --}}
        <x-site.lazy-youtube
            :video-id="$homeVideoId"
            :title="HomeVideo::title()"
            :poster="$homeVideoPoster"
            :poster-width="HomeVideo::POSTER_WIDTH"
            :poster-height="HomeVideo::POSTER_HEIGHT"
            class="mx-auto mt-4 w-40 transition-[width] duration-300 data-[open=true]:w-[min(78%,17rem)] sm:mt-8 sm:w-80 sm:data-[open=true]:w-80 motion-reduce:transition-none"
            data-test="home-video-frame"
            data-reveal
        />

        {{-- La vidéo se termine par un appel à s'abonner qui n'a pas de sens ici : ces boutons servent de suite. --}}
        <div class="mx-auto mt-4 flex max-w-sm flex-wrap justify-center gap-2 sm:mt-8 sm:max-w-none sm:gap-3" data-reveal>
            <a href="{{ $homeVideoCatalogue }}" class="btn-gold !gap-1.5 !px-2.5 !py-3 !text-sm whitespace-nowrap sm:!gap-2 sm:!px-6 sm:!py-3.5 sm:!text-base" data-test="home-video-formations">
                Voir les formations <flux:icon.arrow-right class="size-4 sm:size-5" />
            </a>
            @if ($homeVideoTiktok)
                <a href="{{ $homeVideoTiktok }}" target="_blank" rel="noopener noreferrer" class="btn-ghost !gap-1.5 !px-2.5 !py-3 !text-sm whitespace-nowrap sm:!gap-2 sm:!px-6 sm:!py-3.5 sm:!text-base" data-test="home-video-tiktok">
                    Suivre sur TikTok <flux:icon.arrow-up-right class="size-4 sm:size-5" />
                </a>
            @endif
        </div>
    </section>
@endif
