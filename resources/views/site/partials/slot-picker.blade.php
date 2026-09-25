{{--
    Choix du jour puis du créneau. Attend $days (ConsultationController::daysForView) et, facultatif, $selected.
    Doit être placé dans un élément x-data="slotPicker(...)" ; remplit le champ caché « starts_at ».
--}}
<input type="hidden" name="starts_at" :value="slot" value="{{ $selected ?? '' }}">

<div class="space-y-5">
    <div>
        <p class="field-label">Choisissez un jour</p>
        <div class="-mx-5 mt-2 flex snap-x gap-2 overflow-x-auto px-5 pb-2 sm:-mx-6 sm:px-6" role="listbox" aria-label="Jours disponibles">
            <template x-for="item in days" :key="item.date">
                <button
                    type="button"
                    role="option"
                    @click="chooseDay(item.date)"
                    :aria-selected="(day === item.date).toString()"
                    :class="day === item.date ? 'bg-gold-400/20 text-gold-100 ring-2 ring-gold-400' : 'bg-veil/5 text-slate-200 ring-1 ring-veil/10 hover:bg-veil/10'"
                    class="flex w-16 shrink-0 snap-start flex-col items-center rounded-2xl py-2.5 transition"
                    data-test="slot-day"
                >
                    <span class="text-[11px] font-semibold uppercase" x-text="item.weekday"></span>
                    <span class="text-xl leading-tight font-bold" x-text="item.day"></span>
                    <span class="text-[11px]" x-text="item.month"></span>
                </button>
            </template>
        </div>
    </div>

    <div x-show="current">
        <p class="field-label" x-text="current ? current.title : ''"></p>
        <p class="mt-0.5 text-xs text-slate-400">Heure de Lomé (GMT), la même qu'au Burkina Faso. Au Bénin (GMT+1), ajoutez une heure.</p>
        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4" role="listbox" aria-label="Heures disponibles">
            <template x-for="item in (current ? current.slots : [])" :key="item.value">
                <button
                    type="button"
                    role="option"
                    @click="chooseSlot(item.value)"
                    :aria-selected="(slot === item.value).toString()"
                    :class="slot === item.value ? 'bg-gold-400/20 text-gold-100 ring-2 ring-gold-400' : 'bg-veil/5 text-white ring-1 ring-veil/10 hover:bg-veil/10'"
                    class="rounded-xl py-2.5 text-sm font-semibold transition"
                    x-text="item.label"
                    data-test="slot-time"
                ></button>
            </template>
        </div>
    </div>

    <p x-show="chosen" x-cloak class="rounded-2xl bg-gold-400/10 px-4 py-3 text-sm text-gold-100 ring-1 ring-gold-400/30" data-test="slot-summary">
        <span class="font-semibold text-white" x-text="chosen ? chosen.title + ' à ' + chosen.label : ''"></span>
        <span class="block text-xs" x-text="chosen ? 'Heure de Lomé (GMT) · ' + chosen.neighbours : ''"></span>
    </p>
    @error('starts_at')
        <p class="text-sm text-rose-300">{{ $message }}</p>
    @enderror
</div>
