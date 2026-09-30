{{--
    Assistante IA « Ama », sur les pages de App\Support\VoiceAssistant::PAGES (et le lecteur de cours si l'admin l'a
    choisi). Contexte de la page sans donnée personnelle et bulle « Vous semblez bloqué ? » (une fois par page et par
    visite) : voir le composant « voiceAssistant » de site.js et resources/js/ama.js.
    - Ama (Gemini, par défaut) : fenêtre de discussion de la page (écrit par défaut, micro et lecture à voix haute
      du navigateur s'il les gère). Les messages passent par le serveur du site, qui seul appelle Gemini.
    - Agent ElevenLabs (au choix dans l'admin) : aucun script d'ElevenLabs dans la page, il n'est chargé qu'au toucher du
      bouton, puis l'élément officiel <elevenlabs-convai> s'ouvre à la place du bouton.
    Style stable face au mode sombre forcé de Chrome : fonds foncés ou translucides, textes clairs, contours en ombre (ring).
    Sur téléphone et tablette : bouton rond compact en bas à droite (le bouton WhatsApp est alors dans l'en-tête), pour
    ne cacher ni le prix ni les grands boutons pleine largeur (« Acheter maintenant », « Envoyer la capture de mon
    paiement »…) ; au-dessus de la barre d'achat (« has-sticky-cta ») et de la barre de l'espace élève (« has-bottom-nav »).
    Sur ordinateur : bouton avec son texte en bas à gauche (le bouton WhatsApp flottant est à droite).
    La fenêtre d'Ama couvre le bas de l'écran sur téléphone (fond assombri derrière) et s'ouvre en bas à gauche sur ordinateur.
--}}
@php
    use App\Support\VoiceAssistant;
@endphp

<div x-data="voiceAssistant(@js(VoiceAssistant::browserConfig(request())))" data-test="voice-assistant">
    <div
        x-show="! opened"
        class="voice-assistant fixed right-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-40 flex max-w-[calc(100vw-2rem)] items-end gap-2 sm:right-6 lg:right-auto lg:bottom-6 lg:left-6 lg:flex-col lg:items-start"
    >
        <p x-show="error" x-cloak x-text="error" role="alert" class="max-w-[calc(100vw-6.5rem)] rounded-2xl sm:max-w-xs bg-night-900/95 px-4 py-3 text-sm text-slate-100 shadow-xl ring-1 shadow-black/40 ring-rose-400/60"></p>

        {{-- Aide proactive : ouvre seulement la fenêtre d'Ama (jamais le micro ni l'appel). Invisible tant qu'elle
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
                @if (VoiceAssistant::usesGemini())
                    <flux:icon.chat-bubble-oval-left-ellipsis x-show="! loading" class="size-5 lg:size-4.5" />
                @else
                    <flux:icon.microphone x-show="! loading" class="size-5 lg:size-4.5" />
                @endif
                <svg x-show="loading" x-cloak class="size-4.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
            </span>
            <span class="sr-only lg:not-sr-only" x-text="loading ? 'Connexion…' : 'Parler avec notre assistante'">Parler avec notre assistante</span>
        </button>
    </div>

    @if (VoiceAssistant::usesGemini())
        {{-- Fenêtre de discussion d'Ama (Gemini). --}}
        <div
            x-show="opened"
            x-cloak
            @keydown.escape.window="opened && closeChat()"
            class="ama-chat fixed inset-0 z-50 flex items-end lg:inset-auto lg:bottom-6 lg:left-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="ama-chat-title"
            data-test="ama-chat"
        >
            <div class="absolute inset-0 bg-night-950/75 lg:hidden" @click="closeChat()" aria-hidden="true"></div>

            <section class="relative flex h-[min(88dvh,46rem)] w-full flex-col overflow-hidden rounded-t-3xl bg-night-900 shadow-2xl ring-1 shadow-black/60 ring-night-600 lg:h-[min(38rem,calc(100dvh-3rem))] lg:w-[24rem] lg:rounded-3xl">
                <header class="flex items-center gap-3 px-4 py-3 shadow-[inset_0_-1px_0_0_var(--color-night-700)]">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-electric-500/25 text-base font-bold text-electric-100 ring-1 ring-electric-300/50" aria-hidden="true">A</span>
                    <div class="min-w-0 flex-1">
                        <h2 id="ama-chat-title" class="font-display text-base leading-tight font-bold text-white">Ama</h2>
                        <p class="truncate text-xs text-slate-400">Assistante IA de Lomé IA School</p>
                    </div>
                    <button type="button" @click="closeChat()" class="grid size-9 shrink-0 place-items-center rounded-full text-slate-300 ring-1 ring-slate-600 hover:bg-night-700 hover:text-white" aria-label="Fermer la discussion">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </header>

                {{-- Lecture à voix haute (synthèse vocale du navigateur) : seulement si le navigateur la gère ;
                     activée automatiquement quand le visiteur utilise le micro. --}}
                <button
                    type="button"
                    x-show="canSpeak"
                    @click="setSpeak(! speakReplies)"
                    role="switch"
                    :aria-checked="speakReplies.toString()"
                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-slate-200 shadow-[inset_0_-1px_0_0_var(--color-night-700)] transition hover:bg-night-800"
                    data-test="ama-read-aloud"
                >
                    <flux:icon.speaker-wave x-show="speakReplies" class="size-4.5 text-electric-200" />
                    <flux:icon.speaker-x-mark x-show="! speakReplies" class="size-4.5 text-slate-400" />
                    <span class="flex-1">Lire les réponses à voix haute</span>
                    <span :class="speakReplies ? 'bg-electric-500/40 ring-electric-300' : 'bg-night-700 ring-slate-600'" class="relative h-6 w-10 shrink-0 rounded-full ring-1 transition" aria-hidden="true">
                        <span :class="speakReplies ? 'translate-x-4 bg-electric-100' : 'bg-slate-400'" class="absolute top-1 left-1 size-4 rounded-full transition"></span>
                    </span>
                </button>

                <div x-ref="messages" class="flex-1 space-y-3 overflow-y-auto overscroll-contain px-4 py-4" aria-live="polite">
                    <template x-for="(message, index) in messages" :key="index">
                        <div :class="message.from === 'visitor' ? 'flex justify-end' : 'flex justify-start'">
                            <div
                                :class="message.from === 'visitor'
                                    ? 'rounded-br-md bg-electric-500/25 text-white ring-electric-300/40'
                                    : 'rounded-bl-md bg-night-800 text-slate-100 ring-night-600'"
                                class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-[15px] leading-relaxed ring-1"
                            >
                                <p x-text="message.text" class="whitespace-pre-line"></p>
                                <a
                                    x-show="message.fallback"
                                    href="{{ \App\Support\WhatsApp::url() }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-3 flex items-center justify-center gap-2 rounded-xl bg-emerald-500/15 px-4 py-2.5 text-sm font-semibold text-emerald-100 ring-1 ring-emerald-400/60 transition hover:bg-emerald-500/25"
                                    data-test="ama-whatsapp"
                                >
                                    <x-site.whatsapp-icon class="size-4.5" />
                                    Écrivez-nous sur WhatsApp
                                </a>
                            </div>
                        </div>
                    </template>

                    <div x-show="sending" class="flex justify-start" role="status">
                        <div class="flex items-center gap-2 rounded-2xl rounded-bl-md bg-night-800 px-3.5 py-3 text-sm text-slate-300 ring-1 ring-night-600">
                            <span class="flex gap-1" aria-hidden="true">
                                <span class="size-1.5 animate-bounce rounded-full bg-electric-300"></span>
                                <span class="size-1.5 animate-bounce rounded-full bg-electric-300 [animation-delay:150ms]"></span>
                                <span class="size-1.5 animate-bounce rounded-full bg-electric-300 [animation-delay:300ms]"></span>
                            </span>
                            Ama écrit…
                        </div>
                    </div>

                    {{-- Questions proposées tant que le visiteur n'a rien écrit. --}}
                    <div x-show="! visitorHasWritten && ! sending" class="flex flex-wrap gap-2 pt-1">
                        <template x-for="suggestion in suggestions" :key="suggestion">
                            <button type="button" @click="send(suggestion)" x-text="suggestion" class="rounded-full bg-electric-500/10 px-3.5 py-2 text-left text-sm text-electric-100 ring-1 ring-electric-300/40 transition hover:bg-electric-500/20"></button>
                        </template>
                    </div>
                </div>

                <footer class="px-3 shadow-[inset_0_1px_0_0_var(--color-night-700)] pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <p x-show="notice" x-text="notice" role="alert" class="mb-2 rounded-xl bg-amber-500/10 px-3 py-2 text-sm text-amber-100 ring-1 ring-amber-400/40"></p>
                    <form @submit.prevent="send()" class="flex items-end gap-2">
                        <label for="ama-input" class="sr-only">Votre message pour Ama</label>
                        <textarea
                            id="ama-input"
                            x-ref="input"
                            x-model="draft"
                            @keydown.enter="enter($event)"
                            rows="1"
                            maxlength="{{ VoiceAssistant::limit('max_length') }}"
                            :placeholder="listening ? 'Je vous écoute…' : 'Écrivez votre question…'"
                            class="field-sizing-content max-h-32 min-h-11 min-w-0 flex-1 resize-none rounded-2xl bg-night-950 px-4 py-2.5 text-[16px] text-white ring-1 ring-slate-600 placeholder:text-slate-400 focus:ring-2 focus:ring-electric-400 focus:outline-none"
                        ></textarea>
                        <button
                            type="button"
                            x-show="canListen"
                            @click="listen()"
                            :aria-pressed="listening.toString()"
                            :aria-label="listening ? 'Arrêter le micro' : 'Parler au micro'"
                            :class="listening ? 'bg-rose-500/20 text-rose-100 ring-rose-400 animate-pulse' : 'bg-night-800 text-slate-200 ring-slate-600 hover:bg-night-700'"
                            class="grid size-11 shrink-0 place-items-center rounded-full ring-1 transition"
                            data-test="ama-microphone"
                        >
                            <flux:icon.microphone x-show="! listening" class="size-5" />
                            <flux:icon.stop x-show="listening" class="size-5" />
                        </button>
                        <button
                            type="submit"
                            :disabled="sending || draft.trim() === ''"
                            class="grid size-11 shrink-0 place-items-center rounded-full bg-electric-600 text-white shadow-lg shadow-electric-900/40 transition hover:bg-electric-500 disabled:opacity-50"
                            aria-label="Envoyer"
                        >
                            <flux:icon.paper-airplane class="size-5" />
                        </button>
                    </form>
                    <p class="mt-2 text-center text-[11px] leading-snug text-slate-400">Ama est une IA et peut se tromper. Ne partagez jamais de code secret ni de mot de passe.</p>
                </footer>
            </section>
        </div>
    @endif
</div>
