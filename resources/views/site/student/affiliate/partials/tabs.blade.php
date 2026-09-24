@php
    $tabs = [
        ['route' => 'student.affiliate.index', 'label' => 'Tableau de bord'],
        ['route' => 'student.affiliate.commissions', 'label' => 'Commissions'],
        ['route' => 'student.affiliate.withdrawals', 'label' => 'Retraits'],
    ];
@endphp

<nav class="-mx-4 mt-5 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0" aria-label="Affiliation">
    @foreach ($tabs as $tab)
        <a
            href="{{ route($tab['route']) }}"
            @class([
                'shrink-0 rounded-full px-4 py-2 text-sm font-semibold ring-1 transition',
                'bg-gold-400 text-night-950 ring-gold-400' => request()->routeIs($tab['route']),
                'text-slate-300 ring-veil/15 hover:bg-veil/5 hover:text-white' => ! request()->routeIs($tab['route']),
            ])
            @if (request()->routeIs($tab['route'])) aria-current="page" @endif
        >{{ $tab['label'] }}</a>
    @endforeach
</nav>

@if ($affiliate->isSuspended())
    <x-site.alert type="error" class="mt-5">
        Votre compte affilié est suspendu{{ $affiliate->suspension_reason ? ' : '.$affiliate->suspension_reason : '' }}. Vos liens ne sont plus actifs et les retraits ne sont pas possibles. Écrivez-nous pour en savoir plus.
    </x-site.alert>
@endif
