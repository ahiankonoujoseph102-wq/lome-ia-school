{{--
    Lecteur YouTube léger, commun à la vidéo d'accueil, à la vidéo de la page Entreprises et à la vidéo de présentation
    d'une formation gratuite. Au chargement, seule l'image d'aperçu est demandée ; le lecteur youtube-nocookie.com n'est
    ajouté qu'au toucher du bouton lecture (composant Alpine « homeVideo » de site.js). Jamais de lecture automatique
    avant ce toucher. Sans JavaScript, le bouton est un simple lien vers YouTube. Vidéo illisible : message et lien
    « Regarder sur YouTube ».

    Trois présentations (novembre 2026 : Joseph met sur YouTube une miniature au format YouTube, 16:9) :
    - « portrait » : image d'aperçu 9:16 envoyée depuis l'admin (vidéo d'accueil) : aperçu étroit sur téléphone, qui
      s'agrandit au toucher ;
    - « grow » : vidéo verticale sans image envoyée : la miniature YouTube s'affiche en grand et entière, en 16:9, comme
      sur YouTube ; au toucher, le cadre passe en 9:16 pour que la vidéo verticale se lise sans bandes noires ;
    - « landscape » : vidéo horizontale : cadre 16:9 du début à la fin.
    Miniature : la version haute définition (maxresdefault, 1280 × 720) ; si YouTube ne l'a pas, sddefault (640 × 480)
    puis hqdefault (480 × 360), en 4:3 : leurs bandes noires sont retirées par le recadrage 16:9.

    Style stable face au mode sombre forcé : fonds foncés, contours dessinés en ombre, icône blanche, image jamais
    recolorée. Le cadre extérieur reçoit de l'appelant ses marges et son data-test ; la largeur dépend de la
    présentation. data-open = « true » pendant la lecture ou si la vidéo est illisible ; data-playing pendant la lecture.
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
    $hdThumbnail = YouTube::hdThumbnailUrl($videoId);
    $watchUrl = HomeVideo::watchUrl($videoId);
    $mode = $poster && $vertical ? 'portrait' : ($vertical ? 'grow' : 'landscape');
@endphp

<div
    {{ $attributes->class([
        'mx-auto w-40 transition-[width] duration-300 data-[open=true]:w-[min(78%,17rem)] sm:w-80 sm:data-[open=true]:w-80 motion-reduce:transition-none' => $mode === 'portrait',
        'mx-auto w-full max-w-2xl transition-[max-width] duration-300 data-[playing=true]:max-w-[min(100%,calc((100svh_-_7rem)*9/16),24rem)] motion-reduce:transition-none' => $mode === 'grow',
        'w-full' => $mode === 'landscape',
    ]) }}
    x-bind:data-open="(playing || failed) ? 'true' : 'false'"
    x-bind:data-playing="playing ? 'true' : 'false'"
    data-video-mode="{{ $mode }}"
    x-data="homeVideo"
>
    <div
        @class([
            'relative overflow-hidden bg-night-900 shadow-[0_0_0_1px_var(--color-night-600),0_24px_48px_-24px_rgb(0_0_0/0.7)]',
            'aspect-[9/16] rounded-3xl' => $mode === 'portrait',
            'aspect-video rounded-2xl sm:rounded-3xl' => $mode !== 'portrait',
        ])
        @if ($mode === 'grow') x-bind:class="playing ? '!aspect-[9/16]' : ''" @endif
    >
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
            @if ($mode === 'portrait')
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
            @else
                {{-- Miniature YouTube en haute définition, entière et sans bandes noires (repli : hqdefault). --}}
                <img
                    src="{{ $hdThumbnail }}"
                    data-fallback="{{ YouTube::sdThumbnailUrl($videoId) }} {{ $thumbnail }}"
                    alt=""
                    class="size-full object-cover transition duration-500 motion-safe:group-hover:scale-[1.03]"
                    width="1280"
                    height="720"
                    loading="lazy"
                    decoding="async"
                    x-init="checkThumbnail($el)"
                    x-on:load="checkThumbnail($el)"
                    x-on:error="thumbnailError($el)"
                    data-test="{{ $test }}-thumbnail"
                >
                {{-- Bouton lecture en bas à gauche : il ne cache jamais le titre écrit sur la miniature. --}}
                <span class="absolute inset-x-0 bottom-0 h-1/3 bg-linear-to-t from-night-950/70 to-transparent" aria-hidden="true"></span>
                <span class="absolute bottom-2 left-2 inline-flex items-center gap-2 rounded-full bg-night-950/85 p-1 text-sm sm:py-1.5 sm:pr-4 sm:pl-1.5 font-semibold text-white shadow-[0_0_0_1px_var(--color-night-600),0_8px_24px_rgb(0_0_0/0.45)] transition group-hover:bg-night-950 sm:bottom-4 sm:left-4 sm:text-base" data-test="{{ $test }}-play-label">
                    <span class="grid size-8 place-items-center rounded-full bg-night-900 shadow-[0_0_0_2px_var(--color-gold-500)] sm:size-10">
                        <flux:icon.play class="size-4 translate-x-px text-white sm:size-5" variant="solid" />
                    </span>
                    <span class="hidden sm:inline">Regarder la vidéo</span>
                </span>
            @endif
        </a>
    </div>
</div>
