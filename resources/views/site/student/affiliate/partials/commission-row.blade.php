{{-- Une commission : ce qui a été vendu, jamais d'information sur l'acheteur. --}}
<li class="flex items-start justify-between gap-3 py-3.5" data-test="commission-row">
    <div class="min-w-0">
        <p class="font-medium text-white">{{ $commission->itemLabel() }}</p>
        <p class="mt-0.5 text-xs text-slate-400">
            {{ $commission->created_at?->translatedFormat('j F Y') }}
            @if ($commission->status === \App\Enums\CommissionStatus::Pending && $commission->available_at)
                · disponible le {{ $commission->available_at->translatedFormat('j F Y') }}
            @elseif ($commission->status === \App\Enums\CommissionStatus::Cancelled)
                · {{ $commission->cancel_reason === 'purchase_refunded' ? 'achat remboursé' : 'achat refusé' }}
            @elseif (! $commission->isReversal())
                · {{ $commission->rate }} % de {{ \App\Support\Money::fcfa($commission->base_amount) }}
            @endif
        </p>
    </div>
    <div class="shrink-0 text-right">
        <p @class(['font-bold', 'text-gold-300' => $commission->amount >= 0, 'text-rose-300' => $commission->amount < 0, 'line-through opacity-60' => $commission->status === \App\Enums\CommissionStatus::Cancelled])>{{ $commission->formattedAmount() }}</p>
        <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $commission->status->badgeClasses() }}">{{ $commission->isReversal() ? 'Déduction' : $commission->status->label() }}</span>
    </div>
</li>
