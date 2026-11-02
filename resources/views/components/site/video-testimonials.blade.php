{{--
    Témoignages vidéo (novembre 2026, demande de Joseph : les miniatures recadrées en largeur faisaient amateur).
    Rangée qui défile sur le côté (sur téléphone : une carte et le début de la suivante, pour montrer qu'il y en a d'autres).
    Chaque carte est au format de la vidéo :
    - vertical (filmée au téléphone) : cadre 9:16, miniature au format d'origine de YouTube (oardefault), nette et
      sans bandes ; à défaut, la miniature courante recadrée sur la vidéo ;
    - horizontal : cadre 16:9, miniature haute définition (maxresdefault, puis sddefault, puis hqdefault) ;
    - auto (aucun choix dans l'admin) : vertical si YouTube a une miniature verticale au format d'origine, sinon
      horizontal (composant Alpine « videoTestimonial » de site.js).
    Seule la miniature est chargée (i.ytimg.com, chargement différé) ; le lecteur youtube-nocookie.com n'est ajouté
    qu'au toucher. Style stable face au mode sombre forcé : fonds foncés, contours en ombre, icône blanche.
--}}
@props(['items'])

@php
    use App\Support\YouTube;

    $sources = function (array $testimonial): array {
        $id = $testimonial['youtube'];
        $original = 'https://i.ytimg.com/vi/'.$id.'/oardefault.jpg';

        return match ($testimonial['video_format'] ?? 'auto') {
            'vertical' => [$original, YouTube::thumbnailUrl($id)],
            'horizontal' => [YouTube::hdThumbnailUrl($id), YouTube::sdThumbnailUrl($id), YouTube::thumbnailUrl($id)],
            default => [$original, YouTube::hdThumbnailUrl($id), YouTube::sdThumbnailUrl($id), YouTube::thumbnailUrl($id)],
        };
    };
@endphp

<div class="relative -mx-4 sm:mx-0" data-test="video-testimonials" x-data="{ scroll(direction) { const list = this.$refs.list; list.scrollBy({ left: direction * list.clientWidth * 0.8, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }) } }">
    {{-- Flèches sur ordinateur (sur téléphone, le doigt fait défiler la rangée). --}}
    @if (count($items) > 1)
        <div class="mb-4 hidden justify-end gap-2 sm:flex">
            <button type="button" x-on:click="scroll(-1)" class="grid size-10 place-items-center rounded-full bg-night-900 text-white shadow-[0_0_0_1px_var(--color-night-600)] transition hover:bg-night-800" aria-label="Témoignages précédents">
                <flux:icon.chevron-left class="size-5" />
            </button>
            <button type="button" x-on:click="scroll(1)" class="grid size-10 place-items-center rounded-full bg-night-900 text-white shadow-[0_0_0_1px_var(--color-night-600)] transition hover:bg-night-800" aria-label="Témoignages suivants">
                <flux:icon.chevron-right class="size-5" />
            </button>
        </div>
    @endif
    <ul x-ref="list" class="flex snap-x snap-mandatory items-start gap-4 overflow-x-auto px-4 pb-3 [scrollbar-width:thin] sm:px-0" aria-label="Témoignages vidéo">
        @foreach ($items as $testimonial)
            @php
                $format = $testimonial['video_format'] ?? 'auto';
                $author = collect([$testimonial['name'], $testimonial['formation']])->filter()->implode(', ');
                [$first, $rest] = [$sources($testimonial)[0], array_slice($sources($testimonial), 1)];
            @endphp
            <li
                x-data="videoTestimonial(@js($format))"
                x-bind:class="vertical ? 'w-[min(15rem,68vw)]' : 'w-[min(26rem,84vw)]'"
                @class([
                    'shrink-0 snap-start transition-[width] duration-300 motion-reduce:transition-none',
                    'w-[min(15rem,68vw)]' => $format !== 'horizontal',
                    'w-[min(26rem,84vw)]' => $format === 'horizontal',
                ])
                data-video-format="{{ $format }}"
            >
                <figure class="overflow-hidden rounded-2xl bg-night-900 shadow-[0_0_0_1px_var(--color-night-600)]">
                    <div
                        @class(['relative bg-night-950', 'aspect-[9/16]' => $format !== 'horizontal', 'aspect-video' => $format === 'horizontal'])
                        x-bind:class="vertical ? '!aspect-[9/16]' : '!aspect-video'"
                    >
                        <template x-if="playing">
                            <iframe
                                src="{{ YouTube::embedUrl($testimonial['youtube']) }}"
                                title="Témoignage vidéo{{ $author ? ' de '.$author : '' }}"
                                class="absolute inset-0 size-full"
                                allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                                referrerpolicy="strict-origin-when-cross-origin"
                            ></iframe>
                        </template>
                        <button type="button" x-show="! playing" x-on:click="playing = true" class="group absolute inset-0 block size-full" aria-label="Lire le témoignage vidéo{{ $author ? ' de '.$author : '' }}">
                            <img
                                src="{{ $first }}"
                                data-fallback="{{ implode(' ', $rest) }}"
                                alt=""
                                class="size-full object-cover transition duration-500 motion-safe:group-hover:scale-[1.03]"
                                loading="lazy"
                                decoding="async"
                                x-init="check($el)"
                                x-on:load="check($el)"
                                x-on:error="next($el)"
                                data-test="video-testimonial-thumbnail"
                            >
                            <span class="absolute inset-0 bg-linear-to-t from-night-950/80 via-transparent to-transparent" aria-hidden="true"></span>
                            <span class="absolute inset-0 grid place-items-center">
                                @include('site.partials.home-video-play-button')
                            </span>
                        </button>
                    </div>
                    @if ($testimonial['name'] || $testimonial['formation'])
                        <figcaption class="px-4 py-3">
                            @if ($testimonial['name'])
                                <span class="block truncate font-semibold text-white">{{ $testimonial['name'] }}</span>
                            @endif
                            @if ($testimonial['formation'])
                                <span class="block truncate text-sm text-slate-400">{{ $testimonial['formation'] }}</span>
                            @endif
                        </figcaption>
                    @endif
                </figure>
            </li>
        @endforeach
    </ul>
</div>
