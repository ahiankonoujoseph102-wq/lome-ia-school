{{--
    Liste de textes modifiable sur téléphone : ajouter, retirer, monter, descendre.
    Envoie un tableau « name[] ». Les lignes vides sont ignorées à l'enregistrement.
--}}
@props([
    'name',
    'label',
    'items' => [],
    'hint' => null,
    'addLabel' => 'Ajouter une ligne',
    'multiline' => false,
    'placeholder' => '',
])

@php
    $key = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $values = array_values(array_map('strval', (array) old($key, $items)));
@endphp

<fieldset class="space-y-2" x-data="{ items: @js($values), add() { this.items.push(''); this.$nextTick(() => this.$refs.list.querySelector('li:last-child :is(input, textarea)')?.focus()); }, move(index, step) { const target = index + step; if (target < 0 || target >= this.items.length) return; [this.items[index], this.items[target]] = [this.items[target], this.items[index]]; } }">
    <legend class="field-label">{{ $label }}</legend>
    @if ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
    <ul class="space-y-2" x-ref="list">
        <template x-for="(item, index) in items" :key="index">
            <li class="flex items-start gap-2">
                @if ($multiline)
                    <textarea name="{{ $name }}[]" x-model="items[index]" rows="3" class="field-input min-w-0 flex-1" placeholder="{{ $placeholder }}" :aria-label="'{{ $label }}, ligne ' + (index + 1)"></textarea>
                @else
                    <input type="text" name="{{ $name }}[]" x-model="items[index]" class="field-input min-w-0 flex-1" placeholder="{{ $placeholder }}" :aria-label="'{{ $label }}, ligne ' + (index + 1)">
                @endif
                <div class="flex shrink-0 flex-col gap-1">
                    <button type="button" class="grid size-8 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @click="move(index, -1)" :disabled="index === 0" aria-label="Monter"><flux:icon.chevron-up class="size-4" /></button>
                    <button type="button" class="grid size-8 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @click="move(index, 1)" :disabled="index === items.length - 1" aria-label="Descendre"><flux:icon.chevron-down class="size-4" /></button>
                </div>
                <button type="button" class="grid size-8 shrink-0 place-items-center rounded-full text-rose-200 ring-1 ring-rose-400/40 hover:bg-rose-400/10" @click="items.splice(index, 1)" aria-label="Retirer"><flux:icon.x-mark class="size-4" /></button>
            </li>
        </template>
    </ul>
    <p class="text-sm text-slate-500" x-show="items.length === 0">Aucune ligne pour le moment.</p>
    <button type="button" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10" @click="add()">
        <flux:icon.plus class="size-4" /> {{ $addLabel }}
    </button>
    @error($key)
        <p class="text-sm text-rose-300">{{ $message }}</p>
    @enderror
</fieldset>
