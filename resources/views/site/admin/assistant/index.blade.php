<x-admin.page title="Conversations d'Ama" subtitle="Assistante IA (Gemini) : demandes du jour, incidents et conversations, sans aucune donnée personnelle." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.settings.edit', 'assistante') }}" class="btn-ghost">Réglages d'Ama</a>
    </x-slot:actions>

    <section class="grid gap-3 sm:grid-cols-3" data-test="assistant-stats">
        <div class="card p-4">
            <p class="text-sm text-slate-400">Demandes envoyées à Google aujourd'hui</p>
            <p class="mt-1 text-2xl font-bold text-white"><span data-test="assistant-requests-today">{{ $requestsToday }}</span> <span class="text-base font-semibold text-slate-400">/ {{ $dailyLimit }}</span></p>
            <p class="mt-1 text-xs text-slate-500">Dont {{ $failuresToday }} sans réponse. Nouvelle journée de Google à {{ $nextReset->translatedFormat('H \h i') }} (heure de Lomé).</p>
        </div>
        <div class="card p-4">
            <p class="text-sm text-slate-400">Conversations commencées aujourd'hui</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ $conversationsToday }}</p>
        </div>
        <div class="card p-4">
            <p class="text-sm text-slate-400">Incidents aujourd'hui</p>
            <p @class(['mt-1 text-2xl font-bold', 'text-amber-200' => $incidentsTodayCount > 0, 'text-white' => $incidentsTodayCount === 0])>{{ $incidentsTodayCount }}</p>
        </div>
    </section>

    <section class="card space-y-3 p-4 sm:p-5" data-test="assistant-incidents">
        <h2 class="font-semibold text-white">Incidents récents</h2>
        <p class="text-sm text-slate-400">À chaque incident, le visiteur a vu un message clair et le bouton « Écrivez-nous sur WhatsApp ».</p>
        @if ($incidentsByType !== [])
            <ul class="flex flex-wrap gap-2 text-xs">
                @foreach ($incidentsByType as $label => $total)
                    <li class="rounded-full px-2.5 py-1 text-amber-100 ring-1 ring-amber-400/50">{{ $label }} : {{ $total }}</li>
                @endforeach
            </ul>
        @endif
        <ol class="divide-y divide-veil/10">
            @forelse ($incidents as $incident)
                <li class="py-2.5 text-sm">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-semibold text-slate-100">{{ $incident->type->label() }}</span>
                        <span class="text-slate-400">{{ $incident->created_at?->translatedFormat('j M Y à H\hi') }}</span>
                        @if ($incident->conversation)
                            <a href="{{ route('admin.assistant.show', $incident->conversation) }}" class="text-electric-300 underline underline-offset-4">Voir la conversation</a>
                        @endif
                    </div>
                    @if ($incident->detail)
                        <p class="mt-1 font-mono text-xs break-words text-slate-400">{{ $incident->detail }}</p>
                    @endif
                </li>
            @empty
                <li class="py-3 text-sm text-slate-400">Aucun incident.</li>
            @endforelse
        </ol>
    </section>

    <section class="space-y-2" data-test="assistant-conversations">
        <h2 class="font-semibold text-white">Conversations</h2>
        <p class="text-sm text-slate-400">Les numéros, adresses e-mail et codes écrits par les visiteurs sont retirés avant l'enregistrement. Conversations effacées automatiquement après {{ $retentionDays }} jours.</p>
        <ol class="space-y-2">
            @forelse ($conversations as $conversation)
                <li>
                    <a href="{{ route('admin.assistant.show', $conversation) }}" class="card flex flex-wrap items-center gap-x-3 gap-y-1 p-4 transition hover:bg-veil/[0.07]" data-test="assistant-conversation-row">
                        <span class="text-sm text-slate-400">{{ ($conversation->last_message_at ?? $conversation->created_at)?->translatedFormat('j M Y à H\hi') }}</span>
                        <span class="text-sm font-semibold text-white">{{ $conversation->messages_count }} message{{ $conversation->messages_count > 1 ? 's' : '' }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm text-slate-300">{{ $conversation->page ?? 'Page inconnue' }}@if ($conversation->formation) · {{ $conversation->formation }}@endif</span>
                        @if ($conversation->voice_used)
                            <span class="rounded-full px-2 py-0.5 text-xs text-electric-200 ring-1 ring-electric-400/40">Micro utilisé</span>
                        @endif
                        @if ($conversation->fallback_shown)
                            <span class="rounded-full px-2 py-0.5 text-xs text-emerald-200 ring-1 ring-emerald-400/50">Relève WhatsApp</span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="card p-6 text-center text-slate-400">Aucune conversation pour le moment.</li>
            @endforelse
        </ol>
        {{ $conversations->links() }}
    </section>
</x-admin.page>
