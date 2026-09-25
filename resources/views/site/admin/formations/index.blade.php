<x-admin.page title="Formations et prix" subtitle="Ordre d'affichage sur le site, prix, visibilité. Un changement de prix ne modifie jamais les achats déjà faits." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.formations.create') }}" class="btn-gold"><flux:icon.plus class="size-5" /> Nouvelle formation</a>
    </x-slot:actions>

    <ol class="space-y-2">
        @foreach ($formations as $formation)
            <li class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center" data-test="formation-row">
                <x-site.formation-cover :formation="$formation" class="aspect-[16/10] w-full shrink-0 rounded-xl sm:w-28" />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-white">{{ $formation->title }}</p>
                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold text-gold-200">{{ $formation->formatted_price }}</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
                            'text-emerald-200 ring-emerald-400/50' => $formation->is_active,
                            'text-slate-400 ring-veil/20' => ! $formation->is_active,
                        ])>{{ $formation->is_active ? 'Visible' : 'Masquée' }}</span>
                        @if ($formation->is_featured)
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-gold-100 ring-1 ring-gold-400/60">Mise en avant</span>
                        @endif
                        <span class="text-slate-400">{{ $formation->sales_count }} {{ $formation->sales_count > 1 ? 'ventes validées' : 'vente validée' }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('admin.formations.move', [$formation, 'up']) }}">@csrf<button class="grid size-9 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @disabled($loop->first) aria-label="Monter {{ $formation->title }}"><flux:icon.chevron-up class="size-4" /></button></form>
                    <form method="POST" action="{{ route('admin.formations.move', [$formation, 'down']) }}">@csrf<button class="grid size-9 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @disabled($loop->last) aria-label="Descendre {{ $formation->title }}"><flux:icon.chevron-down class="size-4" /></button></form>
                    <a href="{{ route('admin.content.show', $formation) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10">Contenu</a>
                    <a href="{{ route('admin.formations.edit', $formation) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10">Modifier</a>
                </div>
            </li>
        @endforeach
    </ol>
</x-admin.page>
