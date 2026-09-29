@php
    use App\Support\YouTube;

    $business = (array) config('site.business');
    $videoId = YouTube::id($business['video_url'] ?? null);
    // Même réservation que la consultation gratuite, avec « Une entreprise » présélectionné (modifiable).
    $bookingUrl = route('consultation', ['profil' => 'entreprise']).'#reserver';
    $bookingNote = 'Appel vidéo gratuit de '.config('site.consultation.duration').' sur Google Meet, au créneau de votre choix.';
@endphp

<x-layouts::site
    title="Entreprises et centres de formation"
    description="Accompagnement des centres de formation et des entreprises dans l'intégration de l'intelligence artificielle : formation des employés, automatisation, agents IA, création de contenu."
>
    @include('site.partials.page-header', [
        'eyebrow' => 'Entreprises et centres de formation',
        'title' => $business['title'] ?? 'Entreprises et centres de formation',
    ])

    <section class="mx-auto max-w-4xl px-4 sm:px-6" data-test="business-page">
        @if ($videoId)
            {{-- Vidéo : le lecteur YouTube n'est chargé qu'au clic (page légère, aucun cookie avant). --}}
            <div class="card overflow-hidden p-0" data-test="business-video" data-reveal>
                <div class="aspect-video bg-night-950" x-data="{ playing: false }">
                    <template x-if="playing">
                        <iframe
                            src="{{ YouTube::embedUrl($videoId) }}"
                            title="Présentation vidéo : accompagnement des entreprises et des centres de formation"
                            class="size-full"
                            allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen
                        ></iframe>
                    </template>
                    <button type="button" x-show="! playing" x-on:click="playing = true" class="group relative block size-full" aria-label="Lire la vidéo de présentation">
                        <img src="{{ YouTube::thumbnailUrl($videoId) }}" alt="" class="size-full object-cover opacity-80 transition group-hover:opacity-100" loading="lazy">
                        <span class="absolute inset-0 grid place-items-center">
                            <span class="grid size-16 place-items-center rounded-full bg-night-950/80 text-white shadow-xl shadow-black/40 ring-2 ring-gold-400 transition group-hover:scale-105">
                                <flux:icon.play class="size-7 translate-x-0.5 text-gold-300" variant="solid" />
                            </span>
                        </span>
                    </button>
                </div>

            </div>
        @endif

        <div @class(['space-y-4 text-base leading-relaxed text-slate-300 sm:text-lg', 'mt-10' => $videoId]) data-reveal>
            @foreach ((array) ($business['intro'] ?? []) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        <div class="mt-8" data-reveal>
            <a href="{{ $bookingUrl }}" class="btn-gold w-full sm:w-auto" data-test="business-booking">
                <flux:icon.calendar-days class="size-5" /> Réserver un appel gratuit
            </a>
            <p class="mt-3 text-sm text-slate-400">{{ $bookingNote }}</p>
        </div>
    </section>

    <section class="section">
        <x-site.section-heading eyebrow="Services" title="Ce que je vous propose" />
        <ul class="mx-auto mt-10 grid max-w-4xl gap-3 sm:grid-cols-2" data-test="business-services">
            @foreach ((array) ($business['services'] ?? []) as $service)
                <li class="flex items-start gap-3 rounded-2xl bg-veil/[0.04] p-4 ring-1 ring-veil/10" data-reveal style="--reveal-delay: {{ min($loop->index, 6) * 40 }}ms">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30">
                        <flux:icon.check class="size-5" />
                    </span>
                    <span class="pt-1.5 text-slate-100">{{ $service }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="section !pt-0">
        <div class="relative overflow-hidden rounded-[2rem] border border-veil/10 bg-linear-to-br from-electric-700 via-night-700 to-night-900 px-6 py-12 text-center sm:px-12 sm:py-16" data-reveal>
            <div class="glow pointer-events-none absolute inset-0"></div>
            <div class="grid-pattern pointer-events-none absolute inset-0"></div>
            <div class="relative mx-auto max-w-2xl">
                <h2 class="text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">Parlons de votre projet</h2>
                <p class="mt-4 text-base text-pretty text-slate-200 sm:text-lg">Présentez-moi votre centre de formation ou votre entreprise et vos besoins : nous verrons ensemble comment avancer.</p>
                <a href="{{ $bookingUrl }}" class="btn-gold mt-8 w-full sm:w-auto">
                    <flux:icon.calendar-days class="size-5" /> Réserver un appel gratuit
                </a>
                <p class="mt-4 text-sm text-slate-300">{{ $bookingNote }}</p>
            </div>
        </div>
    </section>
</x-layouts::site>
