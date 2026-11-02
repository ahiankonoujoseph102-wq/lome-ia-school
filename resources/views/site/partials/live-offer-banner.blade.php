{{--
    Bandeau court de l'accueil vers une formation proposée en direct (config/live_offers.php).
    Disparaît tout seul après la dernière séance. Style stable face au mode sombre forcé :
    fond foncé, texte clair, filet doré dessiné en ombre (pas de bordure).
--}}
@foreach (\App\Support\LiveOffer::current() as $offer)
    <a href="{{ route('formations.show', $offer->formation) }}#formation-en-direct" class="theme-island group block bg-night-900 shadow-[inset_0_-1px_0_var(--color-gold-500)] hover:bg-night-800" data-test="home-live-offer">
        <span class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-3 sm:px-6">
            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-rose-500/15 px-2.5 py-1 text-xs font-bold text-rose-100 uppercase ring-1 ring-rose-400/40">
                <span class="size-1.5 rounded-full bg-rose-400"></span> En direct
            </span>
            <span class="min-w-0 flex-1 text-sm text-slate-200">{{ $offer->bannerText() }}</span>
            <span class="hidden shrink-0 items-center gap-1 text-sm font-semibold text-gold-300 sm:inline-flex">Voir le programme <flux:icon.arrow-right class="size-4" /></span>
            <flux:icon.arrow-right class="size-5 shrink-0 text-gold-300 sm:hidden" />
        </span>
    </a>
@endforeach
