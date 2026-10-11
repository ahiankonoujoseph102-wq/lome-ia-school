@php
    $filters = ['toutes' => 'Toutes', 'a-traiter' => 'Attendent l\'équipe', 'sans-reponse' => 'Questions sans réponse'];
@endphp
<x-admin.page title="Conversations WhatsApp" subtitle="Clients qui écrivent au numéro WhatsApp de l'API : réponses de l'agent IA et de l'équipe." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.settings.edit', 'whatsapp') }}" class="btn-ghost">Réglages de l'agent</a>
    </x-slot:actions>

    @unless ($enabled)
        <x-site.alert type="error">L'agent WhatsApp ne répond pas automatiquement en ce moment (interrupteur éteint ou réglages de Meta incomplets) : voir « Réglages de l'agent ». Les messages reçus s'affichent quand même ici.</x-site.alert>
    @endunless

    <section class="grid grid-cols-2 gap-3 sm:grid-cols-4" data-test="whatsapp-stats">
        @foreach (['received' => 'Messages reçus aujourd\'hui', 'agent' => 'Réponses de l\'agent', 'team' => 'Réponses de l\'équipe', 'failed' => 'Envois refusés'] as $key => $label)
            <div class="card p-4">
                <p class="text-sm text-slate-400">{{ $label }}</p>
                <p @class(['mt-1 text-2xl font-bold', 'text-amber-200' => $key === 'failed' && $stats[$key] > 0, 'text-white' => $key !== 'failed' || $stats[$key] === 0])>{{ $stats[$key] }}</p>
            </div>
        @endforeach
    </section>

    <nav class="flex flex-wrap gap-2 text-sm" aria-label="Filtrer les conversations" data-test="whatsapp-filters">
        @foreach ($filters as $key => $label)
            <a
                href="{{ route('admin.whatsapp.index', $key === 'toutes' ? [] : ['filtre' => $key]) }}"
                @class(['rounded-full px-3 py-1.5 ring-1', 'bg-gold-400/10 text-gold-200 ring-gold-400' => $filter === $key, 'text-slate-300 ring-veil/15 hover:bg-veil/10' => $filter !== $key])
                @if ($filter === $key) aria-current="page" @endif
            >{{ $label }} ({{ $counts[$key] }})</a>
        @endforeach
    </nav>

    <p class="text-sm text-slate-400">WhatsApp n'autorise une réponse libre que pendant les 24 heures qui suivent le dernier message du client. Conversations effacées automatiquement {{ $retentionDays }} jours après le dernier message.</p>

    <ol class="space-y-3">
        @forelse ($contacts as $contact)
            <li>
                <a href="{{ route('admin.whatsapp.show', $contact) }}" class="card block space-y-2 p-4 hover:bg-veil/5 sm:p-5" data-test="whatsapp-row">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <span class="text-base font-semibold text-white">{{ $contact->displayName() }}</span>
                        @if ($contact->profile_name)
                            <span class="text-sm text-slate-400">{{ $contact->phone() }}</span>
                        @endif
                        @if ($contact->needs_human_at)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold text-amber-100 ring-1 ring-amber-400/60" data-test="whatsapp-needs-human">Attend l'équipe</span>
                        @endif
                        @unless ($contact->agentActive())
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold text-slate-200 ring-1 ring-slate-500">{{ $contact->agent_stopped ? 'Agent arrêté' : 'Agent en pause' }}</span>
                        @endunless
                    </div>
                    @if ($contact->last_text)
                        <p class="line-clamp-2 text-sm text-slate-300">{{ $contact->last_text }}</p>
                    @endif
                    <p class="text-xs text-slate-400">{{ $contact->messages_count }} message{{ $contact->messages_count > 1 ? 's' : '' }} · dernier le {{ $contact->last_message_at?->translatedFormat('j M à H\hi') }}</p>
                </a>
            </li>
        @empty
            <li class="card p-5 text-sm text-slate-400">Aucune conversation pour le moment.</li>
        @endforelse
    </ol>

    {{ $contacts->links() }}
</x-admin.page>
