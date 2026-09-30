{{-- Bouton « Copier » de la page de paiement (composant Alpine mobileMoneyPayment). --}}
@php $small ??= false; @endphp
<button type="button" @click="copy(@js($value), @js($id))"
    @class([
        'inline-flex shrink-0 items-center gap-1.5 rounded-full bg-veil/5 font-semibold text-white ring-1 ring-veil/20 transition hover:bg-veil/10 active:scale-[0.98]',
        'px-3 py-2 text-xs' => $small,
        'px-4 py-2.5 text-sm' => ! $small,
    ])
    aria-label="{{ $label }} : {{ $value }}">
    <flux:icon.clipboard-document class="size-4" x-show="copied !== @js($id)" />
    <flux:icon.check class="size-4 text-emerald-300" x-show="copied === @js($id)" x-cloak />
    <span x-text="copied === @js($id) ? 'Copié' : @js($label)">{{ $label }}</span>
</button>
