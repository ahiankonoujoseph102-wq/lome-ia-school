<x-layouts::site title="Mes achats" variant="student">
    <div class="mx-auto max-w-3xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">Mes achats</h1>
        <p class="mt-1 text-slate-400">Suivez vos paiements et retrouvez vos reçus.</p>

        <div class="mt-6 space-y-4">
            @forelse ($purchases as $purchase)
                <article class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">{{ $purchase->created_at?->translatedFormat('j F Y à H\hi') }} · {{ $purchase->receipt_number }}</p>
                            <h2 class="mt-1 font-semibold text-white">{{ $purchase->item_label }}</h2>
                            <p class="mt-1 text-sm text-slate-400">{{ $purchase->formatted_amount }} · {{ $purchase->payment_method_name }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                    </div>
                    <p class="mt-3 text-sm text-slate-300">@include('site.student.purchases.status-message')</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('student.purchases.show', $purchase) }}" class="btn-ghost !px-4 !py-2 !text-sm">Voir le détail</a>
                        @if ($purchase->status === \App\Enums\PurchaseStatus::Validated)
                            <a href="{{ route('student.purchases.receipt', $purchase) }}" class="btn-gold !px-4 !py-2 !text-sm"><flux:icon.document-text class="size-4" /> Reçu</a>
                        @endif
                        @if ($purchase->status->grantsAccess())
                            <a href="{{ $purchase->isSessionSupplement() && $purchase->liveSession ? route('student.sessions.show', $purchase->liveSession) : route('student.formations.show', $purchase->formation) }}" class="btn-ghost !px-4 !py-2 !text-sm">{{ $purchase->isSessionSupplement() ? 'Voir la session' : 'Ouvrir la formation' }}</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="card p-8 text-center">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.receipt-percent class="size-7" /></span>
                    <p class="mt-4 font-semibold text-white">Vous n'avez encore aucun achat.</p>
                    <a href="{{ route('formations.index') }}" class="btn-gold mt-5">Voir les formations</a>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts::site>
