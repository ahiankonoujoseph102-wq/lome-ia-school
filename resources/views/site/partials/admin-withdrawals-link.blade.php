{{-- Lien admin « Retraits à payer » (affiliation) avec le nombre de demandes en cours. --}}
@php
    $toPay = once(fn (): int => \App\Models\AffiliateWithdrawal::query()->where('status', \App\Enums\WithdrawalStatus::Requested)->count());
@endphp

<a href="{{ route('admin.affiliates.withdrawals') }}" class="flex items-center gap-2 text-slate-200 hover:bg-white/5 {{ $class ?? '' }}">
    <flux:icon.arrow-up-tray class="size-4" /> Retraits à payer
    <span @class([
        'ml-auto min-w-6 rounded-full px-2 py-0.5 text-center text-xs font-bold',
        'bg-gold-400 text-night-950' => $toPay > 0,
        'bg-white/10 text-slate-400' => $toPay === 0,
    ]) aria-label="{{ $toPay }} à payer">{{ $toPay }}</span>
</a>
