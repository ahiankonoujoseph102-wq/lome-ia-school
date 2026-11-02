{{--
    Page Talkami (/talkami, novembre 2026) : plateforme créée par Joseph Ahiankonou pour apprendre à parler anglais.
    Faits fournis par Joseph uniquement (App\Support\Talkami) ; liens externes vers talkami.net (nouvel onglet).
    Aucun chiffre d'élèves, avis, note ni promesse de résultat.
--}}
@php
    use App\Support\Money;
    use App\Support\Talkami;

    $url = Talkami::url();
    $plans = Talkami::plans();
    $whatsappUrl = Talkami::whatsappUrl();
    $steps = [
        ['title' => 'Évaluez votre anglais', 'text' => 'Une évaluation orale gratuite de 5 minutes donne votre niveau, de A1 à C2.'],
        ['title' => 'Essayez une séance', 'text' => 'Une séance d\'essai gratuite de 15 minutes avec Teacher Joe ou Teacher Rose.'],
        ['title' => 'Suivez votre programme', 'text' => 'Une séance orale chaque jour selon votre objectif, avec corrections et révision.'],
    ];
@endphp

<x-layouts::site
    title="Talkami : apprendre à parler anglais"
    description="Talkami, créé par Joseph Ahiankonou : parlez anglais à l'oral avec un professeur virtuel. Évaluation orale gratuite de 5 minutes, de A1 à C2, sur votre téléphone."
>
    <section class="relative isolate overflow-hidden" data-test="talkami-page">
        <div class="glow pointer-events-none absolute inset-0 -z-10 opacity-70"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
        <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 pt-10 pb-14 sm:px-6 sm:pt-16 lg:grid-cols-[1.1fr_1fr] lg:pb-20">
            <div>
                <img src="{{ asset(\App\Support\Talkami::LOGO) }}" alt="Logo de Talkami" width="480" height="337" class="mb-6 h-20 w-auto rounded-2xl sm:h-24" fetchpriority="high" decoding="async" data-test="talkami-logo">
                <span class="eyebrow" data-reveal>Une création de Joseph Ahiankonou</span>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">Parlez anglais avec Teacher Joe, chaque jour</h1>
                <p class="mt-4 max-w-xl text-base text-pretty text-slate-300 sm:text-lg" data-reveal style="--reveal-delay: 120ms">Vous parlez anglais à l'oral avec un professeur virtuel, Teacher Joe ou Teacher Rose, au choix. Tout se fait sur votre téléphone.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row" data-reveal style="--reveal-delay: 180ms">
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="btn-gold" data-test="talkami-start">Passer mon évaluation gratuite <flux:icon.arrow-up-right class="size-5" /></a>
                    <a href="#formules" class="btn-ghost">Voir les formules</a>
                </div>
                <p class="mt-4 flex items-center gap-2 text-sm text-slate-400" data-reveal style="--reveal-delay: 220ms">
                    <flux:icon.check-circle class="size-4 shrink-0 text-emerald-400" /> Évaluation orale gratuite de 5 minutes, une par compte.
                </p>
            </div>
            <div data-reveal style="--reveal-delay: 120ms">
                @include('site.partials.talkami-phone')
            </div>
        </div>
    </section>

    {{-- Comment ça marche --}}
    <section class="section !pt-4">
        <x-site.section-heading eyebrow="Comment ça marche" title="Trois étapes pour commencer" />
        <ol class="mx-auto mt-10 grid max-w-5xl gap-4 sm:grid-cols-3" data-test="talkami-steps">
            @foreach ($steps as $step)
                <li class="card p-6" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                    <span class="grid size-10 place-items-center rounded-full bg-night-800 text-base font-bold text-gold-300 shadow-[0_0_0_1.5px_var(--color-gold-500)]">{{ $loop->iteration }}</span>
                    <h3 class="mt-4 text-lg font-semibold text-white">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-300">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Ce que propose Talkami --}}
    <section class="section !pt-0">
        <x-site.section-heading eyebrow="Ce que propose Talkami" title="Un professeur d'anglais dans votre poche" />
        <ul class="mx-auto mt-10 grid max-w-5xl gap-4 sm:grid-cols-2 lg:grid-cols-3" data-test="talkami-features">
            @foreach (Talkami::FEATURES as $feature)
                <li class="card flex gap-4 p-5" data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30">
                        <flux:icon :icon="$feature['icon']" class="size-5" />
                    </span>
                    <span>
                        <span class="block font-semibold text-white">{{ $feature['title'] }}</span>
                        <span class="mt-1 block text-sm leading-relaxed text-slate-300">{{ $feature['text'] }}</span>
                    </span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Formules --}}
    @if ($plans)
        <section id="formules" class="section !pt-0 scroll-mt-20">
            <x-site.section-heading eyebrow="Formules" title="Choisissez votre rythme" subtitle="Paiement par Mobile Money. Rien n'est prélevé automatiquement." />
            <ul class="mx-auto mt-10 grid max-w-5xl gap-4 sm:grid-cols-3" data-test="talkami-plans">
                @foreach ($plans as $plan)
                    <li class="card flex flex-col p-6 text-center" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                        <span class="text-sm font-semibold tracking-wide text-electric-300 uppercase">{{ $plan['name'] }}</span>
                        <span class="mt-3 text-3xl font-bold text-gold-300">{{ Money::fcfa($plan['price']) }}</span>
                        @if ($plan['period'] !== '')
                            <span class="mt-1 text-sm text-slate-400">{{ $plan['period'] }}</span>
                        @endif
                        @if ($plan['detail'] !== '')
                            <span class="mt-4 inline-flex items-center justify-center gap-2 text-slate-200"><flux:icon.clock class="size-4 text-gold-400" /> {{ $plan['detail'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Le créateur --}}
    <section class="section !pt-0">
        <div class="card mx-auto flex max-w-3xl flex-col items-center gap-6 p-6 text-center sm:flex-row sm:p-8 sm:text-left" data-reveal data-test="talkami-creator">
            @include('site.partials.trainer-photo', ['size' => 'size-24'])
            <div>
                <p class="text-lg leading-relaxed text-slate-100">{{ Talkami::CREATOR }}</p>
                <a href="{{ route('founder') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-electric-300 hover:text-white">Découvrir son parcours <flux:icon.arrow-right class="size-4" /></a>
            </div>
        </div>
    </section>

    <x-site.cta-banner title="Commencez par votre évaluation gratuite" text="5 minutes à l'oral pour connaître votre niveau d'anglais, de A1 à C2.">
        <a href="{{ $url }}" target="_blank" rel="noopener" class="btn-gold">Aller sur Talkami <flux:icon.arrow-up-right class="size-5" /></a>
        @if ($whatsappUrl)
            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn-ghost" data-test="talkami-whatsapp"><x-site.whatsapp-icon class="size-5 text-[#25D366]" /> WhatsApp de Talkami</a>
        @endif
    </x-site.cta-banner>
</x-layouts::site>
