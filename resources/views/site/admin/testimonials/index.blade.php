<x-admin.page title="Témoignages" subtitle="Uniquement de vrais témoignages d'élèves, publiés avec leur accord. Tant qu'aucun n'est affiché, la section est masquée sur le site." :back="route('admin.settings.index')" back-label="Réglages du site">
    <x-slot:actions>
        <a href="{{ route('admin.testimonials.create') }}" class="btn-gold"><flux:icon.plus class="size-5" /> Ajouter</a>
    </x-slot:actions>

    @forelse ($testimonials as $testimonial)
        <article class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center" data-test="testimonial-row">
            @if ($testimonial->type === 'screenshot' && $testimonial->imageUrl())
                <img src="{{ $testimonial->imageUrl() }}" alt="" class="h-20 w-16 shrink-0 rounded-lg object-cover ring-1 ring-veil/10">
            @elseif ($testimonial->type === 'text' && $testimonial->photoUrl())
                <img src="{{ $testimonial->photoUrl() }}" alt="" class="size-14 shrink-0 rounded-full object-cover">
            @else
                <span class="grid size-14 shrink-0 place-items-center rounded-full bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30">
                    <flux:icon :name="$testimonial->type === 'video' ? 'play' : 'chat-bubble-bottom-center-text'" class="size-6" />
                </span>
            @endif
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-2 font-semibold text-white">
                    {{ $testimonial->name ?: 'Sans nom' }}
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
                        'text-emerald-200 ring-emerald-400/50' => $testimonial->is_active,
                        'text-slate-400 ring-veil/20' => ! $testimonial->is_active,
                    ])>{{ $testimonial->is_active ? 'Affiché' : 'Masqué' }}</span>
                </p>
                <p class="text-sm text-slate-400">{{ $testimonial->typeLabel() }}{{ $testimonial->formation ? ' · '.$testimonial->formation : '' }}</p>
                @if ($testimonial->text)
                    <p class="mt-1 line-clamp-2 text-sm text-slate-300">« {{ $testimonial->text }} »</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('admin.testimonials.move', [$testimonial, 'up']) }}">@csrf<button class="grid size-9 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @disabled($loop->first) aria-label="Monter"><flux:icon.chevron-up class="size-4" /></button></form>
                <form method="POST" action="{{ route('admin.testimonials.move', [$testimonial, 'down']) }}">@csrf<button class="grid size-9 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @disabled($loop->last) aria-label="Descendre"><flux:icon.chevron-down class="size-4" /></button></form>
                <form method="POST" action="{{ route('admin.testimonials.toggle', $testimonial) }}">@csrf<button class="rounded-full px-3 py-2 text-sm font-semibold text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10">{{ $testimonial->is_active ? 'Masquer' : 'Afficher' }}</button></form>
                <a href="{{ route('admin.testimonials.edit', $testimonial) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10">Modifier</a>
            </div>
        </article>
    @empty
        <div class="card p-6 text-center text-slate-400">
            Aucun témoignage pour le moment : la section « Témoignages » est masquée sur le site.
        </div>
    @endforelse
</x-admin.page>
