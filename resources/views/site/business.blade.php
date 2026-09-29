@php
    use App\Support\PhoneNumber;
    use App\Support\WhatsApp;
    use App\Support\YouTube;

    $business = (array) config('site.business');
    $videoId = YouTube::id($business['video_url'] ?? null);
    $transcript = trim((string) ($business['transcript'] ?? ''));
    // Paragraphes séparés par une ligne vide ; un simple retour à la ligne est conservé.
    $paragraphs = $transcript === '' ? [] : preg_split('/\n\s*\n/', $transcript);
    $whatsappUrl = WhatsApp::url($business['whatsapp_message'] ?? null, $business['whatsapp_number'] ?? null);
    $whatsappDisplay = PhoneNumber::format('+'.ltrim((string) ($business['whatsapp_number'] ?? ''), '+'));
@endphp

<x-layouts::site
    title="Entreprises et centres de formation"
    description="Accompagnement des centres de formation et des entreprises dans l'intégration de l'intelligence artificielle : formation des employés, automatisation, agents IA, création de contenu."
    :whatsapp-message="$business['whatsapp_message'] ?? null"
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

                @if ($paragraphs !== [])
                    <details class="group border-t border-veil/10" data-test="business-transcript">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold text-white hover:bg-veil/5 [&::-webkit-details-marker]:hidden">
                            <span class="flex items-center gap-2"><flux:icon.document-text class="size-5 text-electric-300" /> Lire la transcription</span>
                            <flux:icon.chevron-down class="size-5 text-slate-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="space-y-4 px-5 pb-6 text-[15px] leading-relaxed text-slate-300">
                            @foreach ($paragraphs as $paragraph)
                                <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                            @endforeach
                        </div>
                    </details>
                @endif
            </div>
        @endif

        <div @class(['space-y-4 text-base leading-relaxed text-slate-300 sm:text-lg', 'mt-10' => $videoId]) data-reveal>
            @foreach ((array) ($business['intro'] ?? []) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        <div class="mt-8" data-reveal>
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn-whatsapp w-full sm:w-auto" data-test="business-whatsapp">
                <x-site.whatsapp-icon class="size-5 text-white" /> Discuter de votre projet sur WhatsApp
            </a>
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
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn-whatsapp mt-8 w-full sm:w-auto">
                    <x-site.whatsapp-icon class="size-5 text-white" /> Écrire sur WhatsApp
                </a>
                <p class="mt-4 text-sm text-slate-300">WhatsApp : {{ $whatsappDisplay }}</p>
            </div>
        </div>
    </section>
</x-layouts::site>
