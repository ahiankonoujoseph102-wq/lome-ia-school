@php
    use App\Models\QuoteRequest;

    $filters = ['a-traiter' => 'À traiter', 'traitees' => 'Traitées', 'toutes' => 'Toutes'];
@endphp
<x-admin.page title="Demandes de devis" subtitle="Demandes envoyées depuis la page « Entreprises et réalisations sur mesure »." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('business') }}#devis" target="_blank" class="btn-ghost">Voir le formulaire <flux:icon.arrow-up-right class="size-4" /></a>
    </x-slot:actions>

    <nav class="flex flex-wrap gap-2 text-sm" aria-label="Filtrer les demandes" data-test="quote-filters">
        @foreach ($filters as $key => $label)
            <a
                href="{{ route('admin.quotes.index', $key === 'a-traiter' ? [] : ['filtre' => $key]) }}"
                @class(['rounded-full px-3 py-1.5 ring-1', 'bg-gold-400/10 text-gold-200 ring-gold-400' => $filter === $key, 'text-slate-300 ring-veil/15 hover:bg-veil/10' => $filter !== $key])
                @if ($filter === $key) aria-current="page" @endif
            >{{ $label }} ({{ $counts[$key] }})</a>
        @endforeach
    </nav>

    <p class="text-sm text-slate-400">Chaque demande vous est aussi envoyée par e-mail dès l'envoi. Répondez à la personne, puis touchez « Marquer comme traitée ». Demandes effacées automatiquement après {{ QuoteRequest::RETENTION_MONTHS }} mois.</p>

    <ol class="space-y-3">
        @forelse ($quotes as $quote)
            <li class="card space-y-3 p-4 sm:p-5" data-test="quote-row">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <span class="text-base font-semibold text-white">{{ $quote->name }}</span>
                    @if ($quote->organization)
                        <span class="text-sm text-slate-300">{{ $quote->organization }}</span>
                    @endif
                    @if ($quote->isPending())
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold text-amber-100 ring-1 ring-amber-400/60">À traiter</span>
                    @else
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold text-emerald-200 ring-1 ring-emerald-400/50">Traitée</span>
                    @endif
                    <span class="text-sm text-slate-400">{{ $quote->created_at?->translatedFormat('j M Y à H\hi') }}</span>
                </div>

                <p class="text-sm font-semibold text-gold-200">{{ $quote->serviceLabel() }}</p>
                <p class="rounded-2xl bg-night-800 px-4 py-3 text-[15px] leading-relaxed whitespace-pre-line text-slate-100 ring-1 ring-night-600">{{ $quote->message }}</p>

                <dl class="grid gap-x-4 gap-y-1.5 text-sm sm:grid-cols-[auto_1fr]">
                    <dt class="text-slate-400">E-mail</dt>
                    <dd class="font-semibold break-words text-slate-100">{{ $quote->email }}</dd>
                    @if ($quote->phoneLabel())
                        <dt class="text-slate-400">WhatsApp</dt>
                        <dd class="font-semibold text-slate-100">{{ $quote->phoneLabel() }}</dd>
                    @endif
                    @unless ($quote->isPending())
                        <dt class="text-slate-400">Traitée</dt>
                        <dd class="text-slate-200">{{ $quote->handled_at?->translatedFormat('j M Y à H\hi') }}@if ($quote->handler) par {{ $quote->handler->name }}@endif</dd>
                    @endunless
                </dl>

                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <a href="mailto:{{ $quote->email }}?subject={{ rawurlencode('Votre demande de devis : '.$quote->serviceLabel()) }}" class="inline-flex items-center gap-2 rounded-full bg-electric-500/15 px-4 py-2 text-sm font-semibold text-electric-100 ring-1 ring-electric-400/60 hover:bg-electric-500/25" data-test="quote-email">
                        <flux:icon.envelope class="size-4" /> Répondre par e-mail
                    </a>
                    @if ($quote->whatsappUrl())
                        <a href="{{ $quote->whatsappUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-emerald-500/15 px-4 py-2 text-sm font-semibold text-emerald-100 ring-1 ring-emerald-400/60 hover:bg-emerald-500/25" data-test="quote-whatsapp">
                            <x-site.whatsapp-icon class="size-4" /> Répondre sur WhatsApp
                        </a>
                    @endif
                    <form method="POST" action="{{ route('admin.quotes.toggle', $quote) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-slate-500 hover:bg-veil/10" data-test="quote-toggle">
                            @if ($quote->isPending())
                                <flux:icon.check class="size-4" /> Marquer comme traitée
                            @else
                                <flux:icon.arrow-uturn-left class="size-4" /> Remettre à traiter
                            @endif
                        </button>
                    </form>
                </div>
            </li>
        @empty
            <li class="card p-5 text-sm text-slate-400">
                {{ $filter === 'a-traiter' ? 'Aucune demande à traiter.' : 'Aucune demande.' }}
            </li>
        @endforelse
    </ol>

    {{ $quotes->links() }}
</x-admin.page>
