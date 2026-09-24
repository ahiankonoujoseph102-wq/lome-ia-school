<x-layouts::site title="Sessions" noindex>
    <div class="mx-auto max-w-4xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        @can('access-admin')
            <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
                <flux:icon.arrow-left class="size-4" /> Administration
            </a>
        @endcan

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">Sessions en direct et à Lomé</h1>
                <p class="mt-1 text-slate-400">Sessions ponctuelles des formations enregistrées. Heures de Lomé (GMT).</p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
            <a href="{{ route('admin.sessions.create', ['type' => 'online']) }}" class="btn-violet !px-4"><flux:icon.video-camera class="size-5" /> En direct</a>
            <a href="{{ route('admin.sessions.create', ['type' => 'onsite']) }}" class="btn-gold !px-4"><flux:icon.map-pin class="size-5" /> Présentiel</a>
        </div>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif

        <h2 class="mt-8 text-lg font-bold text-white">À venir</h2>
        <div class="mt-3 space-y-3" data-test="upcoming-sessions">
            @forelse ($upcoming as $session)
                <a href="{{ route('admin.sessions.show', $session) }}" class="card card-hover block p-5">
                    <x-site.session-summary :session="$session" show-formation :show-seats="false" />
                    <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-veil/10 pt-3 text-sm text-slate-400">
                        @if ($session->isOnsite())
                            <span><span class="font-semibold text-white">{{ $session->seats_count }}</span> / {{ $session->capacity }} inscrits</span>
                            @if ($session->waitlist_count > 0)
                                <span><span class="font-semibold text-electric-200">{{ $session->waitlist_count }}</span> en liste d'attente</span>
                            @endif
                        @else
                            <span>Tous les élèves de la formation · sans inscription</span>
                        @endif
                        @if ($session->refunds_count > 0)
                            <span class="font-semibold text-gold-200">{{ $session->refunds_count }} supplément(s) à rembourser</span>
                        @endif
                    </p>
                </a>
            @empty
                <div class="card p-6 text-center text-sm text-slate-400">Aucune session prévue. Créez-en une avec les boutons ci-dessus.</div>
            @endforelse
        </div>

        <h2 class="mt-10 text-lg font-bold text-white">Historique</h2>
        <ul class="card mt-3 divide-y divide-veil/[0.06]" data-test="past-sessions">
            @forelse ($past as $session)
                <li>
                    <a href="{{ route('admin.sessions.show', $session) }}" class="flex items-center gap-3 px-4 py-3.5 hover:bg-veil/[0.03]">
                        <span @class(['grid size-10 shrink-0 place-items-center rounded-xl', 'bg-electric-500/15 text-electric-300' => $session->isOnline(), 'bg-gold-400/15 text-gold-300' => $session->isOnsite()])>
                            <flux:icon :icon="$session->format->icon()" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-white">{{ $session->displayTitle() }}</span>
                            <span class="block truncate text-xs text-slate-400">{{ $session->formation->title }} · {{ $session->starts_at->translatedFormat('j F Y') }}</span>
                        </span>
                        @if ($session->refunds_count > 0)
                            <span class="shrink-0 rounded-full bg-gold-400/15 px-2.5 py-1 text-xs font-semibold text-gold-200">{{ $session->refunds_count }} à rembourser</span>
                        @elseif ($session->isCancelled())
                            <span class="shrink-0 rounded-full bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-200">Annulée</span>
                        @elseif ($session->isOnsite())
                            <span class="shrink-0 text-xs text-slate-400">{{ $session->attended_count }} présent(s)</span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="px-4 py-5 text-center text-sm text-slate-400">Aucune session passée.</li>
            @endforelse
        </ul>
    </div>
</x-layouts::site>
