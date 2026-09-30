@php
    use App\Settings\SiteSettings;
    use App\Support\VoiceAssistant;

    $assistant = (array) SiteSettings::get('site.voice_assistant');
    $switches = [
        ['name' => 'enabled', 'label' => 'Assistante vocale activée', 'hint' => 'Désactivée : le bouton disparaît du site.', 'on' => (bool) old('enabled', $assistant['enabled'] ?? false)],
        ['name' => 'lessons', 'label' => 'Afficher Ama dans le lecteur de cours', 'hint' => 'Désactivé : le bouton n\'apparaît pas pendant qu\'un élève regarde une leçon.', 'on' => (bool) old('lessons', $assistant['lessons'] ?? false)],
        ['name' => 'proactive', 'label' => 'Aide proactive', 'hint' => 'Quand le site repère qu\'un client bloque (même erreur deux fois, code promo refusé, capture refusée, code de confirmation faux deux fois, étape du paiement trop longue, allers-retours), une petite bulle « Vous semblez bloqué ? Ama peut vous guider. » s\'affiche à côté du bouton, une fois par page. Elle n\'ouvre jamais l\'appel ni le micro toute seule.', 'on' => (bool) old('proactive', $assistant['proactive'] ?? true)],
    ];
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card space-y-2 p-4 text-sm text-slate-300">
        <p>Bouton flottant « Parler avec notre assistante » : le visiteur parle avec Ama, l'assistante vocale IA créée sur ElevenLabs. Il apparaît sur toutes les pages publiques, les pages d'inscription, de connexion, de confirmation de l'e-mail et de mot de passe oublié, la page de paiement et l'espace élève. Jamais dans l'administration ni dans la conversation d'un groupe.</p>
        <p class="text-slate-400">Ama reçoit la page consultée, la formation et le montant à payer, l'étape du paiement, le pays et l'opérateur choisis, et la dernière erreur affichée. Jamais de nom, d'e-mail, de numéro, de mot de passe, de code ni de capture.</p>
        <p class="text-slate-400">Rien n'est chargé depuis ElevenLabs tant que l'assistante est désactivée, ni tant que le visiteur n'a pas touché le bouton. Seules les adresses autorisées dans la liste d'autorisation de l'agent, chez ElevenLabs, peuvent se connecter.</p>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-5 p-5 sm:p-6">
            @foreach ($switches as $switch)
                <label class="flex cursor-pointer items-start justify-between gap-4" data-test="voice-assistant-{{ $switch['name'] === 'enabled' ? 'toggle' : $switch['name'] }}">
                    <span class="min-w-0">
                        <span class="block font-semibold text-white">{{ $switch['label'] }}</span>
                        <span class="block text-sm text-slate-400">{{ $switch['hint'] }}</span>
                    </span>
                    <input type="hidden" name="{{ $switch['name'] }}" value="0">
                    <input type="checkbox" name="{{ $switch['name'] }}" value="1" @checked($switch['on']) class="peer sr-only">
                    <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
                </label>
            @endforeach

            <x-admin.field name="help_delay" label="Délai avant de proposer l'aide sur une étape du paiement (secondes)" type="number" min="{{ VoiceAssistant::MIN_HELP_DELAY }}" max="{{ VoiceAssistant::MAX_HELP_DELAY }}" :value="$assistant['help_delay'] ?? VoiceAssistant::DEFAULT_HELP_DELAY" hint="90 secondes par défaut (entre {{ VoiceAssistant::MIN_HELP_DELAY }} et {{ VoiceAssistant::MAX_HELP_DELAY }})." />

            <x-admin.field name="agent_id" label="Identifiant de l'agent ElevenLabs" type="url" :value="$assistant['agent_id'] ?? ''" :placeholder="VoiceAssistant::EXAMPLE_ID" hint="Commence par « agent_ ». Ce n'est pas un secret : il est visible dans le code du site." />
        </section>

        <div class="card space-y-2 p-4 text-sm text-slate-400" data-test="voice-assistant-elevenlabs-setup">
            <p class="font-semibold text-slate-200">Réglages à faire chez ElevenLabs pour qu'Ama connaisse la page</p>
            <p>Dans les instructions de l'agent (« System prompt »), ajoutez une ligne contenant <span class="font-mono text-slate-200">@{{contexte}}</span>. Dans le premier message (« First message »), écrivez seulement <span class="font-mono text-slate-200">@{{message_accueil}}</span>. Dans « Dynamic variables », donnez une valeur de test à chacune.</p>
            <p>Guide des parcours du site pour la base de connaissances de l'agent : fichier <span class="font-mono text-slate-200">docs/guide-ama.md</span>, à recharger chez ElevenLabs à chaque changement.</p>
        </div>

        <div class="card space-y-1 p-4 text-sm text-slate-400">
            <p><span class="font-semibold text-slate-200">Prix des formations :</span> ils sont écrits à la main dans les instructions de l'agent, chez ElevenLabs. Si vous changez un prix sur le site, changez-le aussi chez ElevenLabs.</p>
        </div>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
