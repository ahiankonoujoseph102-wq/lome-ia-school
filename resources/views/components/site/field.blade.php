@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'id' => null,
    'showErrors' => true,
])

@php
    $id ??= 'champ-'.$name;
    $hasError = $showErrors && $errors->has($name);
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ $value ?? old($name) }}" @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-erreur" @endif
        {{ $attributes->class(['field-input', 'field-input-error' => $hasError]) }}
    >
    @if ($hasError)
        <p id="{{ $id }}-erreur" class="text-sm text-rose-300">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="text-xs text-slate-400">{{ $hint }}</p>
    @endif
</div>
