{{--
    « Transmettre au fondateur » (App\Assistant\FounderHandoff) : carte de confirmation dans la discussion d'Ama.
    - Récapitulatif prêt : prénom, moyen de réponse, question, avec « Envoyer à Coach Joseph » et « Corriger ».
    - « Corriger », fiche incomplète (fin d'un appel) ou relève (limite atteinte, panne) : mêmes champs à remplir.
    La demande ne part qu'au toucher de « Envoyer à Coach Joseph » (sendHandoff du composant voiceAssistant).
    Style stable face au mode sombre forcé : fond foncé, textes clairs, contour doré en ombre (ring), aucun texte foncé
    sur fond clair.
--}}
<template x-if="handoff">
    <section class="rounded-2xl bg-night-800 p-4 shadow-lg ring-1 ring-gold-500/70" aria-labelledby="ama-handoff-title" data-test="ama-handoff">
        <div class="flex items-center gap-2.5">
            <span class="grid size-9 shrink-0 place-items-center rounded-full bg-gold-500/15 text-gold-200 ring-1 ring-gold-500/60" aria-hidden="true">
                <flux:icon.paper-airplane class="size-4.5" />
            </span>
            <h3 id="ama-handoff-title" class="font-display text-base leading-tight font-bold text-white">Transmettre à Coach Joseph</h3>
        </div>

        {{-- Récapitulatif (lecture seule). --}}
        <div x-show="! handoff.editing" class="mt-3 space-y-2.5 text-[15px]" data-test="ama-handoff-summary">
            <dl class="space-y-2">
                <div>
                    <dt class="text-xs text-slate-400">Prénom</dt>
                    <dd x-text="handoff.first_name" class="font-semibold text-white"></dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Réponse</dt>
                    <dd class="font-semibold break-words text-white" x-text="(handoff.channel === 'email' ? 'Par e-mail : ' : 'Sur WhatsApp : ') + handoff.contact"></dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-400">Question</dt>
                    <dd x-text="handoff.question" class="leading-relaxed whitespace-pre-line text-slate-100"></dd>
                </div>
            </dl>
        </div>

        {{-- Champs (« Corriger », fin d'un appel, relève). --}}
        <form x-show="handoff.editing" @submit.prevent="sendHandoff()" class="mt-3 space-y-3" novalidate data-test="ama-handoff-form">
            <p class="text-sm leading-snug text-slate-300">Coach Joseph vous répondra personnellement. Ne donnez jamais de code, de mot de passe ni d'information de paiement.</p>

            <div>
                <label for="ama-handoff-name" class="text-sm font-semibold text-slate-100">Votre prénom</label>
                <input id="ama-handoff-name" type="text" x-model="handoff.first_name" maxlength="60" autocomplete="given-name" autocapitalize="words" class="mt-1 w-full rounded-xl bg-night-950 px-3 py-2.5 text-[16px] text-white ring-1 ring-slate-600 placeholder:text-slate-400 focus:ring-2 focus:ring-electric-400 focus:outline-none" placeholder="Exemple : Awa">
                <p x-show="handoff.errors.first_name" x-text="handoff.errors.first_name" class="mt-1 text-sm text-rose-200"></p>
            </div>

            <fieldset>
                <legend class="text-sm font-semibold text-slate-100">Recevoir la réponse</legend>
                <div class="mt-1.5 grid grid-cols-2 gap-2">
                    <button type="button" @click="handoff.channel = 'email'; handoff.errors.contact = ''" :aria-pressed="(handoff.channel === 'email').toString()" :class="handoff.channel === 'email' ? 'bg-gold-500/15 text-gold-100 ring-2 ring-gold-500' : 'bg-night-900 text-slate-200 ring-1 ring-slate-600 hover:bg-night-700'" class="flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold transition" data-test="ama-handoff-email">
                        <flux:icon.envelope class="size-4.5" /> Par e-mail
                    </button>
                    <button type="button" @click="handoff.channel = 'whatsapp'; handoff.errors.contact = ''" :aria-pressed="(handoff.channel === 'whatsapp').toString()" :class="handoff.channel === 'whatsapp' ? 'bg-gold-500/15 text-gold-100 ring-2 ring-gold-500' : 'bg-night-900 text-slate-200 ring-1 ring-slate-600 hover:bg-night-700'" class="flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold transition" data-test="ama-handoff-whatsapp">
                        <x-site.whatsapp-icon class="size-4.5" /> Sur WhatsApp
                    </button>
                </div>
                <p x-show="handoff.errors.channel" x-text="handoff.errors.channel" class="mt-1 text-sm text-rose-200"></p>
            </fieldset>

            <div x-show="handoff.channel !== ''">
                <label for="ama-handoff-contact" class="text-sm font-semibold text-slate-100" x-text="handoff.channel === 'email' ? 'Votre adresse e-mail' : 'Votre numéro WhatsApp, avec l\'indicatif du pays'"></label>
                <input
                    id="ama-handoff-contact"
                    type="text"
                    x-model="handoff.contact"
                    :inputmode="handoff.channel === 'email' ? 'email' : 'tel'"
                    :autocomplete="handoff.channel === 'email' ? 'email' : 'tel'"
                    :placeholder="handoff.channel === 'email' ? 'prenom@gmail.com' : '+228 91 23 45 67'"
                    autocapitalize="none"
                    autocorrect="off"
                    spellcheck="false"
                    maxlength="191"
                    class="mt-1 w-full rounded-xl bg-night-950 px-3 py-2.5 text-[16px] text-white ring-1 ring-slate-600 placeholder:text-slate-400 focus:ring-2 focus:ring-electric-400 focus:outline-none"
                    data-test="ama-handoff-contact"
                >
                <p x-show="handoff.errors.contact" x-text="handoff.errors.contact" class="mt-1 text-sm text-rose-200"></p>
            </div>

            <div>
                <label for="ama-handoff-question" class="text-sm font-semibold text-slate-100">Votre question</label>
                <textarea id="ama-handoff-question" x-model="handoff.question" rows="3" maxlength="1000" class="field-sizing-content mt-1 max-h-40 min-h-20 w-full resize-none rounded-xl bg-night-950 px-3 py-2.5 text-[16px] text-white ring-1 ring-slate-600 placeholder:text-slate-400 focus:ring-2 focus:ring-electric-400 focus:outline-none"></textarea>
                <p x-show="handoff.errors.question" x-text="handoff.errors.question" class="mt-1 text-sm text-rose-200"></p>
            </div>

            {{-- Champ piège : invisible pour les personnes, rempli par les robots. --}}
            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label for="ama-handoff-website">Ne pas remplir ce champ</label>
                <input id="ama-handoff-website" type="text" x-model="handoff.website" tabindex="-1" autocomplete="off">
            </div>
        </form>

        <p x-show="handoff.errors.form" x-text="handoff.errors.form" role="alert" class="mt-3 text-sm text-rose-200"></p>

        <div class="mt-4 flex flex-col gap-2">
            <button
                type="button"
                @click="sendHandoff()"
                :disabled="handoff.sending"
                :aria-busy="handoff.sending"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-electric-600 px-4 py-3 text-[15px] font-bold text-white shadow-lg shadow-electric-900/40 transition hover:bg-electric-500 disabled:cursor-wait disabled:opacity-60"
                data-test="ama-handoff-send"
            >
                <flux:icon.paper-airplane x-show="! handoff.sending" class="size-4.5" />
                <svg x-show="handoff.sending" x-cloak class="size-4.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                <span x-text="handoff.sending ? 'Envoi…' : 'Envoyer à Coach Joseph'">Envoyer à Coach Joseph</span>
            </button>
            <button
                type="button"
                x-show="! handoff.editing"
                @click="correctHandoff()"
                class="rounded-xl px-4 py-3 text-[15px] font-semibold text-slate-100 ring-1 ring-slate-600 transition hover:bg-night-700"
                data-test="ama-handoff-correct"
            >Corriger</button>
            <button
                type="button"
                x-show="handoff.editing"
                @click="cancelHandoff()"
                class="rounded-xl px-4 py-3 text-[15px] font-semibold text-slate-300 ring-1 ring-slate-600 transition hover:bg-night-700"
                data-test="ama-handoff-cancel"
            >Annuler</button>
        </div>
    </section>
</template>
