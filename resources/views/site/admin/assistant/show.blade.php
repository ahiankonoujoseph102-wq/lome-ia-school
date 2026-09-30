<x-admin.page title="Conversation avec Ama" :subtitle="($conversation->page ?? 'Page inconnue').($conversation->formation ? ' · '.$conversation->formation : '')" :back="route('admin.assistant.index')" back-label="Conversations d'Ama">
    <p class="text-sm text-slate-400">Commencée le {{ $conversation->created_at?->translatedFormat('j F Y à H\hi') }}. Visiteur anonyme : les numéros, adresses e-mail et codes écrits sont retirés avant l'enregistrement.</p>

    @if ($incidents->isNotEmpty())
        <section class="card space-y-2 p-4">
            <h2 class="font-semibold text-white">Incidents</h2>
            @foreach ($incidents as $incident)
                <p class="text-sm text-amber-100">{{ $incident->type->label() }} <span class="text-slate-400">({{ $incident->created_at?->translatedFormat('j M à H\hi') }})</span></p>
            @endforeach
        </section>
    @endif

    <ol class="space-y-3" data-test="assistant-messages">
        @foreach ($conversation->messages as $message)
            <li @class(['flex', 'justify-end' => $message->role === 'user', 'justify-start' => $message->role !== 'user'])>
                <div @class([
                    'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed ring-1',
                    'rounded-br-md bg-electric-500/25 text-white ring-electric-300/40' => $message->role === 'user',
                    'rounded-bl-md bg-veil/[0.06] text-slate-100 ring-veil/10' => $message->role !== 'user',
                ])>
                    <p class="mb-1 text-xs font-semibold text-slate-400">{{ $message->role === 'user' ? 'Visiteur' : 'Ama' }} · {{ $message->created_at?->format('H\hi') }}</p>
                    <p class="whitespace-pre-line">{{ $message->content }}</p>
                </div>
            </li>
        @endforeach
    </ol>
</x-admin.page>
