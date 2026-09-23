@php
    use App\Support\Placeholder;
    use App\Support\WhatsApp;
    use Illuminate\Support\Str;

    // Le bouton d'achat mène au récapitulatif de commande (paiement FedaPay à l'étape 4).
    $buyUrl = route('checkout.show', $formation);
    $whatsappMessage = 'Bonjour Lomé IA School, j\'ai une question sur la formation « '.$formation->title.' ».';
    $trainer = config('site.trainer');

    $course = [
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => $formation->title,
        'description' => Str::limit($formation->description, 300),
        'inLanguage' => 'fr',
        'url' => route('formations.show', $formation),
        'image' => route('og.formation', $formation),
        'provider' => ['@type' => 'EducationalOrganization', 'name' => config('app.name'), 'url' => route('home')],
        'offers' => [
            '@type' => 'Offer',
            'price' => $formation->price,
            'priceCurrency' => 'XOF',
            'availability' => 'https://schema.org/InStock',
            'category' => 'Paid',
            'url' => route('formations.show', $formation),
        ],
    ];
@endphp

<x-layouts::site
    :title="$formation->title"
    :description="Str::limit($formation->subtitle ?: $formation->description, 155)"
    :image="route('og.formation', $formation).'?v='.$formation->updated_at?->timestamp"
    type="product"
    :json-ld="[$course]"
    :sticky-cta="true"
    :whatsapp-message="$whatsappMessage"
>
    {{-- En-tête de la formation --}}
    <section class="relative isolate overflow-hidden">
        <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>

        <div class="mx-auto max-w-6xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10 lg:pb-16">
            <nav aria-label="Fil d'Ariane" class="text-sm text-slate-400">
                <ol class="flex flex-wrap items-center gap-1.5">
                    <li><a href="{{ route('home') }}" class="hover:text-white">Accueil</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('formations.index') }}" class="hover:text-white">Formations</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('formations.index', ['categorie' => $formation->category->value]) }}" class="hover:text-white">{{ $formation->category->shortLabel() }}</a></li>
                </ol>
            </nav>

            <div class="mt-6 grid items-center gap-8 lg:grid-cols-[1.15fr_0.85fr] lg:gap-12">
                <div class="order-2 lg:order-1">
                    <span class="eyebrow" data-reveal>
                        <flux:icon :icon="$formation->category->icon()" class="size-3.5" /> {{ $formation->category->label() }}
                    </span>
                    <h1 class="mt-4 text-3xl leading-tight font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">
                        {{ $formation->title }}
                    </h1>
                    @if ($formation->subtitle)
                        <p class="mt-4 text-lg text-pretty text-slate-300" data-reveal style="--reveal-delay: 120ms">{{ $formation->subtitle }}</p>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-2" data-reveal style="--reveal-delay: 160ms">
                        @foreach ($formation->formats as $format)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/5 px-3 py-1.5 text-sm text-slate-200 ring-1 ring-white/10">
                                <flux:icon :icon="$format->icon()" class="size-4 text-electric-300" /> {{ $format->label() }}
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center" data-reveal style="--reveal-delay: 200ms">
                        <div>
                            <p class="text-sm text-slate-400">Prix de la formation</p>
                            <p class="text-4xl font-bold text-gold-300">{{ $formation->formatted_price }}</p>
                        </div>
                        <div class="flex flex-col gap-3 sm:ml-auto sm:flex-row lg:ml-8">
                            <a href="{{ $buyUrl }}" class="btn-gold">Acheter maintenant <flux:icon.arrow-right class="size-5" /></a>
                            <a href="{{ WhatsApp::url($whatsappMessage) }}" target="_blank" rel="noopener" class="btn-ghost">
                                <x-site.whatsapp-icon class="size-5 text-[#25D366]" /> Poser une question
                            </a>
                        </div>
                    </div>
                </div>

                <div class="order-1 lg:order-2" data-reveal>
                    <x-site.formation-cover :formation="$formation" class="aspect-[16/10] rounded-3xl shadow-2xl shadow-electric-900/50 ring-1 ring-white/10" eager />
                </div>
            </div>
        </div>
    </section>

    {{-- Garanties --}}
    <section class="border-y border-white/10 bg-white/[0.02]">
        <ul class="mx-auto grid max-w-6xl gap-4 px-4 py-6 sm:grid-cols-3 sm:px-6">
            @foreach (config('site.guarantees') as $guarantee)
                <li class="flex items-start gap-3" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-gold-400/15 text-gold-300">
                        <flux:icon :icon="$guarantee['icon']" class="size-5" />
                    </span>
                    <span>
                        <span class="block font-semibold text-white">{{ $guarantee['title'] }}</span>
                        <span class="block text-sm text-slate-400">{{ $guarantee['text'] }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    <div class="mx-auto grid max-w-6xl gap-12 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_340px] lg:py-20">
        <div class="min-w-0 space-y-16">
            {{-- Présentation --}}
            <section data-reveal>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Présentation</h2>
                <p class="mt-4 text-base leading-relaxed text-pretty text-slate-300 sm:text-lg">{{ $formation->description }}</p>
            </section>

            {{-- Ce que vous allez apprendre --}}
            @if ($formation->outcomes)
                <section>
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Ce que vous allez apprendre</h2>
                    <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach ($formation->outcomes as $outcome)
                            <li class="card flex gap-3 p-4" data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-electric-500/20 text-electric-300">
                                    <flux:icon.check class="size-4" />
                                </span>
                                <span class="text-slate-200">{{ $outcome }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Programme --}}
            @if ($formation->curriculum)
                <section>
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Programme de la formation</h2>
                    <p class="mt-2 text-slate-400" data-reveal>{{ count($formation->curriculum) }} modules pour progresser pas à pas.</p>
                    <div class="mt-6 space-y-3">
                        @foreach ($formation->curriculum as $module)
                            <details class="card group overflow-hidden" data-reveal @if ($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-center gap-4 p-4 sm:p-5">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-sm font-bold text-electric-300 ring-1 ring-electric-400/25">
                                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="flex-1">
                                        <span class="block font-semibold text-white">{{ $module['title'] }}</span>
                                        <span class="block text-sm text-slate-400">{{ count($module['lessons']) }} leçons</span>
                                    </span>
                                    <flux:icon.chevron-down class="size-5 shrink-0 text-slate-400 transition duration-300 group-open:rotate-180" />
                                </summary>
                                <ul class="space-y-2.5 border-t border-white/10 px-4 py-4 sm:px-5">
                                    @foreach ($module['lessons'] as $lesson)
                                        <li class="flex items-start gap-3 text-slate-300">
                                            <flux:icon.play-circle class="mt-0.5 size-5 shrink-0 text-electric-400" /> {{ $lesson }}
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Pour qui --}}
            @if ($formation->audience)
                <section>
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Pour qui est cette formation ?</h2>
                    <ul class="mt-6 space-y-3">
                        @foreach ($formation->audience as $person)
                            <li class="flex items-start gap-3 text-slate-200" data-reveal>
                                <flux:icon.user-group class="mt-0.5 size-5 shrink-0 text-gold-400" /> {{ $person }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Bonus --}}
            @if ($formation->bonuses)
                <section class="relative overflow-hidden rounded-3xl border border-gold-400/25 bg-linear-to-br from-gold-400/10 to-transparent p-6 sm:p-8" data-reveal>
                    <h2 class="flex items-center gap-3 text-2xl font-bold text-white">
                        <flux:icon.gift class="size-7 text-gold-300" /> Bonus inclus
                    </h2>
                    <ul class="mt-5 space-y-3">
                        <li class="flex items-start gap-3 text-slate-200">
                            <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-gold-300" /> L'accès au groupe privé de discussion de la formation
                        </li>
                        @foreach ($formation->bonuses as $bonus)
                            <li class="flex items-start gap-3 text-slate-200">
                                <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-gold-300" /> {{ $bonus }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Formateur --}}
            <section data-reveal>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Votre formateur</h2>
                <div class="card mt-6 flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
                    @include('site.partials.trainer-photo', ['size' => 'size-20'])
                    <div>
                        <p class="text-lg font-semibold text-white">{{ $trainer['name'] }}</p>
                        <p class="text-sm text-electric-300">{{ $trainer['role'] }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-300">{{ Placeholder::highlight($trainer['short_bio']) }}</p>
                        <a href="{{ route('about') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-electric-300 hover:text-white">
                            En savoir plus <flux:icon.arrow-right class="size-4" />
                        </a>
                    </div>
                </div>
            </section>

            {{-- Témoignages --}}
            <section>
                <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Ce qu'en disent les élèves</h2>
                <x-site.testimonials class="mt-6 md:!grid-cols-1 xl:!grid-cols-3" />
            </section>

            {{-- FAQ --}}
            <section>
                <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Questions fréquentes</h2>
                <x-site.faq :items="config('site.faq.formation')" class="mt-6" />
            </section>
        </div>

        {{-- Encadré d'achat (écrans larges) --}}
        <aside class="hidden lg:block">
            <div class="card sticky top-24 space-y-5 p-6">
                <p class="text-sm text-slate-400">Prix de la formation</p>
                <p class="-mt-3 text-4xl font-bold text-gold-300">{{ $formation->formatted_price }}</p>
                <a href="{{ $buyUrl }}" class="btn-gold w-full">Acheter maintenant</a>
                <ul class="space-y-3 text-sm text-slate-300">
                    @foreach (config('site.guarantees') as $guarantee)
                        <li class="flex items-center gap-2"><flux:icon :icon="$guarantee['icon']" class="size-4 text-gold-400" /> {{ $guarantee['title'] }}</li>
                    @endforeach
                </ul>
                <div class="border-t border-white/10 pt-5">
                    <p class="text-sm text-slate-400">Une question avant d'acheter ?</p>
                    <a href="{{ WhatsApp::url($whatsappMessage) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-[#25D366]">
                        <x-site.whatsapp-icon class="size-5" /> Écrire sur WhatsApp
                    </a>
                </div>
            </div>
        </aside>
    </div>

    {{-- Appel à l'action final --}}
    <x-site.cta-banner
        :title="'Prêt à commencer « '.$formation->title.' » ?'"
        text="Payez par Mobile Money et accédez immédiatement à la formation et à son groupe privé."
    >
        <a href="{{ $buyUrl }}" class="btn-gold">Acheter maintenant · {{ $formation->formatted_price }}</a>
        <a href="{{ route('consultation') }}" class="btn-ghost">Réserver une consultation gratuite</a>
    </x-site.cta-banner>

    {{-- Autres formations --}}
    @if ($related->isNotEmpty())
        <section class="section !pt-0">
            <x-site.section-heading title="Vous aimerez aussi" align="left" />
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <x-site.formation-card :formation="$item" :delay="$loop->index * 70" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Barre d'achat fixée en bas de l'écran (mobile et tablette) --}}
    <x-slot:after>
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-night-950/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl lg:hidden">
            <div class="mx-auto flex max-w-xl items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs text-slate-400">{{ $formation->title }}</p>
                    <p class="text-lg leading-tight font-bold text-gold-300">{{ $formation->formatted_price }}</p>
                </div>
                <a href="{{ $buyUrl }}" class="btn-gold shrink-0 !px-5 !py-3">Acheter maintenant</a>
            </div>
        </div>
    </x-slot:after>
</x-layouts::site>
