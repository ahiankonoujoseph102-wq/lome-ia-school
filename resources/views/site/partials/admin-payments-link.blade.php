{{-- Lien admin « Paiements à vérifier » avec le nombre de paiements en attente. --}}
@php
    $toVerify = once(fn (): int => \App\Models\Purchase::query()->awaitingVerification()->count());
@endphp

<a href="{{ route('admin.payments.index') }}" class="flex items-center gap-2 text-slate-200 hover:bg-white/5 {{ $class ?? '' }}">
    <flux:icon.banknotes class="size-4" /> Paiements à vérifier
    <span @class([
        'ml-auto min-w-6 rounded-full px-2 py-0.5 text-center text-xs font-bold',
        'bg-gold-400 text-night-950' => $toVerify > 0,
        'bg-white/10 text-slate-400' => $toVerify === 0,
    ]) aria-label="{{ $toVerify }} à vérifier">{{ $toVerify }}</span>
</a>
