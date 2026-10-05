{{-- Liste de questions / réponses modifiable (FAQ) : ajouter, retirer, monter, descendre. --}}
{{-- Libellés modifiables pour d'autres paires titre / texte (services de la page de Joseph, par exemple). --}}
@props(['name', 'label', 'items' => [], 'questionLabel' => 'Question', 'answerLabel' => 'Réponse', 'addLabel' => 'Ajouter une question'])

@php
    $values = array_values(array_map(fn ($item) => ['question' => (string) ($item['question'] ?? ''), 'answer' => (string) ($item['answer'] ?? '')], (array) old($name, $items)));
@endphp

<fieldset class="space-y-3" x-data="{ items: @js($values), move(index, step) { const target = index + step; if (target < 0 || target >= this.items.length) return; [this.items[index], this.items[target]] = [this.items[target], this.items[index]]; } }">
    <legend class="field-label">{{ $label }}</legend>
    <template x-for="(item, index) in items" :key="index">
        <div class="space-y-2 rounded-2xl bg-veil/5 p-3 ring-1 ring-veil/10">
            <input type="text" class="field-input" :name="`{{ $name }}[${index}][question]`" x-model="item.question" placeholder="{{ $questionLabel }}" :aria-label="'{{ $questionLabel }} ' + (index + 1)">
            <textarea rows="4" class="field-input" :name="`{{ $name }}[${index}][answer]`" x-model="item.answer" placeholder="{{ $answerLabel }}" :aria-label="'{{ $answerLabel }} ' + (index + 1)"></textarea>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-full px-3 py-1.5 text-sm text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @click="move(index, -1)" :disabled="index === 0">Monter</button>
                <button type="button" class="rounded-full px-3 py-1.5 text-sm text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10 disabled:opacity-30" @click="move(index, 1)" :disabled="index === items.length - 1">Descendre</button>
                <button type="button" class="ml-auto rounded-full px-3 py-1.5 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/40 hover:bg-rose-400/10" @click="items.splice(index, 1)">Retirer</button>
            </div>
        </div>
    </template>
    <button type="button" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10" @click="items.push({ question: '', answer: '' })">
        <flux:icon.plus class="size-4" /> {{ $addLabel }}
    </button>
</fieldset>
