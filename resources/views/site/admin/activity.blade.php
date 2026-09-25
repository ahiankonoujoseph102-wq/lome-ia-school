<x-admin.page title="Journal d'activité" subtitle="Qui a fait quoi et quand dans l'administration (heure de Lomé)." width="max-w-4xl">
    <form method="GET" class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.field name="action" label="Action" type="select" :options="['' => 'Toutes les actions'] + $actions" :value="$filters['action'] ?? ''" />
        <x-admin.field name="user" label="Personne" type="select" :options="['' => 'Tout le monde'] + $people->pluck('name', 'id')->all()" :value="$filters['user'] ?? ''" />
        <x-admin.field name="from" label="Du" type="date" :value="$filters['from'] ?? ''" />
        <x-admin.field name="to" label="Au" type="date" :value="$filters['to'] ?? ''" />
        <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4">
            <button type="submit" class="btn-gold">Filtrer</button>
            <a href="{{ route('admin.activity.index') }}" class="btn-ghost">Tout afficher</a>
        </div>
    </form>

    <ol class="space-y-2">
        @forelse ($activities as $activity)
            <li class="card p-4" data-test="activity-row">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span @class([
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1',
                        'text-gold-100 ring-gold-400/70' => $activity->action->isSensitive(),
                        'text-electric-200 ring-electric-400/40' => ! $activity->action->isSensitive(),
                    ])>{{ $activity->action->label() }}</span>
                    <span class="text-sm text-slate-400">{{ $activity->created_at?->translatedFormat('j M Y à H\hi') }}</span>
                    <span class="text-sm font-semibold text-white">{{ $activity->user?->name ?? 'Système' }}</span>
                </div>
                <p class="mt-2 text-sm break-words text-slate-200">{{ $activity->description }}</p>
            </li>
        @empty
            <li class="card p-6 text-center text-slate-400">Aucune activité pour ces filtres.</li>
        @endforelse
    </ol>

    {{ $activities->links() }}
</x-admin.page>
