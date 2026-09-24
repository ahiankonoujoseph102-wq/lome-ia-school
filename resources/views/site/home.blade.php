@php
    use App\Enums\FormationCategory;
    use App\Enums\FormationFormat;
    use App\Support\Placeholder;
    use App\Support\Testimonials;

    $testimonials = Testimonials::all();
    $trainer = config('site.trainer');
    $lifetime = config('site.lifetime');

    $organization = [
        '@context' => 'https://schema.org',
        '@type' => 'EducationalOrganization',
        'name' => config('app.name'),
        'url' => route('home'),
        'email' => config('site.contact.email'),
        'telephone' => config('site.contact.phones.0'),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Adidogomé, Lycée moderne 2',
            'addressLocality' => 'Lomé',
            'addressCountry' => 'TG',
        ],
    ];
@endphp

<x-layouts::site :json-ld="[$organization]">
    {{-- Accroche --}}
    <section class="relative isolate overflow-hidden">
        <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>

        <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-10 pb-16 sm:px-6 sm:pt-16 lg:grid-cols-[1.15fr_0.85fr] lg:pt-20 lg:pb-24">
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
            </div>

            {{-- Aperçu des formations (écrans larges) --}}
            @if ($featured->isNotEmpty())
                <div class="relative hidden lg:block" data-reveal style="--reveal-delay: 200ms">
                    <div class="absolute -inset-6 -z-10 rounded-[2.5rem] bg-electric-600/20 blur-3xl"></div>
                    <div class="card animate-float space-y-3 p-5 shadow-2xl shadow-electric-900/50">
                        <p class="px-1 text-sm font-semibold text-slate-400">Nos formations phares</p>
                        @foreach ($featured->take(3) as $formation)
                            <a href="{{ route('formations.show', $formation) }}" class="flex items-center gap-4 rounded-2xl bg-white/[0.04] p-3 ring-1 ring-white/10 transition hover:bg-white/[0.08]">
                                <x-site.formation-cover :formation="$formation" class="size-16 shrink-0 rounded-xl" compact eager />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-semibold text-white">{{ $formation->title }}</span>
                                    <span class="block text-sm text-slate-400">{{ $formation->category->label() }}</span>
                                </span>
                                <span class="font-bold whitespace-nowrap text-gold-300">{{ $formation->formatted_price }}</span>
                            </a>
                        @endforeach
                        <div class="flex items-center justify-between rounded-2xl bg-linear-to-r from-gold-400/15 to-transparent px-4 py-3 text-sm">
                            <span class="inline-flex items-center gap-2 text-gold-200"><flux:icon.lock-open class="size-4" /> Accès à vie et accompagnement à vie</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- Chiffres clés --}}
    <section class="border-y border-white/10 bg-white/[0.02]">
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

    {{-- Accès à vie et accompagnement à vie --}}
    <section class="section !pb-0">
        <div class="relative overflow-hidden rounded-[2rem] border border-gold-400/25 bg-linear-to-br from-gold-400/[0.12] via-night-900 to-night-950 p-6 sm:p-10" data-reveal>
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
                    <ul class="grid gap-4 sm:grid-cols-2">
                        @foreach ($lifetime['items'] as $item)
                            <li class="rounded-2xl bg-white/[0.05] p-5 ring-1 ring-gold-400/25">
                                <span class="grid size-12 place-items-center rounded-2xl bg-gold-400 text-night-950 shadow-lg shadow-gold-500/20">
                                    <flux:icon :icon="$item['icon']" class="size-6" />
                                </span>
                                <h3 class="mt-4 text-xl font-bold text-gold-200">{{ $item['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-300">{{ $item['text'] }}</p>
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

        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (config('site.why_us') as $item)
                <div class="card card-hover p-6" data-reveal style="--reveal-delay: {{ $loop->index * 70 }}ms">
                    <span class="grid size-12 place-items-center rounded-2xl bg-electric-500/15 text-electric-300 ring-1 ring-electric-400/25">
                        <flux:icon :icon="$item['icon']" class="size-6" />
                    </span>
                    <h3 class="mt-5 text-lg font-semibold text-white">{{ $item['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $item['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Formations mises en avant --}}
    @if ($featured->isNotEmpty())
        <section class="relative">
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

                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($featured as $formation)
                        <x-site.formation-card :formation="$formation" :delay="$loop->index * 70" />
                    @endforeach
                </div>

                <div class="mt-10" data-reveal>
                    <p class="text-center text-sm font-semibold text-slate-400">Explorer par thème</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        @foreach (FormationCategory::cases() as $category)
                            <a href="{{ route('formations.index', ['categorie' => $category->value]) }}" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white">
                                <flux:icon :icon="$category->icon()" class="size-4 text-electric-300" /> {{ $category->label() }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Les 3 formats --}}
    <section class="section">
        <x-site.section-heading
            eyebrow="3 façons d'apprendre"
            title="En ligne, en direct ou en présentiel à Lomé"
            subtitle="Choisissez le format qui correspond à votre emploi du temps et à votre façon d'apprendre."
        />

        <div class="mt-12 grid gap-4 md:grid-cols-3">
            @foreach (FormationFormat::cases() as $format)
                <div class="card card-hover relative overflow-hidden p-6 sm:p-8" data-reveal style="--reveal-delay: {{ $loop->index * 90 }}ms">
                    <div class="absolute -top-10 -right-10 size-32 rounded-full bg-electric-500/10 blur-2xl"></div>
                    <span class="grid size-14 place-items-center rounded-2xl bg-linear-to-br from-electric-500 to-electric-700 text-white shadow-lg shadow-electric-700/40">
                        <flux:icon :icon="$format->icon()" class="size-7" />
                    </span>
                    <h3 class="mt-6 text-xl font-semibold text-white">{{ $format->label() }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-400">{{ $format->description() }}</p>
                    <a href="{{ route('formations.index', ['format' => $format->value]) }}" class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-electric-300 hover:text-white">
                        Voir les formations <flux:icon.arrow-right class="size-4" />
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Comment ça marche --}}
    <section class="border-y border-white/10 bg-white/[0.02]">
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
                            <li class="flex items-start gap-2 rounded-2xl bg-white/[0.04] p-3 text-sm text-slate-200 ring-1 ring-white/10">
                                <flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-gold-400" /> <span>{{ Placeholder::highlight($highlight) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('about') }}" class="btn-ghost mt-6">
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

    {{-- Affiliation --}}
    <section class="section !pt-0">
        <div class="relative overflow-hidden rounded-[2rem] border border-gold-400/20 bg-linear-to-br from-night-800 via-night-900 to-night-950 p-6 sm:p-10 lg:p-14" data-reveal>
            <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-gold-400/15 blur-3xl"></div>
            <div class="relative grid items-center gap-10 lg:grid-cols-2">
                <div>
                    <span class="eyebrow !border-gold-400/30 !bg-gold-400/10 !text-gold-200">Programme d'affiliation</span>
                    <h2 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl">{{ config('site.affiliate.title') }}</h2>
                    <p class="mt-4 text-slate-300">{{ config('site.affiliate.text') }}</p>
                    <a href="{{ route('register') }}" class="btn-gold mt-8">Créer mon compte et obtenir mon lien</a>
                </div>
                <ul class="grid gap-3">
                    @foreach (config('site.affiliate.points') as $point)
                        <li class="flex items-center gap-4 rounded-2xl bg-white/[0.04] p-4 ring-1 ring-white/10">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gold-400/15 text-gold-300">
                                <flux:icon.check class="size-5" />
                            </span>
                            <span class="text-slate-200">{{ $point }}</span>
                        </li>
                    @endforeach
                    <li class="flex items-center justify-between rounded-2xl bg-gold-400/10 p-4 ring-1 ring-gold-400/25">
                        <span class="text-sm text-gold-100">Exemple : une formation à 36&nbsp;000&nbsp;FCFA vendue grâce à votre lien</span>
                        <span class="text-lg font-bold whitespace-nowrap text-gold-300">+ 3&nbsp;600&nbsp;FCFA</span>
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
