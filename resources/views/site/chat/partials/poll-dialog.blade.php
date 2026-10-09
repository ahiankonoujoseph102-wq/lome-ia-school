{{--
    Créer un sondage (administrateurs, interrupteur « Sondages ») : une question, de 2 à 10 choix,
    « plusieurs réponses possibles ». Et confirmation avant de clôturer un sondage.
--}}
@if (\App\Chat\ChatPolls::canCreate($viewer->user))
    <div x-show="dialog === 'poll'" x-cloak x-transition class="chat-dialog max-h-[88dvh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="titre-sondage" data-test="poll-dialog">
        <h2 id="titre-sondage" class="flex items-center gap-2 text-lg font-bold text-white">
            <flux:icon.chart-bar class="size-5 text-gold-300" /> Nouveau sondage
        </h2>

        <label for="sondage-question" class="field-label mt-4">Question</label>
        <textarea id="sondage-question" x-model="pollDraft.question" rows="2" maxlength="{{ \App\Models\ChatPoll::QUESTION_MAX_LENGTH }}" placeholder="Exemple : Quel horaire vous convient pour la prochaine session ?" class="field-input mt-1.5 !py-2.5" data-test="poll-question"></textarea>

        <p class="field-label mt-4">Choix</p>
        <div class="mt-1.5 space-y-2">
            <template x-for="(option, index) in pollDraft.options" :key="index">
                <div class="flex items-center gap-2">
                    <label class="sr-only" :for="'sondage-choix-' + index" x-text="'Choix ' + (index + 1)"></label>
                    <input :id="'sondage-choix-' + index" type="text" x-model="pollDraft.options[index]" maxlength="{{ \App\Models\ChatPoll::OPTION_MAX_LENGTH }}" :placeholder="'Choix ' + (index + 1)" class="field-input min-w-0 flex-1" data-test="poll-option-input">
                    <button type="button" x-show="pollDraft.options.length > {{ \App\Models\ChatPoll::MIN_OPTIONS }}" @click="pollDraft.options.splice(index, 1)" class="grid size-11 shrink-0 place-items-center rounded-full text-slate-400 hover:bg-veil/10 hover:text-white" :aria-label="'Retirer le choix ' + (index + 1)">
                        <flux:icon.x-mark class="size-5" />
                    </button>
                </div>
            </template>
        </div>
        <button type="button" x-show="pollDraft.options.length < {{ \App\Models\ChatPoll::MAX_OPTIONS }}" @click="pollDraft.options.push('')" class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-electric-200 hover:bg-veil/5" data-test="poll-add-option">
            <flux:icon.plus class="size-4" /> Ajouter un choix
        </button>
        <p class="text-xs text-slate-400">De {{ \App\Models\ChatPoll::MIN_OPTIONS }} à {{ \App\Models\ChatPoll::MAX_OPTIONS }} choix.</p>

        <label class="mt-4 flex min-h-11 cursor-pointer items-center gap-3 text-sm text-slate-200" data-test="poll-multiple">
            <input type="checkbox" x-model="pollDraft.multiple" class="peer sr-only">
            <span class="grid size-5 shrink-0 place-items-center rounded-md bg-night-900 ring-1 ring-slate-600 transition peer-checked:bg-gold-500/20 peer-checked:ring-gold-500 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300" aria-hidden="true">
                <flux:icon.check x-show="pollDraft.multiple" x-cloak class="size-3.5 text-white" />
            </span>
            Plusieurs réponses possibles
        </label>

        <p x-show="pollDraft.error" x-cloak x-text="pollDraft.error" class="mt-4 rounded-xl bg-rose-500/15 px-3 py-2.5 text-sm text-rose-100 ring-1 ring-rose-400/50" role="alert" data-test="poll-error"></p>

        <div class="mt-5 flex gap-3">
            <button type="button" class="btn-ghost flex-1 !py-3" @click="closeAll()">Annuler</button>
            <button type="button" class="btn flex-1 bg-electric-600 !py-3 text-white hover:bg-electric-500" :disabled="busy" @click="sendPoll()" data-test="poll-send">Publier le sondage</button>
        </div>
    </div>
@endif

<div x-show="dialog === 'poll-close'" x-cloak x-transition class="chat-dialog" role="alertdialog" aria-modal="true" aria-labelledby="titre-cloture" data-test="poll-close-dialog">
    <h2 id="titre-cloture" class="text-lg font-bold text-white">Clôturer ce sondage ?</h2>
    <p class="mt-1 text-sm text-slate-400">Les résultats restent affichés, mais plus personne ne pourra voter ni modifier son vote.</p>
    <div class="mt-5 flex gap-3">
        <button type="button" class="btn-ghost flex-1 !py-3" @click="closeAll()">Annuler</button>
        <button type="button" class="btn flex-1 bg-electric-600 !py-3 text-white hover:bg-electric-500" :disabled="busy" @click="closePoll()">Clôturer</button>
    </div>
</div>
