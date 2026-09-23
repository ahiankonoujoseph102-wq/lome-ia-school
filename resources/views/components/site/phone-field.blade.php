@props([
    'dialCode' => null,
    'number' => null,
    'id' => 'champ-phone',
    'showErrors' => true,
])

@php
    use App\Support\PhoneNumber;

    $dialCode = old('phone_country', $dialCode ?? PhoneNumber::DEFAULT_DIAL_CODE);
    $number = old('phone', $number);
    $hasError = $showErrors && $errors->has('phone');
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="field-label">Numéro WhatsApp</label>
    <div class="flex gap-2">
        <label for="{{ $id }}-pays" class="sr-only">Pays</label>
        <select
            id="{{ $id }}-pays"
            name="phone_country"
            class="field-input w-auto max-w-[8.5rem] shrink-0 appearance-none bg-night-900 pr-3 text-center"
        >
            @foreach (PhoneNumber::COUNTRIES as $code => $countryLabel)
                <option value="{{ $code }}" @selected($code === $dialCode) class="bg-night-900">{{ $countryLabel }}</option>
            @endforeach
        </select>
        <input
            id="{{ $id }}"
            name="phone"
            type="tel"
            inputmode="tel"
            autocomplete="tel-national"
            value="{{ $number }}"
            placeholder="90 12 34 56"
            required
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-erreur" @endif
            {{ $attributes->class(['field-input min-w-0 flex-1', 'field-input-error' => $hasError]) }}
        >
    </div>
    @if ($hasError)
        <p id="{{ $id }}-erreur" class="text-sm text-rose-300">{{ $errors->first('phone') }}</p>
    @else
        <p class="text-xs text-slate-500">Autre pays : tapez le numéro complet en commençant par +.</p>
    @endif
</div>
