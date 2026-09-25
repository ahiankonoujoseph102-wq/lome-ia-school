@php
    use App\Support\Money;
@endphp

<x-admin.page title="Codes promo" subtitle="Réduction en pourcentage ou en montant fixe. La commission d'affiliation et le seuil d'accès provisoire s'appliquent au montant réellement payé." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.promo-codes.create') }}" class="btn-gold"><flux:icon.plus class="size-5" /> Nouveau code</a>
    </x-slot:actions>

    @error('promo')
        <x-site.alert type="error">{{ $message }}</x-site.alert>
    @enderror

    <ul class="space-y-3">
        @forelse ($codes as $promo)
            <li class="card space-y-3 p-4 sm:p-5" data-test="promo-row">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-lg bg-veil/10 px-2.5 py-1 font-mono text-base font-bold tracking-wider text-white">{{ $promo->code }}</span>
                    <span class="font-semibold text-gold-200">{{ $promo->valueLabel() }}</span>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-semibold ring-1',
                        'text-emerald-200 ring-emerald-400/50' => $promo->stateLabel() === 'Actif',
                        'text-sky-200 ring-sky-400/50' => $promo->stateLabel() === 'Programmé',
                        'text-slate-400 ring-veil/20' => in_array($promo->stateLabel(), ['Inactif', 'Expiré'], true),
                    ])>{{ $promo->stateLabel() }}</span>
                </div>
                @if ($promo->description)
                    <p class="text-sm text-slate-300">{{ $promo->description }}</p>
                @endif
                <p class="text-sm text-slate-400">
                    {{ $promo->all_formations ? 'Toutes les formations' : $promo->formations->pluck('title')->implode(', ') }}
                    @if ($promo->starts_at || $promo->ends_at)
                        · {{ $promo->starts_at ? 'du '.$promo->starts_at->translatedFormat('j M Y H\hi') : '' }} {{ $promo->ends_at ? 'au '.$promo->ends_at->translatedFormat('j M Y H\hi') : '' }}
                    @endif
                </p>
                <dl class="grid grid-cols-3 gap-2 rounded-2xl bg-veil/5 p-3 text-center text-sm ring-1 ring-veil/10">
                    <div><dt class="text-xs text-slate-400">Utilisations</dt><dd class="font-bold text-white">{{ $promo->uses_count }}{{ $promo->max_uses ? ' / '.$promo->max_uses : '' }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Chiffre d'affaires</dt><dd class="font-bold text-white">{{ Money::fcfa((int) $promo->revenue) }}</dd></div>
                    <div><dt class="text-xs text-slate-400">Remises</dt><dd class="font-bold text-white">{{ Money::fcfa((int) $promo->discounts) }}</dd></div>
                </dl>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.promo-codes.edit', $promo) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10">Modifier</a>
                    <form method="POST" action="{{ route('admin.promo-codes.toggle', $promo) }}">@csrf<button class="rounded-full px-3 py-2 text-sm font-semibold text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10">{{ $promo->is_active ? 'Désactiver' : 'Activer' }}</button></form>
                    <a href="{{ route('admin.sales.index', ['code' => $promo->id]) }}" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10">Voir les ventes</a>
                </div>
            </li>
        @empty
            <li class="card p-6 text-center text-slate-400">Aucun code promo pour le moment.</li>
        @endforelse
    </ul>
    <p class="text-xs text-slate-500">Chiffre d'affaires et remises : paiements validés uniquement. Utilisations : paiements envoyés, sauf refusés ou remboursés.</p>
</x-admin.page>
