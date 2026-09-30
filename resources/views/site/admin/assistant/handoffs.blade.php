@php
    use App\Models\AssistantHandoff;
    use App\Support\PhoneNumber;

    $filters = ['a-traiter' => 'À traiter', 'traitees' => 'Traitées', 'toutes' => 'Toutes'];
@endphp
<x-admin.page title="Demandes transmises" subtitle="Questions qu'Ama n'a pas su traiter, transmises à Coach Joseph à la demande des visiteurs." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.assistant.index') }}" class="btn-ghost">Conversations d'Ama</a>
    </x-slot:actions>

    <nav class="flex flex-wrap gap-2 text-sm" aria-label="Filtrer les demandes" data-test="handoff-filters">
        @foreach ($filters as $key => $label)
            <a
                href="{{ route('admin.assistant.handoffs.index', $key === 'a-traiter' ? [] : ['filtre' => $key]) }}"
                @class(['rounded-full px-3 py-1.5 ring-1', 'bg-gold-400/10 text-gold-200 ring-gold-400' => $filter === $key, 'text-slate-300 ring-veil/15 hover:bg-veil/10' => $filter !== $key])
                @if ($filter === $key) aria-current="page" @endif
            >{{ $label }} ({{ $counts[$key] }})</a>
        @endforeach
    </nav>

    <p class="text-sm text-slate-400">Chaque demande vous est aussi envoyée par e-mail dès l'envoi. Répondez au visiteur, puis touchez « Marquer comme traitée ». Demandes effacées automatiquement après {{ $retentionDays }} jours.</p>

    <ol class="space-y-3">
        @forelse ($handoffs as $handoff)
            @php
                $whatsappUrl = $handoff->whatsappReplyUrl();
                $mailto = $handoff->channel === AssistantHandoff::EMAIL && $handoff->email
                    ? 'mailto:'.$handoff->email.'?subject='.rawurlencode('Votre question à Lomé IA School')
                    : null;
            @endphp
            <li class="card space-y-3 p-4 sm:p-5" data-test="handoff-row">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <span class="text-base font-semibold text-white">{{ $handoff->first_name }}</span>
                    @if ($handoff->isPending())
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold text-amber-100 ring-1 ring-amber-400/60" data-test="handoff-status">À traiter</span>
                    @else
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold text-emerald-200 ring-1 ring-emerald-400/50" data-test="handoff-status">Traitée</span>
                    @endif
                    <span class="text-sm text-slate-400">{{ $handoff->created_at?->translatedFormat('j M Y à H\hi') }}</span>
                </div>

                <p class="rounded-2xl bg-night-800 px-4 py-3 text-[15px] leading-relaxed whitespace-pre-line text-slate-100 ring-1 ring-night-600">{{ $handoff->question }}</p>

                <dl class="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[auto_1fr]">
                    <dt class="text-slate-400">Réponse souhaitée</dt>
                    <dd class="font-semibold break-words text-slate-100">
                        {{ $handoff->channel === AssistantHandoff::EMAIL ? 'Par e-mail : '.$handoff->email : 'Sur WhatsApp : '.PhoneNumber::format($handoff->phone) }}
                    </dd>
                    @if ($handoff->page)
                        <dt class="text-slate-400">Page d'origine</dt>
                        <dd class="text-slate-200">{{ $handoff->page }}@if ($handoff->formation) · {{ $handoff->formation }}@endif</dd>
                    @endif
                    <dt class="text-slate-400">Envoyée depuis</dt>
                    <dd class="text-slate-200">{{ $handoff->sourceLabel() }}</dd>
                    @unless ($handoff->isPending())
                        <dt class="text-slate-400">Traitée</dt>
                        <dd class="text-slate-200">{{ $handoff->handled_at?->translatedFormat('j M Y à H\hi') }}@if ($handoff->handler) par {{ $handoff->handler->name }}@endif</dd>
                    @endunless
                </dl>

                @if ($handoff->excerpt)
                    <details class="text-sm">
                        <summary class="cursor-pointer font-semibold text-electric-300">Extrait de la conversation</summary>
                        <p class="mt-2 rounded-xl px-3 py-2 leading-relaxed whitespace-pre-line text-slate-300 ring-1 ring-night-600">{{ $handoff->excerpt }}</p>
                    </details>
                @endif

                <div class="flex flex-wrap items-center gap-2 pt-1">
                    @if ($whatsappUrl)
                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-sm font-semibold text-emerald-100 ring-1 ring-emerald-400/60 hover:bg-emerald-500/25" data-test="handoff-whatsapp">
                            <x-site.whatsapp-icon class="size-4" /> Répondre sur WhatsApp
                        </a>
                    @elseif ($mailto)
                        <a href="{{ $mailto }}" class="inline-flex items-center gap-2 rounded-full bg-electric-500/15 px-4 py-2 text-sm font-semibold text-electric-100 ring-1 ring-electric-400/60 hover:bg-electric-500/25" data-test="handoff-email">
                            <flux:icon.envelope class="size-4" /> Répondre par e-mail
                        </a>
                    @endif
                    <form method="POST" action="{{ route('admin.assistant.handoffs.toggle', $handoff) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-slate-500 hover:bg-veil/10" data-test="handoff-toggle">
                            @if ($handoff->isPending())
                                <flux:icon.check class="size-4" /> Marquer comme traitée
                            @else
                                <flux:icon.arrow-uturn-left class="size-4" /> Remettre à traiter
                            @endif
                        </button>
                    </form>
                    @if ($handoff->conversation)
                        <a href="{{ route('admin.assistant.show', $handoff->conversation) }}" class="text-sm text-electric-300 underline underline-offset-4">Voir la conversation</a>
                    @endif
                </div>
            </li>
        @empty
            <li class="card p-5 text-sm text-slate-400">
                {{ $filter === 'a-traiter' ? 'Aucune demande à traiter.' : 'Aucune demande.' }}
            </li>
        @endforelse
    </ol>

    {{ $handoffs->links() }}
</x-admin.page>
