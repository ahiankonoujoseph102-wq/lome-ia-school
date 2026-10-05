{{--
    Lecteur YouTube léger, commun à la vidéo d'accueil et à la vidéo de présentation d'une formation gratuite.
    Au chargement, seule l'image d'aperçu est demandée ; le lecteur youtube-nocookie.com n'est ajouté qu'au toucher
    du bouton lecture (composant Alpine « homeVideo » de site.js). Jamais de lecture automatique avant ce toucher.
    Sans JavaScript, le bouton est un simple lien vers YouTube. Vidéo illisible : message et lien « Regarder sur YouTube ».
    Vertical (9:16) : image d'aperçu envoyée, sinon la miniature YouTube entière sur un fond flouté de la même image.
    Horizontal (16:9) : la miniature remplit le cadre (les bandes noires de la miniature 4:3 sont retirées).
    Style stable face au mode sombre forcé : fonds foncés, contours dessinés en ombre, icône blanche, image jamais recolorée.
    Le cadre extérieur (largeur, marges, data-test) est donné par l'appelant ; l'attribut data-open passe à « true »
    pendant la lecture (ou si la vidéo est illisible), pour qu'un aperçu étroit puisse s'agrandir.
--}}
@props([
    'videoId',
    'title',
    'vertical' => true,
    'poster' => null,
    'posterWidth' => null,
    'posterHeight' => null,
    'test' => 'home-video',
])

@php
    use App\Support\HomeVideo;
    use App\Support\YouTube;

    $thumbnail = YouTube::thumbnailUrl($videoId);
    $watchUrl = HomeVideo::watchUrl($videoId);
@endphp

<div
    {{ $attributes }}
    x-bind:data-open="(playing || failed) ? 'true' : 'false'"
    x-data="homeVideo"
>
    <div @class([
        'relative overflow-hidden bg-night-900 shadow-[0_0_0_1px_var(--color-night-600),0_24px_48px_-24px_rgb(0_0_0/0.7)]',
        'aspect-[9/16] rounded-3xl' => $vertical,
        'aspect-video rounded-2xl sm:rounded-3xl' => ! $vertical,
    ])>
        <template x-if="playing">
            <iframe
                x-ref="frame"
                src="{{ HomeVideo::embedUrl($videoId) }}"
                title="{{ $title }}"
                class="absolute inset-0 size-full"
                allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"
                x-on:load="listen()"
            ></iframe>
        </template>

        <template x-if="failed">
            <div class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-night-900 p-6 text-center" data-test="{{ $test }}-fallback" role="status">
                <flux:icon.exclamation-triangle class="size-8 text-slate-300" />
                <p class="text-sm text-slate-200">Cette vidéo ne peut pas être lue ici pour le moment.</p>
                <a href="{{ $watchUrl }}" target="_blank" rel="noopener noreferrer" class="btn-ghost !px-4 !py-2 !text-sm">
                    Regarder sur YouTube <flux:icon.arrow-up-right class="size-4" />
                </a>
            </div>
        </template>

        <a
            href="{{ $watchUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            x-show="! playing && ! failed"
            x-on:click.prevent="play()"
            class="group absolute inset-0 block"
            aria-label="Lire la vidéo : {{ $title }}"
            data-test="{{ $test }}-play"
        >
            @if ($poster)
                {{-- Image d'aperçu envoyée depuis l'admin : remplit le cadre sans rien couper. --}}
                <img
                    src="{{ $poster }}"
                    alt=""
                    class="size-full object-cover"
                    @if ($posterWidth) width="{{ $posterWidth }}" @endif
                    @if ($posterHeight) height="{{ $posterHeight }}" @endif
                    loading="lazy"
                    decoding="async"
                    data-test="{{ $test }}-poster"
                >
                <span class="absolute inset-0 grid place-items-center bg-night-950/20 transition group-hover:bg-night-950/5">
                    @include('site.partials.home-video-play-button')
                </span>
            @elseif ($vertical)
                {{--
                    Miniature YouTube (4:3, avec bandes noires autour d'une image 16:9) : jamais recadrée en hauteur.
                    Fond = la même image floutée et assombrie (une seule image téléchargée) ; devant, la miniature
                    entière en 16:9 (les bandes noires du 4:3 sont retirées), puis le bouton lecture dessous.
                --}}
                <img
                    src="{{ $thumbnail }}"
                    alt=""
                    class="absolute inset-0 size-full scale-125 object-cover opacity-70 blur-xl"
                    width="480"
                    height="360"
                    loading="lazy"
                    decoding="async"
                    aria-hidden="true"
                    data-test="{{ $test }}-backdrop"
                >
                <span class="absolute inset-0 bg-night-950/45"></span>
                <span class="absolute inset-0 flex flex-col items-center justify-center gap-4 sm:gap-6">
                    <span class="relative block aspect-video w-full overflow-hidden shadow-[0_10px_30px_rgb(0_0_0/0.5)]" data-test="{{ $test }}-thumbnail">
                        <img
                            src="{{ $thumbnail }}"
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
            @else
                {{-- Horizontal : la miniature 4:3 recadrée en 16:9 montre l'image sans ses bandes noires. --}}
                <img
                    src="{{ $thumbnail }}"
                    alt=""
                    class="size-full object-cover"
                    width="480"
                    height="360"
                    loading="lazy"
                    decoding="async"
                    x-on:error="fail()"
                    data-test="{{ $test }}-thumbnail"
                >
                <span class="absolute inset-0 grid place-items-center bg-night-950/20 transition group-hover:bg-night-950/5">
                    @include('site.partials.home-video-play-button')
                </span>
            @endif
        </a>
    </div>
</div>
