{{--
    Assistante vocale IA « Ama » (agent ElevenLabs), sur les pages de App\Support\VoiceAssistant::PAGES (et le lecteur
    de cours si l'admin l'a choisi). Contexte de la page sans donnée personnelle et bulle « Vous semblez bloqué ? »
    (une fois par page et par visite) : voir le composant « voiceAssistant » de site.js et resources/js/ama.js.
    Aucun script d'ElevenLabs dans la page : il n'est chargé qu'au toucher du bouton (composant « voiceAssistant »
    de site.js), puis l'élément officiel <elevenlabs-convai> s'ouvre à la place du bouton.
    Style stable face au mode sombre forcé de Chrome : fond foncé, texte clair, contour en ombre (ring).
    Sur téléphone et tablette : bouton rond compact en bas à droite (le bouton WhatsApp est alors dans l'en-tête), pour
    ne cacher ni le prix ni les grands boutons pleine largeur (« Acheter maintenant », « Envoyer la capture de mon
    paiement »…) ; au-dessus de la barre d'achat (« has-sticky-cta ») et de la barre de l'espace élève (« has-bottom-nav »).
    Sur ordinateur : bouton avec son texte en bas à gauche (le bouton WhatsApp flottant est à droite).
--}}
@php
    use App\Support\VoiceAssistant;
@endphp

<div
    x-data="voiceAssistant(@js(VoiceAssistant::browserConfig(request())))"
    x-show="! opened"
    class="voice-assistant fixed right-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-40 flex max-w-[calc(100vw-2rem)] items-end gap-2 sm:right-6 lg:right-auto lg:bottom-6 lg:left-6 lg:flex-col lg:items-start"
    data-test="voice-assistant"
>
    <p x-show="error" x-cloak x-text="error" role="alert" class="max-w-[calc(100vw-6.5rem)] rounded-2xl sm:max-w-xs bg-night-900/95 px-4 py-3 text-sm text-slate-100 shadow-xl ring-1 shadow-black/40 ring-rose-400/60"></p>

    {{-- Aide proactive : ouvre seulement le panneau d'Ama (jamais l'appel ni le micro). Invisible tant qu'elle
         passerait au-dessus d'un bouton ou d'un champ de la page (« covering »). --}}
    <div x-show="bubble" x-cloak x-ref="bubble" :class="covering && 'invisible'" role="status" class="relative max-w-[calc(100vw-6.5rem)] rounded-2xl sm:max-w-[17rem] bg-night-900/95 py-2.5 pr-10 pl-4 shadow-xl ring-2 shadow-black/40 ring-gold-500" data-test="voice-assistant-help">
        <button type="button" @click="open()" class="text-left text-sm leading-snug font-semibold text-white">
            Vous semblez bloqué ? <span class="text-gold-200">Ama peut vous guider.</span>
        </button>
        <button type="button" @click="closeBubble()" class="absolute top-1/2 right-1.5 grid size-8 -translate-y-1/2 place-items-center rounded-full text-slate-300 hover:bg-veil/10 hover:text-white" aria-label="Fermer ce message">
            <flux:icon.x-mark class="size-4" />
        </button>
    </div>

    <button
        type="button"
        @click="open()"
        :disabled="loading"
        :aria-busy="loading"
        aria-label="Parler avec notre assistante"
        :class="bubble && covering ? 'ring-gold-500' : 'ring-electric-400/70'"
        class="group relative flex items-center gap-2.5 rounded-full bg-night-900/95 p-2 text-sm font-semibold text-white shadow-xl ring-2 shadow-black/40 backdrop-blur-md transition hover:ring-gold-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-300 disabled:cursor-wait lg:py-2 lg:pr-4 lg:pl-2"
    >
        {{-- Aide proposée mais bulle en attente d'une place libre : repère doré sur le bouton. --}}
        <span x-show="bubble && covering" x-cloak class="absolute -top-0.5 -right-0.5 size-3.5 rounded-full bg-gold-500 ring-2 ring-night-950" aria-hidden="true"></span>
        <span class="relative grid size-10 shrink-0 place-items-center rounded-full bg-electric-500/25 text-electric-200 ring-1 ring-electric-300/50 lg:size-8">
            <flux:icon.microphone x-show="! loading" class="size-5 lg:size-4.5" />
            <svg x-show="loading" x-cloak class="size-4.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        </span>
        <span class="sr-only lg:not-sr-only" x-text="loading ? 'Connexion…' : 'Parler avec notre assistante'">Parler avec notre assistante</span>
    </button>
</div>
