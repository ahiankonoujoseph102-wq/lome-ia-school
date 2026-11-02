{{--
    Témoignages gérés dans l'admin (voir App\Support\Testimonials). À n'afficher que si la liste n'est pas vide.
    Novembre 2026 : les témoignages vidéo (souvent filmés au téléphone) sont présentés à part, dans une rangée qui défile
    sur le côté (x-site.video-testimonials), au format de la vidéo ; les autres restent en colonnes.
    « narrow » : deux colonnes au plus sur grand écran (pages des formations).
--}}
@props(['items', 'narrow' => false])

@php
    $videoItems = array_values(array_filter($items, fn (array $item): bool => $item['type'] === 'video'));
    $otherItems = array_values(array_filter($items, fn (array $item): bool => $item['type'] !== 'video'));
@endphp

<div {{ $attributes->class('space-y-8') }}>
@if ($videoItems)
    <x-site.video-testimonials :items="$videoItems" />
@endif

@if ($otherItems)
<div @class(['columns-1 gap-5 sm:columns-2', 'lg:columns-3' => ! $narrow])>
    @foreach ($otherItems as $testimonial)
        @php
            $author = collect([$testimonial['name'], $testimonial['formation']])->filter()->implode(', ');
        @endphp

        <figure class="card mb-5 flex break-inside-avoid flex-col gap-5 overflow-hidden p-6" data-reveal style="--reveal-delay: {{ $loop->index * 100 }}ms">
            @if ($testimonial['type'] === 'screenshot')
                <a href="{{ $testimonial['image'] }}" target="_blank" rel="noopener" class="-mx-6 -mt-6 block bg-night-950">
                    <img
                        src="{{ $testimonial['image'] }}"
                        alt="Capture d'écran d'un message WhatsApp{{ $author ? ' de '.$author : ' d\'un élève' }}"
                        class="mx-auto w-full"
                        loading="lazy"
                    >
                </a>
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-[#25D366]">
                    <x-site.whatsapp-icon class="size-4" /> Message reçu sur WhatsApp
                </span>
            @elseif ($testimonial['type'] === 'video')
                <div class="-mx-6 -mt-6 aspect-video bg-night-950" x-data="{ playing: false }">
                    <template x-if="playing">
                        <iframe
                            src="{{ YouTube::embedUrl($testimonial['youtube']) }}"
                            title="Témoignage vidéo{{ $author ? ' de '.$author : '' }}"
                            class="size-full"
                            allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                        ></iframe>
                    </template>
                    <button type="button" x-show="! playing" x-on:click="playing = true" class="group relative block size-full" aria-label="Lire le témoignage vidéo{{ $author ? ' de '.$author : '' }}">
                        <img src="{{ YouTube::thumbnailUrl($testimonial['youtube']) }}" alt="" class="size-full object-cover opacity-80 transition group-hover:opacity-100" loading="lazy">
                        <span class="absolute inset-0 grid place-items-center">
                            <span class="grid size-16 place-items-center rounded-full bg-gold-400 text-night-950 shadow-xl shadow-black/40 transition group-hover:scale-105">
                                <flux:icon.play class="size-7 translate-x-0.5" variant="solid" />
                            </span>
                        </span>
                    </button>
                </div>
            @else
                <svg viewBox="0 0 32 32" class="size-8 text-electric-400" fill="currentColor" aria-hidden="true">
                    <path d="M9.3 25.3c-2.4 0-4.6-2-4.6-5.3 0-4.8 3.3-9.4 8-11.3l.9 1.6c-2.8 1.6-4.5 3.9-4.7 6.3.3-.1.7-.1 1.1-.1 2.3 0 4 1.7 4 4.2 0 2.6-2 4.6-4.7 4.6Zm13.3 0c-2.4 0-4.6-2-4.6-5.3 0-4.8 3.3-9.4 8-11.3l.9 1.6c-2.8 1.6-4.5 3.9-4.7 6.3.3-.1.7-.1 1.1-.1 2.3 0 4 1.7 4 4.2 0 2.6-2 4.6-4.7 4.6Z" />
                </svg>
                <blockquote class="text-slate-200">« {{ $testimonial['text'] }} »</blockquote>
            @endif

            @if ($testimonial['name'] || $testimonial['formation'])
                <figcaption class="flex items-center gap-3">
                    @if ($testimonial['type'] === 'text')
                        @if ($testimonial['photo'])
                            <img src="{{ $testimonial['photo'] }}" alt="Photo de {{ $testimonial['name'] }}" class="size-11 rounded-full object-cover" loading="lazy" width="44" height="44">
                        @else
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-linear-to-br from-electric-500 to-electric-700 text-white" aria-hidden="true">
                                <flux:icon.user class="size-5" />
                            </span>
                        @endif
                    @endif
                    <span>
                        @if ($testimonial['name'])
                            <span class="block font-semibold text-white">{{ $testimonial['name'] }}</span>
                        @endif
                        @if ($testimonial['formation'])
                            <span class="block text-sm text-slate-400">{{ $testimonial['formation'] }}</span>
                        @endif
                    </span>
                </figcaption>
            @endif
        </figure>
    @endforeach
</div>
@endif
</div>
