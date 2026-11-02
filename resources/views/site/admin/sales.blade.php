@php
    use App\Enums\PurchaseStatus;
    use App\Support\Money;

    $validated = $totals[PurchaseStatus::Validated->value] ?? ['sales' => 0, 'revenue' => 0];
    $provisional = $totals[PurchaseStatus::Provisional->value] ?? ['sales' => 0, 'revenue' => 0];
    $pending = $totals[PurchaseStatus::Pending->value] ?? ['sales' => 0, 'revenue' => 0];
@endphp

<x-admin.page title="Toutes les ventes" subtitle="Paiements Mobile Money et accès manuels. Heures de Lomé." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.exports.download', ['type' => 'ventes'] + array_filter($filters)) }}" class="btn-ghost"><flux:icon.arrow-down-tray class="size-5" /> Exporter en CSV</a>
    </x-slot:actions>

    <form method="GET" class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.field name="from" label="Du" type="date" :value="$filters['from'] ?? ''" />
        <x-admin.field name="to" label="Au" type="date" :value="$filters['to'] ?? ''" />
        <x-admin.field name="status" label="Statut" type="select" :options="['' => 'Tous'] + $statuses" :value="$filters['status'] ?? ''" />
        <x-admin.field name="formation" label="Formation" type="select" :options="['' => 'Toutes'] + $formations" :value="$filters['formation'] ?? ''" />
        <x-admin.field name="kind" label="Type" type="select" :options="['' => 'Tous', 'formation' => 'Formations', 'supplement' => 'Suppléments de sessions']" :value="$filters['kind'] ?? ''" />
        <x-admin.field name="gateway" label="Mode" type="select" :options="['' => 'Tous'] + $gateways" :value="$filters['gateway'] ?? ''" />
        <x-admin.field name="code" label="Code promo" type="select" :options="['' => 'Tous'] + $codes" :value="$filters['code'] ?? ''" />
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-gold flex-1">Filtrer</button>
            <a href="{{ route('admin.sales.index') }}" class="btn-ghost" aria-label="Effacer les filtres"><flux:icon.x-mark class="size-5" /></a>
        </div>
    </form>

    <dl class="grid gap-3 sm:grid-cols-3">
        <div class="card p-4"><dt class="text-sm text-slate-400">Validé</dt><dd class="mt-1 text-xl font-bold text-white" data-test="validated-total">{{ Money::fcfa($validated['revenue']) }}</dd><dd class="text-xs text-slate-400">{{ $validated['sales'] }} {{ $validated['sales'] > 1 ? 'ventes' : 'vente' }}</dd></div>
        <div class="card p-4"><dt class="text-sm text-slate-400">Accès provisoire (à vérifier)</dt><dd class="mt-1 text-xl font-bold text-sky-200">{{ Money::fcfa($provisional['revenue']) }}</dd><dd class="text-xs text-slate-400">{{ $provisional['sales'] }} en attente de vérification</dd></div>
        <div class="card p-4"><dt class="text-sm text-slate-400">En attente de validation</dt><dd class="mt-1 text-xl font-bold text-gold-200">{{ Money::fcfa($pending['revenue']) }}</dd><dd class="text-xs text-slate-400">{{ $pending['sales'] }} sans accès pour le moment</dd></div>
    </dl>

    <ul class="space-y-2">
        @forelse ($purchases as $purchase)
            <li class="card p-4 text-sm" data-test="sale-row">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold text-white">{{ $purchase->item_label }}</span>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                </div>
                <p class="mt-1 text-slate-300">
                    <span class="font-semibold text-gold-200">{{ $purchase->formatted_amount }}</span>
                    @if ($purchase->discount_amount)
                        <s class="text-slate-500">{{ Money::fcfa((int) $purchase->original_amount) }}</s> · code {{ $purchase->promoCode?->code }}
                    @endif
                    · {{ $purchase->payment_method_name }}
                    @if ($purchase->isManual())
                        <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold text-electric-200 ring-1 ring-electric-400/50">Manuel</span>
                    @endif
                </p>
                <p class="mt-1 text-slate-400">
                    @if ($purchase->user)
                        <a href="{{ route('admin.students.show', $purchase->user) }}" class="font-semibold text-electric-200 hover:text-white">{{ $purchase->user->name }}</a> ·
                    @endif
                    {{ $purchase->created_at?->translatedFormat('j M Y à H\hi') }}
                    @if ($purchase->manual_reason)
                        · Motif : {{ $purchase->manual_reason }}
                    @endif
                </p>
            </li>
        @empty
            <li class="card p-6 text-center text-slate-400">Aucune vente pour ces filtres.</li>
        @endforelse
    </ul>

    {{ $purchases->links() }}
</x-admin.page>
