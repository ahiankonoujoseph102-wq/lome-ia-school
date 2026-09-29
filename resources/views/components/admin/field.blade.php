{{--
    Champ de formulaire de l'admin. « name » peut être un nom de tableau (contact[email]) : l'erreur
    correspondante (contact.email) est affichée. Types : text, email, url, number, date, datetime-local,
    textarea, select (options = [valeur => libellé]), file.
    Un champ « url » est un champ texte avec le clavier des liens : le navigateur ne le refuse jamais lui-même
    (lien sans https://, texte autour du lien), c'est le serveur qui le nettoie (App\Support\PastedLink) puis le vérifie.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'id' => null,
    'options' => [],
    'unit' => null,
    'bag' => 'default',
    'rows' => 4,
    'placeholder' => null,
])

@php
    $key = trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    $id ??= 'champ-'.str_replace('.', '-', $key);
    $errorBag = $errors->getBag($bag);
    $hasError = $errorBag->has($key);
    $current = old($key, $value);
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    <div @class(['flex items-center gap-2' => $unit])>
        @if ($type === 'textarea')
            <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif {{ $attributes->class(['field-input', 'field-input-error' => $hasError]) }}>{{ $current }}</textarea>
        @elseif ($type === 'select')
            <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->class(['field-input', 'field-input-error' => $hasError]) }}>
                @foreach ($options as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'file')
            <input id="{{ $id }}" name="{{ $name }}" type="file" {{ $attributes->class(['field-input file:mr-3 file:rounded-full file:border-0 file:bg-veil/10 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white', 'field-input-error' => $hasError]) }}>
        @else
            <input id="{{ $id }}" name="{{ $name }}" type="{{ $type === 'url' ? 'text' : $type }}" value="{{ $current }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif @if ($type === 'number') inputmode="numeric" @endif @if ($type === 'url') inputmode="url" autocapitalize="none" autocorrect="off" spellcheck="false" @endif {{ $attributes->class(['field-input', 'field-input-error' => $hasError]) }}>
        @endif
        @if ($unit)
            <span class="shrink-0 text-sm text-slate-400">{{ $unit }}</span>
        @endif
    </div>
    @if ($hasError)
        <p class="text-sm text-rose-300">{{ $errorBag->first($key) }}</p>
    @elseif ($hint)
        <p class="text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
