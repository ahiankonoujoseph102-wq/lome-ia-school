@php
    use App\Support\Placeholder;

    $trainer = config('site.trainer');
    $about = config('site.about');
@endphp

<x-layouts::site
    title="À propos de Lomé IA School et de Joseph Ahiankonou"
    description="Lomé IA School, fondée par Joseph Ahiankonou, rend l'intelligence artificielle accessible à tous avec des formations pratiques en français, à Lomé et en ligne."
>
    @include('site.partials.page-header', [
        'eyebrow' => 'À propos',
        'title' => 'Rendre l\'intelligence artificielle accessible à tous',
        'subtitle' => 'Lomé IA School forme, depuis Lomé, celles et ceux qui veulent maîtriser l\'IA et l\'appliquer concrètement dans leur travail et leurs projets.',
    ])

    {{-- Formateur --}}
    <section class="section !pt-4">
        <div class="grid items-start gap-10 lg:grid-cols-[320px_1fr]">
            <div class="card p-6 text-center lg:sticky lg:top-24" data-reveal>
                <div class="flex justify-center">
                    @include('site.partials.trainer-photo', ['size' => 'size-48', 'loading' => 'eager'])
                </div>
                <p class="mt-5 text-xl font-bold text-white"><a href="{{ route('founder') }}" class="hover:text-gold-200">{{ $trainer['name'] }}</a></p>
                <p class="mt-1 text-sm text-electric-300">{{ $trainer['role'] }}</p>
                <ul class="mt-6 space-y-2 text-left text-sm">
                    @foreach ($trainer['highlights'] as $highlight)
                        <li class="flex items-start gap-2 text-slate-300">
                            <flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-gold-400" /> <span>{{ Placeholder::highlight($highlight) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="space-y-6" data-reveal>
                <span class="eyebrow">Le formateur</span>
                <h2 class="text-3xl font-bold text-white">{{ $trainer['name'] }}</h2>
                <p class="text-lg text-slate-200">{{ Placeholder::highlight($trainer['short_bio']) }}</p>
                <div class="prose-site">
                    @foreach ($trainer['bio'] as $paragraph)
                        <p>{{ Placeholder::highlight($paragraph) }}</p>
                    @endforeach
                </div>
                @if (\App\Support\Talkami::enabled())
                    {{-- Création du fondateur (novembre 2026) --}}
                    <div class="flex gap-4 rounded-2xl bg-night-900 p-5 shadow-[inset_0_0_0_1px_var(--color-night-600)]" data-test="about-talkami">
                        <img src="{{ asset(\App\Support\Talkami::LOGO) }}" alt="Logo de Talkami" width="480" height="337" class="h-11 w-auto shrink-0 rounded-lg" loading="lazy" decoding="async" data-test="about-talkami-logo">
                        <div>
                            <p class="font-semibold text-white">Une autre de ses créations : Talkami</p>
                            <p class="mt-1 text-sm leading-relaxed text-slate-300">{{ \App\Support\Talkami::CREATOR }} On y parle anglais à l'oral avec un professeur virtuel, avec une évaluation gratuite de 5 minutes.</p>
                            <a href="{{ route('talkami') }}" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-electric-300 hover:text-white">Découvrir Talkami <flux:icon.arrow-right class="size-4" /></a>
                        </div>
                    </div>
                @endif
                <a href="{{ route('founder') }}" class="inline-flex items-center gap-2 font-semibold text-gold-300 hover:text-gold-200" data-test="about-founder-link">
                    Son parcours, ses livres et ses services <flux:icon.arrow-right class="size-4" />
                </a>
                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    <a href="{{ route('formations.index') }}" class="btn-gold">Découvrir ses formations</a>
                    <a href="{{ route('consultation') }}" class="btn-ghost">Échanger gratuitement avec lui</a>
                </div>
            </div>
        </div>
    </section>

    {{-- L'école --}}
    <section class="border-y border-veil/10 bg-veil/[0.02]">
        <div class="section">
            <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                <div data-reveal>
                    @if ($verticalLogo = \App\Support\Brand::url(config('site.brand.logo_vertical')))
                        <img src="{{ $verticalLogo }}" alt="Logo de Lomé IA School" class="mb-6 size-40 rounded-3xl shadow-xl shadow-black/40 ring-1 ring-veil/15 sm:size-48" width="192" height="192" loading="lazy" data-test="about-logo">
                    @endif
                    <span class="eyebrow">Lomé IA School</span>
                    <h2 class="mt-4 text-3xl font-bold text-white sm:text-4xl">Notre mission</h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-300">{{ $about['mission'] }}</p>
                </div>
                <dl class="grid grid-cols-2 gap-4" data-reveal>
                    <div class="card flex flex-col-reverse p-5">
                        <dt class="mt-1 text-sm text-slate-400">formations pratiques</dt>
                        <dd class="text-3xl font-bold text-white">{{ $formationCount }}</dd>
                    </div>
                    @foreach (config('site.stats') as $stat)
                        <div class="card flex flex-col-reverse p-5">
                            <dt class="mt-1 text-sm text-slate-400">{{ $stat['label'] }}</dt>
                            <dd class="text-3xl font-bold text-white">{{ Placeholder::highlight($stat['value']) }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="mt-14 grid gap-4 md:grid-cols-3">
                @foreach ($about['values'] as $value)
                    <div class="card p-6" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                        <span class="text-sm font-bold text-gold-300">0{{ $loop->iteration }}</span>
                        <h3 class="mt-3 text-lg font-semibold text-white">{{ $value['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $value['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <x-site.cta-banner />
</x-layouts::site>
