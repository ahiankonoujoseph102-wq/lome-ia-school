@php
    use Illuminate\Support\Str;

    // Données légères pour les filtres côté navigateur (aucun rechargement de page).
    $items = $formations->mapWithKeys(fn ($formation) => [
        $formation->id => [
            'category' => $formation->category->value,
            'text' => Str::lower(Str::ascii($formation->title.' '.$formation->subtitle.' '.$formation->category->label())),
        ],
    ]);
@endphp

<x-layouts::site
    title="Toutes nos formations en intelligence artificielle"
    description="Découvrez nos {{ $formations->count() }} formations pratiques en IA : ChatGPT, affiches, vidéos, sites web, applications, automatisation, agents IA, business en ligne. Paiement Mobile Money."
>
    <section class="relative isolate overflow-hidden">
        @if (\App\Support\AnimatedBackground::shownOn('formations.index'))
            @include('site.partials.animated-background', ['short' => true])
        @else
            <div class="glow pointer-events-none absolute inset-0 -z-10 opacity-70"></div>
        @endif
        <div class="mx-auto max-w-6xl px-4 pt-10 pb-8 text-center sm:px-6 sm:pt-16">
            <span class="eyebrow" data-reveal>Catalogue</span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal>Nos formations en intelligence artificielle</h1>
            <p class="mx-auto mt-4 max-w-2xl text-slate-400 sm:text-lg" data-reveal>
                {{ $formations->count() }} formations pratiques pour apprendre, créer et développer vos projets avec l'IA. Paiement par Mobile Money.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-20 sm:px-6" x-data="catalogue(@js($items))">
        {{-- Filtres --}}
        <div class="sticky top-16 z-30 -mx-4 space-y-3 border-b border-veil/10 bg-night-950/90 px-4 py-4 backdrop-blur-xl sm:-mx-6 sm:px-6">
            <label class="relative block">
                <span class="sr-only">Rechercher une formation</span>
                <flux:icon.magnifying-glass class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-500" />
                <input
                    type="search"
                    x-model.debounce.200ms="search"
                    placeholder="Rechercher : vidéo, site web, agents…"
                    class="w-full rounded-full border border-veil/10 bg-veil/5 py-3 pr-4 pl-12 text-base text-white placeholder-slate-500 outline-none focus:border-electric-400/60 focus:ring-2 focus:ring-electric-500/30"
                    autocomplete="off"
                >
            </label>

            <div class="scrollbar-none -mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Filtrer par catégorie">
                <button type="button" @click="category = ''" :aria-pressed="(category === '').toString()"
                    :class="category === '' ? 'bg-electric-600 text-white ring-electric-500' : 'bg-veil/5 text-slate-300 ring-veil/10 hover:bg-veil/10'"
                    class="shrink-0 rounded-full px-4 py-2 text-sm font-medium ring-1 transition">
                    Toutes
                </button>
                @foreach ($categories as $category)
                    <button type="button" @click="category = @js($category->value)" :aria-pressed="(category === @js($category->value)).toString()"
                        :class="category === @js($category->value) ? 'bg-electric-600 text-white ring-electric-500' : 'bg-veil/5 text-slate-300 ring-veil/10 hover:bg-veil/10'"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium ring-1 transition">
                        <flux:icon :icon="$category->icon()" class="size-4" />
                        <span class="sm:hidden">{{ $category->shortLabel() }}</span>
                        <span class="hidden sm:inline">{{ $category->label() }}</span>
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="inline-flex items-center gap-2 text-sm text-slate-300">
                    <flux:icon.play-circle class="size-4 shrink-0 text-gold-400" /> {{ config('site.formats.recorded.catalogue') }}
                </p>

                <p class="text-sm text-slate-400" aria-live="polite">
                    <span x-text="count">{{ $formations->count() }}</span>
                    <span x-text="count > 1 ? 'formations' : 'formation'">formations</span>
                    <button type="button" x-show="hasFilters" x-cloak @click="reset()" class="ml-2 font-semibold text-electric-300 underline-offset-2 hover:underline">Effacer</button>
                </p>
            </div>
        </div>

        {{-- Résultats --}}
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($formations as $formation)
                <x-site.formation-card :formation="$formation" :delay="($loop->index % 3) * 70" x-show="matches({{ $formation->id }})" x-transition:enter="transition duration-300 ease-out motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" />
            @endforeach
        </div>

        <div x-show="count === 0" x-cloak class="card mx-auto mt-8 max-w-lg p-8 text-center">
            <flux:icon.magnifying-glass class="mx-auto size-10 text-slate-500" />
            <p class="mt-4 font-semibold text-white">Aucune formation ne correspond à votre recherche.</p>
            <p class="mt-2 text-sm text-slate-400">Essayez un autre mot ou écrivez-nous : nous vous conseillerons la formation adaptée.</p>
            <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                <button type="button" @click="reset()" class="btn-ghost !py-2.5 !text-sm">Voir toutes les formations</button>
                <a href="{{ route('consultation') }}" class="btn-violet !py-2.5 !text-sm">Consultation gratuite</a>
            </div>
        </div>
    </section>

    <x-site.cta-banner
        title="Vous hésitez entre plusieurs formations ?"
        text="Réservez une consultation gratuite : nous vous aidons à choisir la formation adaptée à votre objectif."
    >
        <a href="{{ route('consultation') }}" class="btn-gold">Réserver une consultation gratuite</a>
        <a href="{{ \App\Support\WhatsApp::url() }}" target="_blank" rel="noopener" class="btn-ghost"><x-site.whatsapp-icon class="size-5" /> Nous écrire sur WhatsApp</a>
    </x-site.cta-banner>
</x-layouts::site>
