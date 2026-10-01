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
    use App\Support\YouTube;

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
        <div
            class="mx-auto mt-4 w-40 transition-[width] duration-300 data-[open=true]:w-[min(78%,17rem)] sm:mt-8 sm:w-80 sm:data-[open=true]:w-80 motion-reduce:transition-none"
            x-bind:data-open="(playing || failed) ? 'true' : 'false'"
            x-data="homeVideo"
            data-test="home-video-frame"
            data-reveal
        >
            <div class="relative aspect-[9/16] overflow-hidden rounded-3xl bg-night-900 shadow-[0_0_0_1px_var(--color-night-600),0_24px_48px_-24px_rgb(0_0_0/0.7)]">
                <template x-if="playing">
                    <iframe
                        x-ref="frame"
                        src="{{ HomeVideo::embedUrl($homeVideoId) }}"
                        title="{{ HomeVideo::title() }}"
                        class="absolute inset-0 size-full"
                        allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                        x-on:load="listen()"
                    ></iframe>
                </template>

                <template x-if="failed">
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-night-900 p-6 text-center" data-test="home-video-fallback" role="status">
                        <flux:icon.exclamation-triangle class="size-8 text-slate-300" />
                        <p class="text-sm text-slate-200">Cette vidéo ne peut pas être lue ici pour le moment.</p>
                        <a href="{{ HomeVideo::watchUrl($homeVideoId) }}" target="_blank" rel="noopener noreferrer" class="btn-ghost !px-4 !py-2 !text-sm">
                            Regarder sur YouTube <flux:icon.arrow-up-right class="size-4" />
                        </a>
                    </div>
                </template>

                <a
                    href="{{ HomeVideo::watchUrl($homeVideoId) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    x-show="! playing && ! failed"
                    x-on:click.prevent="play()"
                    class="group absolute inset-0 block"
                    aria-label="Lire la vidéo : {{ HomeVideo::title() }}"
                    data-test="home-video-play"
                >
                    @if ($homeVideoPoster)
                        {{-- Image d'aperçu verticale envoyée depuis l'admin (WebP 9:16) : remplit le cadre sans rien couper. --}}
                        <img
                            src="{{ $homeVideoPoster }}"
                            alt=""
                            class="size-full object-cover"
                            width="{{ HomeVideo::POSTER_WIDTH }}"
                            height="{{ HomeVideo::POSTER_HEIGHT }}"
                            loading="lazy"
                            decoding="async"
                            data-test="home-video-poster"
                        >
                        <span class="absolute inset-0 grid place-items-center bg-night-950/20 transition group-hover:bg-night-950/5">
                            @include('site.partials.home-video-play-button')
                        </span>
                    @else
                        {{--
                            Miniature YouTube (4:3, avec bandes noires autour d'une image 16:9) : jamais recadrée en hauteur.
                            Fond = la même image floutée et assombrie (une seule image téléchargée) ; devant, la miniature
                            entière en 16:9 (les bandes noires du 4:3 sont retirées), puis le bouton lecture dessous.
                        --}}
                        <img
                            src="{{ YouTube::thumbnailUrl($homeVideoId) }}"
                            alt=""
                            class="absolute inset-0 size-full scale-125 object-cover opacity-70 blur-xl"
                            width="480"
                            height="360"
                            loading="lazy"
                            decoding="async"
                            aria-hidden="true"
                            data-test="home-video-backdrop"
                        >
                        <span class="absolute inset-0 bg-night-950/45"></span>
                        <span class="absolute inset-0 flex flex-col items-center justify-center gap-4 sm:gap-6">
                            <span class="relative block aspect-video w-full overflow-hidden shadow-[0_10px_30px_rgb(0_0_0/0.5)]" data-test="home-video-thumbnail">
                                <img
                                    src="{{ YouTube::thumbnailUrl($homeVideoId) }}"
                                    alt=""
                                    class="size-full object-cover"
                                    width="480"
                                    height="360"
                                    loading="lazy"
                                    decoding="async"
                                    x-on:error="fail()"
                                >
                            </span>
                            @include('site.partials.home-video-play-button')
                        </span>
                    @endif
                </a>
            </div>
        </div>

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
