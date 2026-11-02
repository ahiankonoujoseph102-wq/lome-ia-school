@php
    use App\Enums\FormationCategory;
    use App\Support\Placeholder;
    use App\Support\Testimonials;

    $testimonials = Testimonials::all();
    $trainer = config('site.trainer');
    $lifetime = config('site.lifetime');
    $formats = config('site.formats');

    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'EducationalOrganization',
        '@id' => \App\Support\Founder::organizationId(),
        'name' => config('app.name'),
        'url' => \App\Support\AppUrl::to(route('home')),
        'email' => config('site.contact.email'),
        'telephone' => config('site.contact.phones.0'),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => config('site.contact.address'),
            'addressLocality' => 'Lomé',
            'addressCountry' => 'TG',
        ],
        // Fondateur : fiche Person complète sur la page de Joseph Ahiankonou (même @id).
        'founder' => \App\Support\Founder::personReference(),
    ];
@endphp

<x-layouts::site :json-ld="[$organization]">
    {{-- Formation proposée en direct : bandeau qui disparaît après la dernière séance --}}
    @include('site.partials.live-offer-banner')

    {{-- Accroche --}}
    <section class="relative isolate overflow-hidden">
        {{-- Fond animé (interrupteur « Fond animé » de l'admin) ; éteint : fond fixe d'origine. --}}
        @if (\App\Support\AnimatedBackground::shownOn('home'))
            @include('site.partials.animated-background')
        @else
            <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
            <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
        @endif

        <div class="mx-auto grid max-w-6xl grid-cols-[minmax(0,1fr)] items-center gap-12 px-4 pt-10 pb-16 sm:px-6 sm:pt-16 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] lg:pt-20 lg:pb-24">
            <div class="text-center lg:text-left">
                <span class="eyebrow" data-reveal>
                    <span class="size-1.5 rounded-full bg-gold-400"></span>
                    {{ config('site.hero.badge') }}
                </span>

                <h1 class="text-gradient mt-5 text-4xl leading-[1.08] font-bold tracking-tight text-balance sm:text-5xl lg:text-6xl" data-reveal style="--reveal-delay: 80ms">
                    {{ config('site.hero.title') }}
                </h1>

                <p class="mx-auto mt-5 max-w-xl text-base text-pretty text-slate-300 sm:text-lg lg:mx-0" data-reveal style="--reveal-delay: 160ms">
                    {{ config('site.hero.subtitle') }}
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center lg:justify-start" data-reveal style="--reveal-delay: 240ms">
                    <a href="{{ route('formations.index') }}" class="btn-gold sm:whitespace-nowrap">
                        Voir les formations <flux:icon.arrow-right class="size-5" />
                    </a>
                    <a href="{{ route('consultation') }}" class="btn-ghost sm:whitespace-nowrap">
                        <flux:icon.calendar-days class="size-5" /> Réserver une consultation gratuite
                    </a>
                </div>

                <ul class="mt-8 flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm text-slate-400 lg:justify-start" data-reveal style="--reveal-delay: 320ms">
                    @foreach (config('site.hero.points') as $point)
                        <li class="inline-flex items-center gap-1.5"><flux:icon.check-circle class="size-4 text-gold-400" /> {{ $point }}</li>
                    @endforeach
                </ul>

                {{-- Repère de confiance : Coach Joseph et le premier chiffre clé officiel (Réglages du site > Coordonnées). --}}
                @php
                    $heroStat = collect((array) config('site.stats'))->first(fn ($stat) => is_array($stat) && filled($stat['value'] ?? null) && ! str_starts_with((string) $stat['value'], '['));
                @endphp
                <a href="{{ route('founder') }}" class="mx-auto mt-7 flex w-fit items-center gap-3 rounded-full bg-night-900/80 py-1.5 pr-5 pl-1.5 text-left shadow-[inset_0_0_0_1px_var(--color-night-600)] transition hover:bg-night-800 lg:mx-0" data-test="hero-trainer" data-reveal style="--reveal-delay: 360ms">
                    @include('site.partials.trainer-photo', ['size' => 'size-11 !rounded-full', 'loading' => 'eager'])
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-white">Avec Coach Joseph, votre formateur</span>
                        @if ($heroStat)
                            <span class="block text-xs text-slate-300">{{ $heroStat['value'] }} {{ $heroStat['label'] }}</span>
                        @endif
                    </span>
                </a>
            </div>

            {{-- Téléphone et tablette : aperçu des formations phares qui défile sur le côté (l'écran large a sa carte à droite). --}}
            @if ($featured->isNotEmpty())
                <div class="-mx-4 -mt-4 lg:hidden" data-test="hero-featured" data-reveal style="--reveal-delay: 400ms">
                    <p class="px-4 text-center text-xs font-semibold tracking-wide text-slate-400 uppercase">Nos formations phares</p>
                    <ul class="mt-3 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-px-4 px-4 pb-2 [scrollbar-width:none] sm:justify-center">
                        @foreach ($featured->take(5) as $formation)
                            <li class="w-[15.5rem] shrink-0 snap-start">
                                <a href="{{ route('formations.show', $formation) }}" class="flex items-center gap-3 rounded-2xl bg-night-900/90 p-2.5 shadow-[inset_0_0_0_1px_var(--color-night-600)]">
                                    <x-site.formation-cover :formation="$formation" class="size-14 shrink-0 rounded-xl" compact />
                                    <span class="min-w-0 flex-1">
                                        <span class="line-clamp-2 text-sm leading-snug font-semibold text-white">{{ $formation->title }}</span>
                                        <span class="mt-0.5 block text-sm font-bold text-gold-300">{{ $formation->formatted_price }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Aperçu des formations (écrans larges) --}}
            @if ($featured->isNotEmpty())
                <div class="relative hidden lg:block" data-reveal style="--reveal-delay: 200ms">
                    <div class="absolute -inset-6 -z-10 rounded-[2.5rem] bg-electric-600/20 blur-3xl"></div>
                    <div class="card animate-float space-y-3 p-5 shadow-2xl shadow-electric-900/50">
                        <p class="px-1 text-sm font-semibold text-slate-400">Nos formations phares</p>
                        @foreach ($featured->take(3) as $formation)
                            <a href="{{ route('formations.show', $formation) }}" class="flex items-center gap-4 rounded-2xl bg-veil/[0.04] p-3 ring-1 ring-veil/10 transition hover:bg-veil/[0.08]">
                                <x-site.formation-cover :formation="$formation" class="size-16 shrink-0 rounded-xl" compact eager />
                                <span class="min-w-0 flex-1">
                                    <span class="line-clamp-2 leading-snug font-semibold text-white">{{ $formation->title }}</span>
                                    <span class="block text-sm text-slate-400">{{ $formation->category->label() }}</span>
                                </span>
                                <span class="font-bold whitespace-nowrap text-gold-300">{{ $formation->formatted_price }}</span>
                            </a>
                        @endforeach
                        <div class="flex items-center justify-between rounded-2xl bg-linear-to-r from-gold-400/15 to-transparent px-4 py-3 text-sm">
                            <span class="inline-flex items-center gap-2 text-gold-200"><flux:icon.lock-open class="size-4" /> Accès à vie et accompagnement de 3 mois</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- Vidéo d'accueil (verticale, chargée au toucher) : absente sans lien YouTube valide --}}
    @include('site.partials.home-video')

    {{-- Chiffres clés --}}
    <section class="border-y border-veil/10 bg-veil/[0.02]">
        <dl class="mx-auto grid max-w-6xl grid-cols-2 gap-px px-4 sm:px-6 lg:grid-cols-4">
            <div class="flex flex-col-reverse px-2 py-8 text-center" data-reveal>
                <dt class="mt-1 text-sm text-slate-400">formations pratiques</dt>
                <dd class="text-3xl font-bold text-white sm:text-4xl">{{ $formationCount }}</dd>
            </div>
            @foreach (config('site.stats') as $stat)
                <div class="flex flex-col-reverse px-2 py-8 text-center" data-reveal style="--reveal-delay: {{ ($loop->index + 1) * 80 }}ms">
                    <dt class="mt-1 text-sm text-slate-400">{{ $stat['label'] }}</dt>
                    <dd @class(['font-bold text-white', 'text-3xl sm:text-4xl' => ! str_starts_with($stat['value'], '['), 'text-base' => str_starts_with($stat['value'], '[')])>{{ Placeholder::highlight($stat['value']) }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Accès à vie et accompagnement de 3 mois après la formation --}}
    <section class="section !pb-0">
        <div class="relative overflow-hidden rounded-[2rem] border border-gold-400/25 bg-linear-to-br from-gold-400/[0.12] via-night-900 to-night-950 p-4 sm:p-10" data-reveal>
            <div class="pointer-events-none absolute -top-20 -left-16 size-64 rounded-full bg-gold-400/15 blur-3xl"></div>
            <div class="relative grid items-center gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:gap-12">
                <div class="text-center lg:text-left">
                    <span class="eyebrow !border-gold-400/30 !bg-gold-400/10 !text-gold-200">
                        <flux:icon.sparkles class="size-3.5" /> Inclus dans chaque formation
                    </span>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">{{ $lifetime['title'] }}</h2>
                    <p class="mt-4 text-slate-300">{{ $lifetime['text'] }}</p>
                </div>
                <div>
                    <ul class="grid grid-cols-2 gap-3 sm:gap-4">
                        @foreach ($lifetime['items'] as $item)
                            <li class="rounded-2xl bg-veil/[0.05] p-3 ring-1 ring-gold-400/25 sm:p-5">
                                <span class="grid size-10 place-items-center rounded-xl bg-gold-400 text-night-950 shadow-lg shadow-gold-500/20 sm:size-12 sm:rounded-2xl">
                                    <flux:icon :icon="$item['icon']" class="size-5 sm:size-6" />
                                </span>
                                <h3 class="mt-3 text-sm leading-snug font-bold break-words hyphens-auto text-gold-200 sm:mt-4 sm:text-xl">{{ $item['title'] }}</h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-slate-300 sm:mt-2 sm:text-sm">{{ $item['text'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-4 text-center text-xs text-slate-500 lg:text-left">{{ $lifetime['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Pourquoi nous choisir --}}
    <section class="section">
        <x-site.section-heading
            eyebrow="Pourquoi nous choisir"
            title="Une école pensée pour vous faire progresser vite"
            subtitle="Des formations concrètes, un paiement simple et un accompagnement réel, pour passer de la curiosité aux résultats."
        />

        <div class="mt-8 grid grid-cols-2 gap-3 sm:mt-12 sm:gap-4 lg:grid-cols-3" data-test="home-why-us">
            @foreach (config('site.why_us') as $item)
                <div class="card card-hover p-4 sm:p-6" data-reveal style="--reveal-delay: {{ $loop->index * 70 }}ms">
                    <span class="grid size-10 place-items-center rounded-xl bg-electric-500/15 text-electric-300 ring-1 ring-electric-400/25 sm:size-12 sm:rounded-2xl">
                        <flux:icon :icon="$item['icon']" class="size-5 sm:size-6" />
                    </span>
                    <h3 class="mt-3 text-sm leading-snug font-semibold break-words hyphens-auto text-white sm:mt-5 sm:text-lg">{{ $item['title'] }}</h3>
                    <p class="mt-1.5 text-xs leading-relaxed text-slate-400 sm:mt-2 sm:text-sm">{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Formations mises en avant --}}
    @if ($featured->isNotEmpty())
        <section id="catalogue" class="relative scroll-mt-16">
            <div class="glow pointer-events-none absolute inset-0 opacity-50"></div>
            <div class="section relative">
                <div class="flex flex-col items-center justify-between gap-6 text-center md:flex-row md:items-end md:text-left">
                    <x-site.section-heading
                        eyebrow="Formations phares"
                        title="Commencez par nos formations phares"
                        align="left"
                        class="md:max-w-xl"
                    />
                    <a href="{{ route('formations.index') }}" class="btn-ghost shrink-0" data-reveal>
                        Voir les {{ $formationCount }} formations <flux:icon.arrow-right class="size-5" />
                    </a>
                </div>

                {{-- Téléphone : rangée qui défile sur le côté (une carte et le bord de la suivante) ; dès sm : grille. --}}
                <div class="-mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-px-4 px-4 pb-3 [scrollbar-width:none] sm:mx-0 sm:mt-10 sm:grid sm:grid-cols-2 sm:gap-5 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-3" data-test="home-featured">
                    @foreach ($featured as $formation)
                        <div class="flex w-[84%] shrink-0 snap-start sm:w-auto [&>*]:w-full">
                            <x-site.formation-card :formation="$formation" :delay="$loop->index * 70" />
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 flex items-center justify-center gap-1.5 text-xs text-slate-400 sm:hidden" aria-hidden="true">
                    <flux:icon.arrows-right-left class="size-4" /> Faites glisser pour voir les autres formations
                </p>

                <div class="mt-10" data-reveal>
                    <p class="text-center text-sm font-semibold text-slate-400">Explorer par thème</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        @foreach (FormationCategory::cases() as $category)
                            <a href="{{ route('formations.index', ['categorie' => $category->value]) }}" class="inline-flex items-center gap-2 rounded-full bg-veil/5 px-4 py-2 text-sm text-slate-200 ring-1 ring-veil/10 transition hover:bg-veil/10 hover:text-white">
                                <flux:icon :icon="$category->icon()" class="size-4 text-electric-300" /> {{ $category->label() }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Talkami (création de Joseph) : grande section #talkami (site/partials/home-talkami.blade.php) --}}
    @if (\App\Support\Talkami::enabled())
        @include('site.partials.home-talkami')
    @endif

    {{-- Déroulement : l'enregistré est la règle, le direct et le présentiel sont ponctuels --}}
    <section class="section">
        <x-site.section-heading
            :eyebrow="$formats['eyebrow']"
            :title="$formats['title']"
        />

        <div class="mt-12 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
            <div class="card relative overflow-hidden p-6 sm:p-8" data-reveal>
                <div class="absolute -top-12 -right-12 size-40 rounded-full bg-electric-500/15 blur-2xl"></div>
                <div class="relative">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="grid size-14 place-items-center rounded-2xl bg-linear-to-br from-electric-500 to-electric-700 text-white shadow-lg shadow-electric-700/40">
                            <flux:icon.play-circle class="size-7" />
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gold-400/15 px-3 py-1.5 text-sm font-semibold text-gold-200 ring-1 ring-gold-400/40">
                            <flux:icon.bolt class="size-4" /> {{ $formats['recorded']['badge'] }}
                        </span>
                    </div>
                    <h3 class="mt-6 text-2xl font-bold text-white">{{ $formats['recorded']['title'] }}</h3>
                    <p class="mt-3 leading-relaxed text-slate-300">{{ $formats['recorded']['text'] }}</p>
                    <ul class="mt-5 grid gap-2 sm:grid-cols-3">
                        @foreach ($formats['recorded']['points'] as $point)
                            <li class="flex items-start gap-2 text-sm text-slate-200">
                                <flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-gold-400" /> {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('formations.index') }}" class="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-electric-300 hover:text-white">
                        Voir les formations <flux:icon.arrow-right class="size-4" />
                    </a>
                </div>
            </div>

            <div class="card p-6 sm:p-8" data-reveal style="--reveal-delay: 90ms">
                <div class="flex gap-2">
                    <span class="grid size-11 place-items-center rounded-2xl bg-veil/5 text-electric-300 ring-1 ring-veil/10"><flux:icon.video-camera class="size-5" /></span>
                    <span class="grid size-11 place-items-center rounded-2xl bg-veil/5 text-electric-300 ring-1 ring-veil/10"><flux:icon.map-pin class="size-5" /></span>
                </div>
                <h3 class="mt-6 text-xl font-semibold text-white">{{ $formats['sessions']['title'] }}</h3>
                <p class="mt-3 text-sm leading-relaxed text-slate-400">{{ $formats['sessions']['text'] }}</p>
            </div>
        </div>
    </section>

    {{-- Prochaines sessions en direct ou à Lomé : affiché seulement s'il y en a (jamais de lien Google Meet ici) --}}
    @if ($upcomingSessions->isNotEmpty())
        <section class="section !pt-0" data-test="home-sessions">
            <x-site.section-heading eyebrow="Agenda" title="Prochaines sessions" subtitle="Sessions ponctuelles réservées aux élèves de la formation concernée. Heures de Lomé (GMT)." />
            <div class="mt-10 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($upcomingSessions as $session)
                    <a href="{{ route('formations.show', $session->formation) }}#prochaine-session" class="card card-hover flex flex-col p-5" data-reveal>
                        <x-site.session-summary :session="$session" show-formation />
                        <span class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold text-gold-300">Voir la formation <flux:icon.arrow-right class="size-4" /></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Comment ça marche --}}
    <section class="border-y border-veil/10 bg-veil/[0.02]">
        <div class="section">
            <x-site.section-heading
                eyebrow="Comment ça marche"
                title="Votre formation en 3 étapes simples"
            />

            <ol class="relative mt-12 grid gap-6 md:grid-cols-3">
                <div class="absolute top-7 right-[16%] left-[16%] hidden h-px bg-linear-to-r from-electric-500/0 via-electric-400/50 to-electric-500/0 md:block" aria-hidden="true"></div>
                @foreach (config('site.steps') as $step)
                    <li class="relative text-center" data-reveal style="--reveal-delay: {{ $loop->index * 120 }}ms">
                        <span class="relative mx-auto grid size-14 place-items-center rounded-full bg-night-900 text-xl font-bold text-gold-300 ring-2 ring-gold-400/60">
                            {{ $loop->iteration }}
                        </span>
                        <h3 class="mt-5 text-lg font-semibold text-white">{{ $step['title'] }}</h3>
                        <p class="mx-auto mt-2 max-w-xs text-sm text-slate-400">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>

            <div class="mt-12 text-center" data-reveal>
                <a href="{{ route('formations.index') }}" class="btn-gold">Choisir ma formation <flux:icon.arrow-right class="size-5" /></a>
            </div>
        </div>
    </section>

    {{-- Formateur --}}
    <section class="section">
        <div class="card relative overflow-hidden p-6 sm:p-10" data-reveal>
            <div class="pointer-events-none absolute -right-24 -bottom-24 size-72 rounded-full bg-electric-600/20 blur-3xl"></div>
            <div class="relative grid items-center gap-8 md:grid-cols-[240px_1fr] lg:gap-12">
                <div class="mx-auto w-full max-w-60">
                    @include('site.partials.trainer-photo', ['size' => 'aspect-square w-full !rounded-3xl'])
                </div>
                <div class="text-center md:text-left">
                    <span class="eyebrow">Votre formateur</span>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $trainer['name'] }}</h2>
                    <p class="mt-1 text-sm font-medium text-electric-300">{{ $trainer['role'] }}</p>
                    <p class="mt-4 text-slate-300 sm:text-lg">{{ Placeholder::highlight($trainer['short_bio']) }}</p>
                    <ul class="mt-6 grid gap-3 text-left sm:grid-cols-3">
                        @foreach ($trainer['highlights'] as $highlight)
                            <li class="flex items-start gap-2 rounded-2xl bg-veil/[0.04] p-3 text-sm text-slate-200 ring-1 ring-veil/10">
                                <flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-gold-400" /> <span>{{ Placeholder::highlight($highlight) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('founder') }}" class="btn-ghost mt-6" data-test="home-founder-link">
                        Découvrir son parcours <flux:icon.arrow-right class="size-5" />
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Témoignages (masqués tant qu'il n'y en a aucun) --}}
    @if ($testimonials)
        <section class="section !pt-0">
            <x-site.section-heading
                eyebrow="Témoignages"
                title="Ils se sont formés avec nous"
            />
            <x-site.testimonials :items="$testimonials" class="mt-12" />
        </section>
    @endif

    {{-- Nous le faisons pour vous (réalisations sur mesure) et entreprises : page /entreprises, devis en #devis --}}
    @php
        $customWork = (array) config('site.custom_work');
        $workItems = array_values(array_filter((array) ($customWork['items'] ?? []), fn ($item): bool => is_array($item) && filled($item['title'] ?? null)));
        $workIcons = ['window', 'computer-desktop', 'cpu-chip', 'arrow-path', 'sparkles', 'cog-6-tooth'];
    @endphp
    <section class="section !pt-0" data-test="home-business">
        <div class="rounded-[2rem] bg-night-900 p-6 shadow-[inset_0_0_0_1px_var(--color-night-600)] sm:p-10" data-reveal>
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="eyebrow">Réalisations sur mesure</span>
                    <h2 class="mt-4 text-2xl font-bold tracking-tight text-balance text-white sm:text-4xl">{{ $customWork['title'] ?? 'Nous le faisons pour vous' }}</h2>
                    @if (filled($customWork['intro'] ?? null))
                        <p class="mt-3 text-pretty text-slate-300 sm:text-lg">{{ $customWork['intro'] }}</p>
                    @endif
                </div>
                <a href="{{ route('business') }}#devis" class="btn-gold w-full shrink-0 sm:w-auto" data-test="home-quote">Demander un devis <flux:icon.arrow-right class="size-5" /></a>
            </div>
            @if ($workItems)
                <ul class="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ($workItems as $item)
                        <li class="rounded-2xl bg-night-800 p-3.5 shadow-[inset_0_0_0_1px_var(--color-night-600)] sm:p-4">
                            <flux:icon :icon="$workIcons[$loop->index % count($workIcons)]" class="size-6 text-gold-300" />
                            <p class="mt-3 text-sm font-semibold break-words hyphens-auto text-white sm:text-base">{{ $item['title'] }}</p>
                            <p class="mt-1 text-xs leading-relaxed break-words hyphens-auto text-slate-400 sm:text-sm">{{ $item['text'] ?? '' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-8 flex flex-col gap-4 pt-6 shadow-[inset_0_1px_0_var(--color-night-700)] sm:flex-row sm:items-center">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30"><flux:icon.building-office-2 class="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-white">{{ config('site.business.home_title') }}</p>
                    <p class="mt-1 text-sm text-slate-300">{{ config('site.business.home_text') }}</p>
                </div>
                <a href="{{ route('business') }}" class="btn-ghost w-full shrink-0 sm:w-auto">Voir la page Entreprises <flux:icon.arrow-right class="size-5" /></a>
            </div>
        </div>
    </section>

    {{-- Affiliation --}}
    <section class="section !pt-0">
        <div class="relative overflow-hidden rounded-[2rem] border border-gold-400/20 bg-linear-to-br from-night-800 via-night-900 to-night-950 p-6 sm:p-10 lg:p-14" data-reveal>
            <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-gold-400/15 blur-3xl"></div>
            <div class="relative grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <span class="eyebrow !border-gold-400/30 !bg-gold-400/10 !text-gold-200">Programme d'affiliation</span>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">{{ config('site.affiliate.title') }}</h2>
                    <p class="mt-4 text-slate-300">{{ config('site.affiliate.text') }}</p>
                    <a href="{{ route('affiliate.program') }}" class="btn-gold mt-8">Découvrir le programme d'affiliation</a>
                </div>
                <ul class="grid gap-3">
                    @foreach (config('site.affiliate.points') as $point)
                        <li class="flex items-center gap-4 rounded-2xl bg-veil/[0.04] p-4 ring-1 ring-veil/10">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gold-400/15 text-gold-300">
                                <flux:icon.check class="size-5" />
                            </span>
                            <span class="text-slate-200">{{ $point }}</span>
                        </li>
                    @endforeach
                    <li class="flex items-center justify-between rounded-2xl bg-gold-400/10 p-4 ring-1 ring-gold-400/25">
                        <span class="text-sm text-gold-100">Exemple de calcul : une formation à 36&nbsp;000&nbsp;FCFA achetée grâce à votre lien</span>
                        <span class="text-lg font-bold whitespace-nowrap text-gold-300">{{ \App\Support\Money::fcfa(intdiv(36000 * (int) config('affiliate.rate'), 100)) }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="section !pt-0">
        <x-site.section-heading eyebrow="Questions fréquentes" title="Vous avez des questions ?" />
        <x-site.faq :items="config('site.faq.home')" class="mx-auto mt-10 max-w-3xl" />
    </section>

    <x-site.cta-banner />
</x-layouts::site>
