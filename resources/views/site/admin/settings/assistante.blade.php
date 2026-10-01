@php
    use App\Assistant\AmaKnowledge;
    use App\Assistant\AmaPrompt;
    use App\Contracts\AssistantModel;
    use App\Models\AssistantUsage;
    use App\Settings\SiteSettings;
    use App\Support\VoiceAssistant;

    $assistant = (array) SiteSettings::get('site.voice_assistant');
    $provider = old('provider', ($assistant['provider'] ?? VoiceAssistant::GEMINI) === VoiceAssistant::ELEVENLABS ? VoiceAssistant::ELEVENLABS : VoiceAssistant::GEMINI);
    $knowledge = (array) SiteSettings::get('site.assistant_knowledge');
    $size = AmaPrompt::stats();
    $sizeLimit = AmaPrompt::GOOGLE_TOKENS_PER_MINUTE;
    $keyPresent = app(AssistantModel::class)->isConfigured();
    $providers = [
        VoiceAssistant::GEMINI => ['Ama (Gemini)', 'Notre propre assistante : discussion écrite, micro et lecture à voix haute du navigateur. Réponses de l\'IA Gemini de Google (offre gratuite).'],
        VoiceAssistant::ELEVENLABS => ['ElevenLabs', 'L\'ancien agent vocal créé sur ElevenLabs (gardé, désactivé tant qu\'il n\'est pas choisi ici).'],
    ];
    $limits = [
        'visitor_daily_limit' => ['Messages par visiteur et par jour', 'messages', '30 par défaut. Au-delà, le visiteur voit le bouton « Écrivez-nous sur WhatsApp ».'],
        'max_length' => ['Longueur maximale d\'un message du visiteur', 'caractères', '500 par défaut.'],
        'daily_limit' => ['Demandes par jour pour tout le site', 'demandes', 'Toujours sous le quota gratuit de Google pour le modèle choisi (voir « Limites de Google » dans Google AI Studio). 400 par défaut.'],
        'visitor_minute_limit' => ['Messages par minute pour un visiteur', 'messages', 'Protection contre les abus. 5 par défaut.'],
        'minute_limit' => ['Demandes par minute pour tout le site', 'demandes', 'Toujours sous la limite par minute de Google. 10 par défaut.'],
    ];
    $switches = [
        ['name' => 'enabled', 'label' => 'Assistante activée', 'hint' => 'Désactivée : le bouton disparaît du site.', 'on' => (bool) old('enabled', $assistant['enabled'] ?? false)],
        ['name' => 'lessons', 'label' => 'Afficher Ama dans le lecteur de cours', 'hint' => 'Désactivé : le bouton n\'apparaît pas pendant qu\'un élève regarde une leçon.', 'on' => (bool) old('lessons', $assistant['lessons'] ?? false)],
        ['name' => 'voice_interrupt', 'label' => 'Interruption à la voix (expérimental)', 'hint' => 'Pendant un appel, si le visiteur se met à parler pendant qu\'Ama parle, elle se tait tout de suite et l\'écoute. Le micro reste alors ouvert pendant qu\'Ama parle : sur certains téléphones (Android surtout), le micro peut couper la voix d\'Ama ou entendre sa voix. Si cela pose problème, éteignez cet interrupteur : toucher le cercle coupe toujours la parole à Ama.', 'on' => (bool) old('voice_interrupt', $assistant['voice_interrupt'] ?? false)],
        ['name' => 'proactive', 'label' => 'Aide proactive', 'hint' => 'Quand le site repère qu\'un client bloque (même erreur deux fois, code promo refusé, capture refusée, code de confirmation faux deux fois, étape du paiement trop longue, allers-retours), une petite bulle « Vous semblez bloqué ? Ama peut vous guider. » s\'affiche à côté du bouton, une fois par page. Elle n\'ouvre jamais le micro toute seule.', 'on' => (bool) old('proactive', $assistant['proactive'] ?? true)],
    ];
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card space-y-2 p-4 text-sm text-slate-300">
        <p>Bouton flottant « Parler avec notre assistante » : le visiteur discute avec Ama, l'assistante IA de l'école. Il apparaît sur toutes les pages publiques, les pages d'inscription, de connexion, de confirmation de l'e-mail et de mot de passe oublié, la page de paiement et l'espace élève. Jamais dans l'administration ni dans la conversation d'un groupe.</p>
        <p class="text-slate-400">Ama reçoit la page consultée, la formation et le montant à payer, l'étape du paiement, le pays et l'opérateur choisis, et la dernière erreur affichée. Jamais de nom, d'e-mail, de numéro, de mot de passe, de code ni de capture : les numéros, adresses e-mail et codes écrits par le visiteur sont retirés avant l'envoi.</p>
        <p><a href="{{ route('admin.assistant.index') }}" class="font-semibold text-electric-300 underline underline-offset-4">Voir les conversations d'Ama, les demandes du jour et les incidents</a></p>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6" x-data="{ provider: @js($provider) }">
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
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <fieldset class="space-y-2" data-test="assistant-provider">
                <legend class="font-semibold text-white">Assistante</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($providers as $value => [$label, $hint])
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-veil/5 px-4 py-3 ring-1 ring-veil/15 transition hover:bg-veil/10 has-[:checked]:bg-gold-400/10 has-[:checked]:ring-2 has-[:checked]:ring-gold-400 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-gold-300">
                            <input type="radio" name="provider" value="{{ $value }}" x-model="provider" class="mt-1 size-4 accent-gold-400" @checked($provider === $value)>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-100">{{ $label }}</span>
                                <span class="block text-xs text-slate-400">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        {{-- Ama (Gemini) --}}
        <div x-show="provider === 'gemini'" class="space-y-6">
            <section class="card space-y-2 p-4 text-sm" data-test="gemini-status">
                @if ($keyPresent)
                    <p class="flex items-center gap-2 font-semibold text-emerald-200"><flux:icon.check-circle class="size-5" /> Clé Gemini présente sur le serveur.</p>
                @else
                    <p class="flex items-center gap-2 font-semibold text-amber-200"><flux:icon.exclamation-triangle class="size-5" /> Clé Gemini absente : le bouton reste masqué sur le site. Ajoutez la variable GEMINI_API_KEY sur le serveur.</p>
                @endif
                <p class="text-slate-400">Modèle utilisé : <span class="font-mono text-slate-200">{{ config('services.gemini.model') }}</span> (variable GEMINI_MODEL, modifiable sur le serveur). La clé n'est jamais affichée ici ni envoyée au navigateur.</p>
                <p class="text-slate-400">Demandes envoyées à Google aujourd'hui : <span class="font-semibold text-slate-200">{{ AssistantUsage::requestsToday() }}</span>. La journée de Google recommence à {{ AssistantUsage::nextReset()->translatedFormat('H \h i') }}, heure de Lomé.</p>
            </section>

            <section class="card space-y-4 p-5 sm:p-6">
                <h2 class="font-semibold text-white">Instructions d'Ama</h2>
                <x-admin.field name="instructions" label="Rôle, style et règles d'Ama" type="textarea" rows="16" :value="AmaPrompt::effectiveInstructions($assistant['instructions'] ?? '')" hint="Videz le champ puis enregistrez pour revenir au texte d'origine." class="text-sm leading-relaxed" />
            </section>

            <section class="card space-y-4 p-5 sm:p-6" data-test="assistant-knowledge">
                <h2 class="font-semibold text-white">Base de connaissances d'Ama</h2>
                <p class="text-sm text-slate-400">Ama répond seulement à partir de ce qui est écrit ici et de ce qu'elle reçoit du site (voir plus bas). N'écrivez que des informations exactes : elle les répétera telles quelles. Pas de chiffre, de témoignage ni de programme inventés. Quand la réponse n'est pas dans ces textes, elle dit qu'elle n'a pas l'information : complétez alors cette base grâce à la liste « Questions sans réponse » des conversations.</p>

                <x-admin.field name="knowledge_school" label="L'école et son fondateur" type="textarea" rows="14" :value="$knowledge['school'] ?? AmaKnowledge::DEFAULT_SCHOOL" hint="Texte de départ : uniquement des faits déjà présents sur le site. {adresse} et {horaires} sont remplacés par les coordonnées des Réglages du site. Videz le champ puis enregistrez pour revenir au texte de départ." class="text-sm leading-relaxed" />

                <x-admin.questions-editor name="knowledge_faq" label="Questions fréquentes (une question, sa réponse)" :items="$knowledge['faq'] ?? []" />

                <x-admin.field name="knowledge_other" label="Autres informations" type="textarea" rows="8" :value="$knowledge['other'] ?? ''" hint="Tout ce qu'Ama doit savoir d'autre (partenaires, méthode de travail, précisions…). Vide au départ." class="text-sm leading-relaxed" />
            </section>

            <section class="card space-y-3 p-5 sm:p-6" data-test="assistant-size">
                <h2 class="font-semibold text-white">Ce qu'Ama reçoit à chaque message</h2>
                <p class="text-sm text-slate-400">Dans cet ordre, avec des titres clairs, lu directement dans la base et les réglages : (1) instructions et règles, (2) informations de l'école, (3) base de connaissances ci-dessus, (4) liste de toutes les formations visibles avec leur prix exact et leur phrase, et le détail complet (description, modules et leçons, séances en direct) seulement de la formation de la page ou de celle que le visiteur vient de nommer, (5) pages publiques et règles des conditions de vente, (6) guide des parcours du site (fichier docs/guide-ama.md) : une ligne « En bref » par partie, et le détail seulement des parties qui concernent la page ou la question du visiteur, (7) contexte de la page du visiteur.</p>
                <dl class="grid gap-1 text-sm sm:grid-cols-2" data-test="assistant-size-parts">
                    @foreach ($size['parts'] as $part => $tokens)
                        <div class="flex justify-between gap-3 rounded-lg bg-veil/5 px-3 py-1.5"><dt class="text-slate-300">{{ $part }}</dt><dd class="text-slate-100">≈ {{ number_format($tokens, 0, ',', ' ') }} jetons</dd></div>
                    @endforeach
                    <div class="flex justify-between gap-3 rounded-lg bg-veil/5 px-3 py-1.5"><dt class="text-slate-300">Conversation (12 derniers messages) et réponse</dt><dd class="text-slate-100">≈ {{ number_format(AmaPrompt::CONVERSATION_ALLOWANCE, 0, ',', ' ') }} jetons</dd></div>
                </dl>
                <p class="text-sm font-semibold text-slate-400">Ajouts selon la situation</p>
                <dl class="grid gap-1 text-sm sm:grid-cols-2" data-test="assistant-size-extras">
                    <div class="flex justify-between gap-3 rounded-lg bg-veil/5 px-3 py-1.5"><dt class="text-slate-300">Détail d'une formation (le plus lourd : {{ $size['detailTitle'] ?? 'aucune' }})</dt><dd class="text-slate-100">+ {{ number_format($size['detail'], 0, ',', ' ') }} jetons</dd></div>
                    <div class="flex justify-between gap-3 rounded-lg bg-veil/5 px-3 py-1.5"><dt class="text-slate-300">Guide détaillé en entier</dt><dd class="text-slate-100">+ {{ number_format($size['guideExtra'], 0, ',', ' ') }} jetons</dd></div>
                    <div class="flex justify-between gap-3 rounded-lg bg-veil/5 px-3 py-1.5"><dt class="text-slate-300">Règles du mode appel</dt><dd class="text-slate-100">+ {{ number_format($size['call'], 0, ',', ' ') }} jetons</dd></div>
                </dl>
                <p class="text-sm text-slate-200">Demande habituelle : environ <span class="font-semibold" data-test="assistant-size-total">{{ number_format($size['total'], 0, ',', ' ') }}</span> jetons, soit environ <span class="font-semibold" data-test="assistant-size-request">{{ number_format($size['perRequest'], 0, ',', ' ') }}</span> jetons avec la conversation. Cas le plus lourd (tous les ajouts à la fois) : environ <span class="font-semibold" data-test="assistant-size-max">{{ number_format($size['perRequestMax'], 0, ',', ' ') }}</span> jetons. Au rythme maximum réglé ({{ VoiceAssistant::limit('minute_limit') }} demandes par minute, en demandes habituelles) : environ {{ number_format($size['perMinute'], 0, ',', ' ') }} jetons par minute, pour {{ number_format($sizeLimit, 0, ',', ' ') }} autorisés par l'offre gratuite de Google (à vérifier dans Google AI Studio).</p>
                @if ($size['perMinute'] > $sizeLimit)
                    <x-site.alert type="error">Trop lourd pour la limite par minute de Google : raccourcissez les textes ci-dessus ou baissez « Demandes par minute pour tout le site ».</x-site.alert>
                @elseif ($size['shortened'])
                    <p class="text-sm text-amber-200">Les descriptions des formations sont raccourcies automatiquement (plus de {{ number_format(AmaKnowledge::TOKEN_BUDGET, 0, ',', ' ') }} jetons). Les titres des modules et des leçons sont toujours envoyés en entier.</p>
                @else
                    <p class="flex items-center gap-2 text-sm text-emerald-200"><flux:icon.check-circle class="size-5" /> Taille confortable. Estimation : la vraie mesure est faite par Google.</p>
                @endif
            </section>

            <section class="card space-y-4 p-5 sm:p-6">
                <h2 class="font-semibold text-white">Limites</h2>
                <p class="text-sm text-slate-400">Quand une limite est atteinte, ou si Gemini ne répond pas, le visiteur voit un message clair et le bouton « Écrivez-nous sur WhatsApp », jamais d'erreur technique. Chaque cas est noté dans les incidents.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($limits as $name => [$label, $unit, $hint])
                        <x-admin.field :name="$name" :label="$label" type="number" :unit="$unit" min="{{ VoiceAssistant::LIMITS[$name][1] }}" max="{{ VoiceAssistant::LIMITS[$name][2] }}" :value="$assistant[$name] ?? VoiceAssistant::LIMITS[$name][0]" :hint="$hint" />
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Agent ElevenLabs (gardé) --}}
        <div x-show="provider === 'elevenlabs'" x-cloak class="space-y-6">
            <section class="card space-y-4 p-5 sm:p-6">
                <x-admin.field name="agent_id" label="Identifiant de l'agent ElevenLabs" type="url" :value="$assistant['agent_id'] ?? ''" :placeholder="VoiceAssistant::EXAMPLE_ID" hint="Commence par « agent_ ». Ce n'est pas un secret : il est visible dans le code du site." />
            </section>

            <div class="card space-y-2 p-4 text-sm text-slate-400" data-test="voice-assistant-elevenlabs-setup">
                <p class="font-semibold text-slate-200">Réglages à faire chez ElevenLabs pour que l'agent connaisse la page</p>
                <p>Dans les instructions de l'agent (« System prompt »), ajoutez une ligne contenant <span class="font-mono text-slate-200">@{{contexte}}</span>. Dans le premier message (« First message »), écrivez seulement <span class="font-mono text-slate-200">@{{message_accueil}}</span>. Dans « Dynamic variables », donnez une valeur de test à chacune.</p>
                <p>Guide des parcours du site pour la base de connaissances de l'agent : fichier <span class="font-mono text-slate-200">docs/guide-ama.md</span>, à recharger chez ElevenLabs à chaque changement.</p>
                <p><span class="font-semibold text-slate-200">Prix des formations :</span> avec ElevenLabs, ils sont écrits à la main dans les instructions de l'agent. Si vous changez un prix sur le site, changez-le aussi chez ElevenLabs.</p>
            </div>
        </div>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
