{{--
    Assistante vocale IA « Ama » (agent ElevenLabs), pages publiques uniquement (App\Support\VoiceAssistant::PAGES).
    Aucun script d'ElevenLabs dans la page : il n'est chargé qu'au toucher du bouton (composant « voiceAssistant »
    de site.js), puis l'élément officiel <elevenlabs-convai> s'ouvre à la place du bouton.
    Style stable face au mode sombre forcé de Chrome : fond foncé, texte clair, contour en ombre (ring).
    En bas à gauche : jamais sur le bouton WhatsApp (à droite sur ordinateur) ; au-dessus de la barre d'achat
    sur téléphone (page d'une formation, classe « has-sticky-cta » du body).
--}}
@php
    use App\Support\VoiceAssistant;
@endphp

<div
    x-data="voiceAssistant(@js([
        'agentId' => VoiceAssistant::agentId(),
        'scriptUrl' => VoiceAssistant::SCRIPT_URL,
        'texts' => VoiceAssistant::TEXTS,
    ]))"
    x-show="! opened"
    class="voice-assistant fixed bottom-[max(1rem,env(safe-area-inset-bottom))] left-4 z-40 flex max-w-[calc(100vw-2rem)] flex-col items-start gap-2 sm:left-6 lg:bottom-6"
    data-test="voice-assistant"
>
    <p x-show="error" x-cloak x-text="error" role="alert" class="max-w-xs rounded-2xl bg-night-900/95 px-4 py-3 text-sm text-slate-100 shadow-xl ring-1 shadow-black/40 ring-rose-400/60"></p>

    <button
        type="button"
        @click="open()"
        :disabled="loading"
        :aria-busy="loading"
        class="group flex items-center gap-2.5 rounded-full bg-night-900/95 py-2 pr-4 pl-2 text-sm font-semibold text-white shadow-xl ring-2 shadow-black/40 ring-electric-400/70 backdrop-blur-md transition hover:ring-gold-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-300 disabled:cursor-wait"
    >
        <span class="relative grid size-8 shrink-0 place-items-center rounded-full bg-electric-500/25 text-electric-200 ring-1 ring-electric-300/50">
            <flux:icon.microphone x-show="! loading" class="size-4.5" />
            <svg x-show="loading" x-cloak class="size-4.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        </span>
        <span x-text="loading ? 'Connexion…' : 'Parler avec notre assistante'">Parler avec notre assistante</span>
    </button>
</div>
