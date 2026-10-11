@php
    use App\Models\AssistantUsage;
    use App\Settings\SiteSettings;
    use App\Support\AppUrl;
    use App\Support\VoiceAssistant;
    use App\WhatsApp\AgentSettings;
    use App\WhatsApp\WhatsAppPrompt;

    $agent = (array) SiteSettings::get('site.whatsapp_agent');
    $enabled = (bool) old('enabled', $agent['enabled'] ?? false);
    $checklist = AgentSettings::checklist();
    $ready = collect($checklist)->every(fn (array $item): bool => $item['present']);
    $webhookUrl = AppUrl::to(route('whatsapp.webhook'));
    $limits = [
        'contact_daily_limit' => ['Réponses de l\'agent par client et par jour', 'réponses', '30 par défaut. Au-delà, le client reçoit un message d\'attente et l\'équipe est prévenue par e-mail.'],
        'pause_hours' => ['Silence de l\'agent après l\'équipe', 'heures', 'Quand l\'équipe répond à un client, ou quand l\'agent lui passe la main, l\'agent se tait pour ce client pendant ce temps. 24 par défaut.'],
    ];
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card space-y-2 p-4 text-sm text-slate-300">
        <p>Quand un client écrit au numéro WhatsApp relié à l'API de Meta, Ama lui répond automatiquement, avec les mêmes connaissances que sur le site (formations, prix, programme, base de connaissances, guide du paiement). Elle se limite à l'école et à ses formations : WhatsApp interdit les assistants IA « à tout faire ».</p>
        <p class="text-slate-400">Elle passe la main à l'équipe quand le client le demande, pour un cas particulier (paiement, remboursement, réclamation) ou quand elle n'a pas la réponse : elle se tait alors pour ce client et vous recevez un e-mail. Ni le numéro ni le nom du client ne sont envoyés à Google ; les numéros, adresses e-mail et codes écrits sont retirés avant l'envoi.</p>
        <p><a href="{{ route('admin.whatsapp.index') }}" class="font-semibold text-electric-300 underline underline-offset-4">Voir les conversations WhatsApp</a></p>
    </div>

    <section class="card space-y-4 p-5 sm:p-6" data-test="whatsapp-checklist">
        <h2 class="font-semibold text-white">Réglages techniques (dans Laravel Cloud)</h2>
        <ul class="space-y-2 text-sm">
            @foreach ($checklist as $item)
                <li class="flex items-start gap-2">
                    @if ($item['present'])
                        <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-400" />
                        <span class="text-slate-200">{{ $item['label'] }} : présent</span>
                    @else
                        <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0 text-amber-400" />
                        <span class="text-amber-100">{{ $item['label'] }} : manquant</span>
                    @endif
                </li>
            @endforeach
        </ul>
        <p class="text-xs text-slate-400">Les valeurs ne sont jamais affichées ici. Elles se saisissent dans Laravel Cloud (« Environment variables »), puis « Save » et « Deploy ». Ne les collez jamais dans une conversation.</p>

        <div class="space-y-2" x-data="copyText(@js($webhookUrl))">
            <label for="webhook-url" class="block text-sm font-semibold text-white">Adresse du webhook (à recopier chez Meta, WhatsApp > Configuration)</label>
            <div class="flex flex-wrap gap-2">
                <input id="webhook-url" x-ref="field" type="text" readonly value="{{ $webhookUrl }}" class="min-w-0 flex-1 rounded-xl bg-night-800 px-3 py-2 text-sm text-slate-100 ring-1 ring-night-600" data-test="whatsapp-webhook-url">
                <button type="button" @click="copy()" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-slate-100 ring-1 ring-veil/20 hover:bg-veil/10">
                    <flux:icon.document-duplicate class="size-4" /> <span x-text="copied ? 'Copiée' : 'Copier'">Copier</span>
                </button>
            </div>
            <p class="text-xs text-slate-400">Chez Meta, le « jeton de vérification » est la valeur de WHATSAPP_VERIFY_TOKEN. Abonnez ensuite le webhook au champ « messages ».</p>
        </div>

        <p class="text-sm text-slate-300">Demandes à Gemini aujourd'hui (Ama et WhatsApp ensemble) : <strong class="text-white">{{ AssistantUsage::requestsToday() }}</strong> sur {{ VoiceAssistant::limit('daily_limit') }} (limite réglée dans « Assistante Ama »).</p>
    </section>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-5 p-5 sm:p-6">
            <label class="flex cursor-pointer items-start justify-between gap-4" data-test="whatsapp-agent-toggle">
                <span class="min-w-0">
                    <span class="block font-semibold text-white">Agent WhatsApp activé</span>
                    <span class="block text-sm text-slate-400">Désactivé : les messages reçus restent visibles dans « Conversations WhatsApp », mais personne ne répond automatiquement.@unless ($ready) Il ne répondra qu'une fois tous les réglages techniques présents.@endunless</span>
                </span>
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($enabled) class="peer sr-only">
                <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
            </label>

            @foreach ($limits as $name => [$label, $unit, $hint])
                <x-admin.field :name="$name" :label="$label" type="number" :unit="$unit" min="{{ AgentSettings::LIMITS[$name][1] }}" max="{{ AgentSettings::LIMITS[$name][2] }}" :value="$agent[$name] ?? AgentSettings::LIMITS[$name][0]" :hint="$hint" />
            @endforeach
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <x-admin.field name="instructions" label="Rôle, style et règles de l'agent WhatsApp" type="textarea" rows="16" :value="WhatsAppPrompt::effectiveInstructions($agent['instructions'] ?? '')" hint="Videz le champ puis enregistrez pour revenir au texte d'origine. Le code ajoute toujours ses propres règles (limité à l'école, objections, passage à l'équipe), qui priment sur ce texte. Les connaissances (formations, base de connaissances, guide) sont celles d'Ama : elles se modifient dans « Assistante Ama »." class="text-sm leading-relaxed" />
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
