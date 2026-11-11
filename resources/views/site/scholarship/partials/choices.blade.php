{{-- Choix en gros boutons (un seul possible) : $name, $label, $options [valeur => libellé], $disabled [valeur => remarque], $columns,
     $legendClass (« sr-only » quand la question est déjà écrite en grand, comme dans le formulaire pas à pas). --}}
@php
    $disabled ??= [];
    $columns ??= 'sm:grid-cols-2';
    $legendClass ??= 'field-label';
@endphp
<fieldset class="min-w-0 space-y-2" data-test="choice-{{ $name }}">
    <legend class="{{ $legendClass }}">{{ $label }}</legend>
    <div class="grid gap-2 {{ $columns }}">
        @foreach ($options as $value => $text)
            <label @class([
                'group flex min-h-14 items-center gap-3 rounded-2xl bg-veil/5 px-4 py-3 text-base font-semibold ring-1 ring-veil/15 transition has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-gold-300',
                'cursor-pointer text-slate-200 hover:bg-veil/10 has-[:checked]:bg-gold-400/10 has-[:checked]:text-white has-[:checked]:ring-2 has-[:checked]:ring-gold-400' => ! isset($disabled[$value]),
                'cursor-not-allowed text-slate-400' => isset($disabled[$value]),
            ])>
                <input type="radio" name="{{ $name }}" value="{{ $value }}" class="sr-only" required @checked(old($name) === (string) $value) @disabled(isset($disabled[$value]))>
                <span class="grid size-5 shrink-0 place-items-center rounded-full ring-1 ring-veil/30 group-has-[:checked]:ring-gold-400">
                    <flux:icon.check class="size-3.5 text-gold-300 opacity-0 group-has-[:checked]:opacity-100" />
                </span>
                <span>{{ $text }}@isset($disabled[$value]) <span class="block text-xs font-normal">{{ $disabled[$value] }}</span>@endisset</span>
            </label>
        @endforeach
    </div>
</fieldset>
