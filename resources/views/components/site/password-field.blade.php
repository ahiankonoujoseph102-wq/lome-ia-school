@props([
    'name' => 'password',
    'label' => 'Mot de passe',
    'id' => null,
    'hint' => null,
    'showErrors' => true,
])

@php
    $id ??= 'champ-'.$name;
    $hasError = $showErrors && $errors->has($name);
@endphp

<div class="space-y-1.5" x-data="{ visible: false }">
    <div class="flex items-center justify-between gap-3">
        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
        {{ $action ?? '' }}
    </div>
    <div class="relative">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            :type="visible ? 'text' : 'password'"
            type="password"
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-erreur" @endif
            {{ $attributes->class(['field-input pr-24', 'field-input-error' => $hasError]) }}
        >
        <button
            type="button"
            @click="visible = !visible"
            class="absolute inset-y-1.5 right-1.5 inline-flex items-center gap-1.5 rounded-xl px-3 text-sm font-medium text-slate-300 hover:bg-veil/10 hover:text-white"
            :aria-pressed="visible.toString()"
        >
            <flux:icon.eye class="size-4" x-show="!visible" />
            <flux:icon.eye-slash class="size-4" x-show="visible" x-cloak />
            <span x-text="visible ? 'Masquer' : 'Afficher'">Afficher</span>
        </button>
    </div>
    @if ($hasError)
        <p id="{{ $id }}-erreur" class="text-sm text-rose-300">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
