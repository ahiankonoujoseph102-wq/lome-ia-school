@php
    $items = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'label' => 'Accueil', 'icon' => 'home'],
        ['route' => 'student.formations.index', 'active' => 'student.formations.*', 'label' => 'Mes formations', 'icon' => 'academic-cap'],
        ['route' => 'formations.index', 'active' => 'formations.*', 'label' => 'Catalogue', 'icon' => 'squares-2x2'],
        ['route' => 'student.profile', 'active' => 'student.profile', 'label' => 'Profil', 'icon' => 'user-circle'],
    ];
@endphp

{{-- Ordinateur : onglets sous l'en-tête --}}
<nav class="hidden border-b border-white/10 bg-night-950/60 lg:block" aria-label="Mon espace">
    <div class="mx-auto flex max-w-6xl items-center gap-1 px-6">
        <span class="mr-4 text-sm font-semibold text-slate-400">Mon espace</span>
        @foreach ($items as $item)
            <a
                href="{{ route($item['route']) }}"
                @class([
                    'inline-flex items-center gap-2 border-b-2 px-4 py-3.5 text-sm font-medium transition',
                    'border-gold-400 text-white' => request()->routeIs($item['active']),
                    'border-transparent text-slate-400 hover:text-white' => ! request()->routeIs($item['active']),
                ])
                @if (request()->routeIs($item['active'])) aria-current="page" @endif
            >
                <flux:icon :icon="$item['icon']" class="size-4" /> {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>

{{-- Téléphone : barre de navigation fixée en bas de l'écran --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-night-950/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl lg:hidden" aria-label="Mon espace">
    <ul class="mx-auto grid max-w-md grid-cols-4">
        @foreach ($items as $item)
            @php $active = request()->routeIs($item['active']); @endphp
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'flex flex-col items-center gap-1 py-2.5 text-[11px] font-medium transition',
                        'text-gold-300' => $active,
                        'text-slate-400 hover:text-white' => ! $active,
                    ])
                    @if ($active) aria-current="page" @endif
                >
                    <span @class(['grid h-7 w-12 place-items-center rounded-full transition', 'bg-gold-400/15' => $active])>
                        <flux:icon :icon="$item['icon']" :variant="$active ? 'solid' : 'outline'" class="size-5" />
                    </span>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
