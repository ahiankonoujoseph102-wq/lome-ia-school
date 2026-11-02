@php
    use App\Support\FormationShare;
    use App\Support\LiveOffer;
    use App\Support\Placeholder;
    use App\Support\Testimonials;
    use App\Support\WhatsApp;
    use Illuminate\Support\Str;

    // Le bouton d'achat mène au récapitulatif de commande, où se fait le paiement Mobile Money.
    $buyUrl = route('checkout.show', $formation);
    $whatsappMessage = \App\Support\WhatsAppMessages::formation($formation);
    $trainer = config('site.trainer');
    $testimonials = Testimonials::all();

    // Formation proposée en direct pendant une période limitée (config/live_offers.php), sinon null.
    $liveOffer = LiveOffer::for($formation);

    // Photo d'illustration en grand en haut de la page (sinon : visuel à droite, comme avant).
    $heroPhoto = \App\Support\FormationPhotos::for($formation);

    $course = [
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => $formation->title,
        'description' => Str::limit($formation->description, 300),
        'inLanguage' => 'fr',
        'url' => FormationShare::url($formation),
        'image' => FormationShare::imageUrl($formation),
        'provider' => ['@type' => 'EducationalOrganization', 'name' => config('app.name'), 'url' => \App\Support\AppUrl::to(route('home'))],
        'offers' => [
            '@type' => 'Offer',
            'price' => $formation->price,
            'priceCurrency' => 'XOF',
            'availability' => 'https://schema.org/InStock',
            'category' => 'Paid',
            'url' => FormationShare::url($formation),
        ],
    ];

    if ($liveOffer) {
        $course['hasCourseInstance'] = [
            '@type' => 'CourseInstance',
            'courseMode' => 'online',
            'location' => ['@type' => 'VirtualLocation', 'name' => 'Google Meet'],
            'startDate' => $liveOffer->startsAt()->toIso8601String(),
            'endDate' => $liveOffer->endsAt()->toIso8601String(),
        ];
    }
@endphp

<x-layouts::site
    :title="$formation->title"
    :description="$liveOffer ? $liveOffer->shareDescription() : Str::limit($formation->subtitle ?: $formation->description, 155)"
    :image="FormationShare::imageUrl($formation)"
    :image-type="FormationShare::imageType($formation)"
    type="product"
    :json-ld="[$course]"
    :sticky-cta="true"
    :whatsapp-message="$whatsappMessage"
>
    {{-- En-tête de la formation --}}
    <section class="relative isolate overflow-hidden" @if ($heroPhoto) data-test="formation-photo-hero" @endif>
        @if ($heroPhoto)
            @include('site.formations.partials.photo-hero-background', ['photo' => $heroPhoto])
        @else
            <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
            <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
        @endif

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

            <div @class(['mt-6 grid items-center gap-8 lg:gap-12', 'lg:grid-cols-[1.15fr_0.85fr]' => ! $heroPhoto, 'pt-10 sm:pt-16 lg:pt-24' => $heroPhoto])>
                <div @class(['order-2 lg:order-1', 'max-w-3xl' => $heroPhoto])>
                    <span class="eyebrow" data-reveal>
                        <flux:icon :icon="$formation->category->icon()" class="size-3.5" /> {{ $formation->category->label() }}
                    </span>
                    <h1 class="mt-4 text-3xl leading-tight font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">
                        {{ $formation->title }}
                    </h1>
                    @if ($formation->subtitle)
                        <p class="mt-4 text-lg text-pretty text-slate-300" data-reveal style="--reveal-delay: 120ms">{{ $formation->subtitle }}</p>
                    @endif

                    @if ($liveOffer)
                        {{-- Formation en direct : dates et horaires dès le haut de la page, lien vers l'encadré --}}
                        <a href="#formation-en-direct" class="mt-5 flex items-start gap-3 rounded-2xl bg-night-900 p-4 ring-1 ring-gold-500/50 hover:bg-night-800" data-reveal style="--reveal-delay: 160ms" data-test="live-offer-summary">
                            <span class="relative mt-1.5 flex size-2.5 shrink-0">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-rose-400 opacity-75 motion-reduce:hidden"></span>
                                <span class="relative inline-flex size-2.5 rounded-full bg-rose-500"></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-white">Formation en direct sur Google Meet</span>
                                <span class="block text-sm text-slate-300">{{ $liveOffer->period() }} · {{ $liveOffer->sessionCount() }} séances, {{ Str::lcfirst($liveOffer->schedule()) }}</span>
                                <span class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-gold-300">Dates, programme et questions <flux:icon.arrow-down class="size-4" /></span>
                            </span>
                        </a>
                    @else
                        <div class="mt-5 flex flex-wrap gap-2" data-reveal style="--reveal-delay: 160ms">
                            @foreach ($formation->formats as $format)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-veil/5 px-3 py-1.5 text-sm text-slate-200 ring-1 ring-veil/10">
                                    <flux:icon :icon="$format->icon()" class="size-4 text-electric-300" /> {{ $format->label() }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <ul class="mt-6 flex flex-wrap gap-2" data-reveal style="--reveal-delay: 180ms">
                        @foreach (config('site.lifetime.items') as $item)
                            <li class="inline-flex items-center gap-1.5 rounded-full bg-gold-400/15 px-3 py-1.5 text-sm font-semibold text-gold-200 ring-1 ring-gold-400/40">
                                <flux:icon :icon="$item['icon']" class="size-4" /> {{ $item['title'] }}
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center" data-reveal style="--reveal-delay: 200ms">
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

                    {{-- Partage : visible par tous, connectés ou non --}}
                    <x-site.share-formation :formation="$formation" class="mt-4" data-reveal style="--reveal-delay: 220ms" />
                </div>

                @unless ($heroPhoto)
                    <div class="order-1 lg:order-2" data-reveal>
                        <x-site.formation-cover :formation="$formation" class="aspect-[16/10] rounded-3xl shadow-2xl shadow-electric-900/50 ring-1 ring-veil/10" eager />
                    </div>
                @endunless
            </div>
        </div>
    </section>

    {{-- Garanties --}}
    <section class="border-y border-veil/10 bg-veil/[0.02]">
        <ul class="mx-auto grid max-w-6xl gap-4 px-4 py-6 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
            @foreach ($formation->guarantees() as $guarantee)
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
            {{-- Formation en direct : dates, horaires, déroulement, programme --}}
            @if ($liveOffer)
                <x-site.live-offer :offer="$liveOffer" :has-access="$hasAccess" />
            @endif

            {{-- Présentation --}}
            <section data-reveal>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Présentation</h2>
                <x-site.rich-text :text="$formation->description" class="mt-4 text-base leading-relaxed text-pretty text-slate-300 sm:text-lg" />
            </section>

            {{-- Prochaine session ponctuelle : sans lien Google Meet, réservée aux élèves de la formation --}}
            @if ($nextSession && ! $liveOffer)
                <section id="prochaine-session" class="scroll-mt-24" data-test="next-session">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl">Prochaine session</h2>
                    <div class="card mt-5 p-5 sm:p-6">
                        <x-site.session-summary :session="$nextSession" />
                        <div class="mt-5 border-t border-veil/10 pt-5">
                            @if ($hasAccess)
                                <a href="{{ route('student.sessions.show', $nextSession) }}" class="btn-gold w-full sm:w-auto">Voir la session dans mon espace</a>
                            @else
                                <p class="text-sm text-slate-300">Session réservée aux élèves de la formation{{ $nextSession->isOnsite() ? ', sur inscription' : '' }}. Achetez la formation pour y participer.</p>
                                <a href="{{ route('checkout.show', $formation) }}" class="btn-gold mt-4 w-full sm:w-auto">Acheter la formation pour participer</a>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

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

            {{-- Programme : uniquement les vraies leçons publiées (masqué s'il n'y en a aucune) --}}
            @php
                $curriculum = $formation->publicCurriculum();
                $lessonCount = $formation->orderedPublishedLessons()->count();
                $totalDuration = $formation->totalDurationSeconds();
            @endphp
            @if ($curriculum->isNotEmpty())
                <section id="programme" class="scroll-mt-24">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Programme de la formation</h2>
                    <p class="mt-2 text-slate-400" data-reveal>
                        {{ $curriculum->count() }} {{ $curriculum->count() > 1 ? 'modules' : 'module' }} · {{ $lessonCount }} {{ $lessonCount > 1 ? 'leçons' : 'leçon' }}@if ($totalDuration) · {{ \App\Models\Lesson::formatDuration($totalDuration) }} de vidéo @endif
                    </p>
                    <div class="mt-6 space-y-3">
                        @foreach ($curriculum as $module)
                            <details class="card group overflow-hidden" data-reveal @if ($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-center gap-4 p-4 sm:p-5">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-sm font-bold text-electric-300 ring-1 ring-electric-400/25">
                                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="flex-1">
                                        <span class="block font-semibold text-white">{{ $module->title }}</span>
                                        <span class="block text-sm text-slate-400">{{ $module->lessons->count() }} {{ $module->lessons->count() > 1 ? 'leçons' : 'leçon' }}</span>
                                    </span>
                                    <flux:icon.chevron-down class="size-5 shrink-0 text-slate-400 transition duration-300 group-open:rotate-180" />
                                </summary>
                                <ul class="space-y-1 border-t border-veil/10 px-2 py-3 sm:px-3">
                                    @foreach ($module->lessons as $lesson)
                                        <li>
                                            @if ($lesson->is_preview)
                                                <a href="{{ route('lessons.show', [$formation, $lesson]) }}" class="flex items-center gap-3 rounded-2xl px-2 py-2 text-slate-200 hover:bg-veil/5">
                                                    <flux:icon.play-circle class="size-5 shrink-0 text-gold-300" />
                                                    <span class="min-w-0 flex-1">{{ $lesson->title }}</span>
                                                    <span class="shrink-0 rounded-full bg-gold-400/15 px-2 py-0.5 text-xs font-semibold text-gold-200 ring-1 ring-gold-400/30">Aperçu gratuit</span>
                                                </a>
                                            @else
                                                <span class="flex items-center gap-3 px-2 py-2 text-slate-300">
                                                    <flux:icon.lock-closed class="size-5 shrink-0 text-slate-500" />
                                                    <span class="min-w-0 flex-1">{{ $lesson->title }}</span>
                                                    @if ($lesson->formatted_duration)
                                                        <span class="shrink-0 text-xs text-slate-500">{{ $lesson->formatted_duration }}</span>
                                                    @endif
                                                </span>
                                            @endif
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

            {{-- Métiers où servent ces compétences (masqué si la liste est vide ; jamais présenté comme un emploi promis) --}}
            @if ($formation->careers)
                <section data-test="formation-careers">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Des métiers où ces compétences servent</h2>
                    <p class="mt-2 text-slate-400" data-reveal>Les compétences de cette formation sont utiles dans ces métiers.</p>
                    <ul class="mt-6 flex flex-wrap gap-2">
                        @foreach ($formation->careers as $career)
                            <li class="rounded-full bg-night-900 px-4 py-2 text-sm font-medium text-slate-100 ring-1 ring-night-600" data-reveal style="--reveal-delay: {{ $loop->index * 50 }}ms">{{ $career }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Inclus avec la formation --}}
            <section class="card p-6 sm:p-8" data-reveal>
                <h2 class="flex items-center gap-3 text-2xl font-bold text-white">
                    <flux:icon.check-badge class="size-7 text-gold-300" /> Inclus avec la formation
                </h2>
                <ul class="mt-5 space-y-3">
                    @foreach ($liveOffer ? $liveOffer->included() : config('site.included') as $item)
                        <li class="flex items-start gap-3 text-slate-200">
                            <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-gold-300" /> {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Bonus (config/site.php, masqués tant que la liste de la formation est vide) --}}
            @if ($formation->bonuses)
                <section class="relative overflow-hidden rounded-3xl border border-gold-400/25 bg-linear-to-br from-gold-400/10 to-transparent p-6 sm:p-8" data-reveal>
                    <h2 class="flex items-center gap-3 text-2xl font-bold text-white">
                        <flux:icon.gift class="size-7 text-gold-300" /> Bonus inclus
                    </h2>
                    <ul class="mt-5 space-y-3">
                        @foreach ($formation->bonuses as $bonus)
                            <li class="flex items-start gap-3 text-slate-200">
                                <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-gold-300" /> {{ $bonus }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Après la formation : attestation, stage, recrutement possible (config site.after_training) --}}
            @if ($afterTraining = $formation->afterTraining())
                <section data-test="formation-after-training">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Après la formation</h2>
                    <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach ($afterTraining as $item)
                            <li @class(['card flex gap-4 p-5', 'sm:col-span-2' => $loop->last && $loop->count % 2 === 1]) data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-night-800 text-gold-300 ring-1 ring-night-600">
                                    <flux:icon :icon="$item['icon']" class="size-5" />
                                </span>
                                <span>
                                    <span class="block font-semibold text-white">{{ $item['title'] }}</span>
                                    <span class="mt-1 block text-sm leading-relaxed text-slate-300">{{ $item['text'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Présentiel au bureau : consultation gratuite ou message (config site.in_person) --}}
            @if (filled(config('site.in_person.title')))
                <section class="rounded-3xl bg-night-900 p-6 shadow-[inset_0_0_0_1px_var(--color-night-600)] sm:p-8" data-reveal data-test="formation-in-person">
                    <h2 class="flex items-center gap-3 text-xl font-bold text-white sm:text-2xl">
                        <flux:icon.map-pin class="size-6 shrink-0 text-gold-300" /> {{ config('site.in_person.title') }}
                    </h2>
                    <p class="mt-3 leading-relaxed text-slate-300">{{ str_replace('{adresse}', config('site.contact.address'), config('site.in_person.text')) }}</p>
                    <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('consultation') }}#reserver" class="btn-ghost">Réserver une consultation gratuite</a>
                        <a href="{{ WhatsApp::url($whatsappMessage) }}" target="_blank" rel="noopener" class="btn-ghost">
                            <x-site.whatsapp-icon class="size-5 text-[#25D366]" /> Écrire sur WhatsApp
                        </a>
                    </div>
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

            {{-- Témoignages (masqués tant qu'il n'y en a aucun) --}}
            @if ($testimonials)
                <section>
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Ce qu'en disent les élèves</h2>
                    <x-site.testimonials :items="$testimonials" class="mt-6" narrow />
                </section>
            @endif

            {{-- FAQ --}}
            <section>
                <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Questions fréquentes</h2>
                <x-site.faq :items="$liveOffer ? $liveOffer->faq() : [...config('site.faq.formation'), ...$formation->extraFaq()]" class="mt-6" data-test="formation-faq" />
            </section>
        </div>

        {{-- Encadré d'achat (écrans larges) --}}
        <aside class="hidden lg:block">
            <div class="card sticky top-24 space-y-5 p-6">
                <p class="text-sm text-slate-400">Prix de la formation</p>
                <p class="-mt-3 text-4xl font-bold text-gold-300">{{ $formation->formatted_price }}</p>
                <a href="{{ $buyUrl }}" class="btn-gold w-full">Acheter maintenant</a>
                <ul class="space-y-3 text-sm text-slate-300">
                    @foreach ($formation->guarantees() as $guarantee)
                        <li class="flex items-center gap-2"><flux:icon :icon="$guarantee['icon']" class="size-4 text-gold-400" /> {{ $guarantee['title'] }}</li>
                    @endforeach
                </ul>
                <div class="border-t border-veil/10 pt-5">
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
        :text="$formation->needsPaymentVerification()
            ? 'Payez par Mobile Money : la formation et son groupe privé s\'ouvrent dès que votre paiement a été vérifié.'
            : 'Payez par Mobile Money et accédez aussitôt à la formation et à son groupe privé.'"
    >
        <a href="{{ $buyUrl }}" class="btn-gold">Acheter maintenant · {{ $formation->formatted_price }}</a>
        <a href="{{ route('consultation') }}" class="btn-ghost">Réserver une consultation gratuite</a>
    </x-site.cta-banner>

    {{-- Autres formations --}}
    @if ($related->isNotEmpty())
        <section class="section !pt-0">
            <x-site.section-heading title="Vous aimerez aussi" align="left" />
            {{-- Téléphone : rangée qui défile sur le côté ; dès sm : grille. --}}
            <div class="-mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-px-4 px-4 pb-3 [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-5 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-3" data-test="related-formations">
                @foreach ($related as $item)
                    <div class="flex w-[84%] shrink-0 snap-start sm:w-auto [&>*]:w-full">
                        <x-site.formation-card :formation="$item" :delay="$loop->index * 70" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Barre d'achat fixée en bas de l'écran (mobile et tablette) --}}
    <x-slot:after>
        <div data-bottom-bar data-install-avoid class="fixed inset-x-0 bottom-0 z-40 border-t border-veil/10 bg-night-950/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl lg:hidden">
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
