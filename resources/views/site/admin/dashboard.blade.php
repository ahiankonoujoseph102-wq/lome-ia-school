@php
    use App\Support\Money;

    $max = max(1, max(array_column($chart, 'revenue')));
    $chartTotal = array_sum(array_column($chart, 'revenue'));
    $todoTotal = array_sum(array_column($todo, 'count'));
@endphp

<x-admin.page title="Administration" :subtitle="'Bonjour '.auth()->user()->first_name.'. Montants en FCFA ; ventes = paiements validés.'" width="max-w-5xl">
    {{-- À traiter --}}
    <section aria-labelledby="titre-a-traiter" class="space-y-3">
        <h2 id="titre-a-traiter" class="flex items-center gap-2 font-semibold text-white">
            À traiter
            @if ($todoTotal > 0)
                <span class="rounded-full bg-gold-400/20 px-2 py-0.5 text-xs font-bold text-gold-100 ring-1 ring-gold-400/70">{{ $todoTotal }}</span>
            @endif
        </h2>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($todo as $item)
                <a href="{{ $item['route'] }}" @class([
                    'card flex items-center gap-3 p-4 hover:bg-veil/5 lg:flex-col lg:items-start',
                    'ring-1 ring-gold-400/60' => $item['count'] > 0,
                ]) data-test="todo-item">
                    <flux:icon :name="$item['icon']" @class(['size-5 shrink-0', 'text-gold-300' => $item['count'] > 0, 'text-slate-500' => $item['count'] === 0]) />
                    <span class="min-w-0 flex-1 text-sm text-slate-200">{{ $item['label'] }}</span>
                    <span @class(['text-2xl font-bold', 'text-white' => $item['count'] > 0, 'text-slate-500' => $item['count'] === 0])>{{ $item['count'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Chiffres --}}
    <section aria-labelledby="titre-chiffres" class="space-y-3">
        <h2 id="titre-chiffres" class="font-semibold text-white">Ventes</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            @foreach ($stats as $key => $stat)
                <div class="card p-4" data-test="stat-{{ $key }}">
                    <p class="text-sm text-slate-400">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold text-white">{{ Money::fcfa($stat['revenue']) }}</p>
                    <p class="text-sm text-slate-400">{{ $stat['sales'] }} {{ $stat['sales'] > 1 ? 'ventes validées' : 'vente validée' }}</p>
                    <p class="mt-2 text-xs text-slate-500">À part : {{ Money::fcfa($stat['unverified']) }} provisoire ou en attente de vérification</p>
                    <p class="text-xs text-slate-500">{{ $stat['signups'] }} {{ $stat['signups'] > 1 ? 'nouveaux inscrits' : 'nouvel inscrit' }}</p>
                </div>
            @endforeach
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="card p-4"><p class="text-sm text-slate-400">Élèves actifs sur 7 jours</p><p class="mt-1 text-2xl font-bold text-white">{{ $activeStudents }}</p><p class="text-xs text-slate-500">Au moins une leçon consultée</p></div>
            <div class="card p-4"><p class="text-sm text-slate-400">Élèves actifs sur 30 jours</p><p class="mt-1 text-2xl font-bold text-white">{{ $activeStudentsMonth }}</p><p class="text-xs text-slate-500">Au moins une leçon consultée</p></div>
        </div>
    </section>

    {{-- Graphique : chiffre d'affaires validé par jour --}}
    <section class="card space-y-4 p-5 sm:p-6" aria-labelledby="titre-graphique">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="titre-graphique" class="font-semibold text-white">Ventes validées sur 30 jours</h2>
            <p class="text-sm text-slate-400">Total : <span class="font-semibold text-white">{{ Money::fcfa($chartTotal) }}</span></p>
        </div>
        <div class="relative">
            <p class="absolute -top-1 left-0 text-xs text-slate-500">{{ Money::fcfa($max === 1 && $chartTotal === 0 ? 0 : $max) }}</p>
            <div class="flex h-40 items-end gap-0.5 border-b border-veil/20 pt-5" role="img" aria-label="Chiffre d'affaires validé par jour sur les 30 derniers jours, total {{ Money::fcfa($chartTotal) }}">
                @foreach ($chart as $day)
                    <div class="group relative flex h-full flex-1 items-end" tabindex="0" aria-label="{{ $day['date']->translatedFormat('j M') }} : {{ Money::fcfa($day['revenue']) }}">
                        <div @class(['w-full rounded-t-[4px]', 'bg-electric-400 group-hover:bg-electric-300 group-focus:bg-electric-300' => $day['revenue'] > 0]) style="height: {{ $day['revenue'] > 0 ? max(2, round($day['revenue'] * 100 / $max)) : 0 }}%"></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 rounded-xl bg-night-800 px-3 py-2 text-xs whitespace-nowrap text-slate-200 shadow-lg ring-1 ring-veil/15 group-hover:block group-focus:block">
                            <span class="block font-semibold text-white">{{ Money::fcfa($day['revenue']) }}</span>
                            {{ $day['date']->translatedFormat('l j M') }} · {{ $day['sales'] }} {{ $day['sales'] > 1 ? 'ventes' : 'vente' }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-slate-500">
                <span>{{ $chart[0]['date']->translatedFormat('j M') }}</span>
                <span>Aujourd'hui</span>
            </div>
        </div>
        <details class="text-sm">
            <summary class="cursor-pointer text-electric-200">Voir le détail jour par jour</summary>
            <table class="mt-3 w-full text-left">
                <thead><tr class="text-slate-400"><th class="py-1 font-medium">Jour</th><th class="py-1 text-right font-medium">Ventes</th><th class="py-1 text-right font-medium">Montant</th></tr></thead>
                <tbody class="divide-y divide-veil/10">
                    @foreach (array_reverse($chart) as $day)
                        <tr><td class="py-1.5 text-slate-300">{{ $day['date']->translatedFormat('D j M') }}</td><td class="py-1.5 text-right text-slate-300">{{ $day['sales'] }}</td><td class="py-1.5 text-right text-white">{{ Money::fcfa($day['revenue']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    </section>

    {{-- Formations les plus vendues --}}
    <section class="card p-5 sm:p-6" aria-labelledby="titre-top">
        <h2 id="titre-top" class="font-semibold text-white">Formations les plus vendues (30 jours)</h2>
        @if ($top === [])
            <p class="mt-3 text-sm text-slate-400">Aucune vente validée sur les 30 derniers jours.</p>
        @else
            @php $topMax = max(1, max(array_column($top, 'revenue'))); @endphp
            <ol class="mt-4 space-y-3">
                @foreach ($top as $row)
                    <li>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate text-slate-200">{{ $loop->iteration }}. {{ $row['title'] }}</span>
                            <span class="shrink-0 font-semibold text-white">{{ Money::fcfa($row['revenue']) }} <span class="font-normal text-slate-400">· {{ $row['sales'] }}</span></span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-veil/10"><div class="h-full rounded-full bg-electric-400" style="width: {{ round($row['revenue'] * 100 / $topMax) }}%"></div></div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- Menu regroupé --}}
    <section class="space-y-3" aria-labelledby="titre-menu">
        <h2 id="titre-menu" class="font-semibold text-white">Tout gérer</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (\App\Admin\AdminMenu::groups(auth()->user()) as $group)
                <div class="card p-4">
                    <p class="flex items-center gap-2 text-sm font-semibold text-white"><flux:icon :name="$group['icon']" class="size-4 text-electric-300" /> {{ $group['label'] }}</p>
                    <ul class="mt-2 space-y-1">
                        @foreach ($group['links'] as $link)
                            <li><a href="{{ route($link['route']) }}" class="flex items-center gap-2 rounded-xl px-2 py-2 text-sm text-slate-300 hover:bg-veil/5 hover:text-white">{{ $link['label'] }} <flux:icon.chevron-right class="ml-auto size-4 text-slate-500" /></a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
</x-admin.page>
