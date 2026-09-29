@php
    use App\Settings\SiteSettings;
    use App\Support\VoiceAssistant;

    $assistant = (array) SiteSettings::get('site.voice_assistant');
    $enabled = (bool) old('enabled', $assistant['enabled'] ?? false);
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card space-y-2 p-4 text-sm text-slate-300">
        <p>Bouton flottant « Parler avec notre assistante » : le visiteur parle avec Ama, l'assistante vocale IA créée sur ElevenLabs. Il apparaît uniquement sur l'accueil, la liste des formations, la page d'une formation, la page Entreprises et la page Contact.</p>
        <p class="text-slate-400">Rien n'est chargé depuis ElevenLabs tant que l'assistante est désactivée, ni tant que le visiteur n'a pas touché le bouton. Seules les adresses autorisées dans la liste d'autorisation de l'agent, chez ElevenLabs, peuvent se connecter.</p>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-5 p-5 sm:p-6">
            <label class="flex cursor-pointer items-start justify-between gap-4" data-test="voice-assistant-toggle">
                <span class="min-w-0">
                    <span class="block font-semibold text-white">Assistante vocale activée</span>
                    <span class="block text-sm text-slate-400">Désactivée : le bouton disparaît du site.</span>
                </span>
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($enabled) class="peer sr-only">
                <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
            </label>

            <x-admin.field name="agent_id" label="Identifiant de l'agent ElevenLabs" type="url" :value="$assistant['agent_id'] ?? ''" :placeholder="VoiceAssistant::EXAMPLE_ID" hint="Commence par « agent_ ». Ce n'est pas un secret : il est visible dans le code du site." />
        </section>

        <div class="card space-y-1 p-4 text-sm text-slate-400">
            <p><span class="font-semibold text-slate-200">Prix des formations :</span> ils sont écrits à la main dans les instructions de l'agent, chez ElevenLabs. Si vous changez un prix sur le site, changez-le aussi chez ElevenLabs.</p>
        </div>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
