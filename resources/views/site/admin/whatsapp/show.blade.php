@php
    use App\Models\WhatsAppMessage;
    use App\WhatsApp\AgentSettings;
@endphp
<x-admin.page :title="$contact->displayName()" :subtitle="'WhatsApp '.$contact->phone()" :back="route('admin.whatsapp.index')" back-label="Conversations WhatsApp">
    <section class="card space-y-3 p-4 sm:p-5" data-test="whatsapp-contact-status">
        @if ($contact->needs_human_at)
            <p class="text-sm text-amber-100"><strong>Attend l'équipe</strong> depuis le {{ $contact->needs_human_at->translatedFormat('j M à H\hi') }}@if ($contact->needs_human_reason) : {{ $contact->needs_human_reason }}@endif</p>
        @endif

        <p class="text-sm text-slate-300">
            @if ($contact->agent_stopped)
                Agent arrêté pour ce client : seule l'équipe répond.
            @elseif (! $contact->agentActive())
                Agent en pause pour ce client jusqu'au {{ $contact->agent_paused_until?->translatedFormat('j M à H\hi') }}.
            @elseif ($enabled)
                L'agent répond automatiquement à ce client.
            @else
                L'agent WhatsApp est éteint ou incomplet (voir ses réglages).
            @endif
        </p>

        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.whatsapp.handled', $contact) }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-veil/20 hover:bg-veil/10" data-test="whatsapp-handled">
                    {{ $contact->needs_human_at ? 'Marquer comme traitée' : 'Remettre à traiter' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.whatsapp.agent', $contact) }}">
                @csrf
                @if ($contact->agentActive())
                    <input type="hidden" name="action" value="stop">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-veil/20 hover:bg-veil/10" data-test="whatsapp-agent-stop">Arrêter l'agent pour ce client</button>
                @else
                    <input type="hidden" name="action" value="resume">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-emerald-100 ring-1 ring-emerald-400/60 hover:bg-emerald-500/15" data-test="whatsapp-agent-resume">Relancer l'agent pour ce client</button>
                @endif
            </form>
        </div>
    </section>

    <ol class="space-y-3" data-test="whatsapp-messages">
        @foreach ($contact->messages as $message)
            @php($fromClient = $message->sender === WhatsAppMessage::CLIENT)
            <li @class(['flex', 'justify-start' => $fromClient, 'justify-end' => ! $fromClient])>
                <div @class([
                    'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed ring-1',
                    'rounded-bl-md bg-veil/[0.06] text-slate-100 ring-veil/10' => $fromClient,
                    'rounded-br-md bg-emerald-500/15 text-white ring-emerald-400/40' => ! $fromClient,
                ])>
                    <p class="mb-1 text-xs font-semibold text-slate-400">
                        {{ $message->senderLabel() }} · {{ $message->created_at?->translatedFormat('j M H\hi') }}
                        @if ($message->statusLabel() && ! $fromClient) · {{ $message->statusLabel() }}@endif
                        @if ($message->unanswered) · <span class="text-amber-200">Sans réponse</span>@endif
                    </p>
                    <p class="break-words whitespace-pre-line">{{ $message->displayText() }}</p>
                    @if ($message->error)
                        <p class="mt-1 text-xs text-rose-200">{{ $message->error }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    <section class="card space-y-3 p-4 sm:p-5">
        <h2 class="font-semibold text-white">Répondre au client</h2>
        @if ($contact->windowOpen())
            <form method="POST" action="{{ route('admin.whatsapp.reply', $contact) }}" class="space-y-3">
                @csrf
                <x-admin.field name="message" label="Votre réponse" type="textarea" rows="4" hint="Envoyée sur WhatsApp au nom de l'école. L'agent se taira ensuite pour ce client pendant {{ AgentSettings::pauseHours() }} h." />
                <button type="submit" class="btn-gold w-full sm:w-auto" data-test="whatsapp-reply">Envoyer sur WhatsApp</button>
            </form>
        @else
            @error('message')
                <x-site.alert type="error">{{ $message }}</x-site.alert>
            @enderror
            <p class="text-sm text-slate-300" data-test="whatsapp-window-closed">Plus de 24 heures depuis le dernier message du client : WhatsApp n'autorise plus de réponse libre depuis le site. Le client doit vous réécrire d'abord.</p>
        @endif
    </section>
</x-admin.page>
