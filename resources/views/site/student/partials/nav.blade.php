@php
    $unreadTotal = auth()->check() ? app(\App\Chat\UnreadCounter::class)->totalFor(auth()->user()) : 0;
    $items = [
        ['route' => 'dashboard', 'active' => 'dashboard', 'label' => 'Accueil', 'icon' => 'home'],
        ['route' => 'student.formations.index', 'active' => 'student.formations.*', 'label' => 'Mes formations', 'icon' => 'academic-cap'],
        ['route' => 'chat.index', 'active' => ['chat.*', 'moderation.*'], 'label' => 'Groupes', 'icon' => 'chat-bubble-left-right', 'badge' => $unreadTotal],
        ['route' => 'student.sessions.index', 'active' => 'student.sessions.*', 'label' => 'Sessions', 'icon' => 'calendar-days', 'desktop' => true],
        ['route' => 'student.purchases.index', 'active' => 'student.purchases.*', 'label' => 'Achats', 'icon' => 'receipt-percent'],
        ['route' => 'formations.index', 'active' => 'formations.*', 'label' => 'Catalogue', 'icon' => 'squares-2x2', 'desktop' => true],
        ['route' => 'student.profile', 'active' => ['student.profile', 'security.edit', 'student.notifications'], 'label' => 'Profil', 'icon' => 'user-circle'],
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
                    'border-gold-400 text-white' => request()->routeIs(...(array) $item['active']),
                    'border-transparent text-slate-400 hover:text-white' => ! request()->routeIs(...(array) $item['active']),
                ])
                @if (request()->routeIs(...(array) $item['active'])) aria-current="page" @endif
            >
                <flux:icon :icon="$item['icon']" class="size-4" /> {{ $item['label'] }}
                @if (($item['badge'] ?? 0) > 0)
                    <span class="min-w-5 rounded-full bg-gold-400 px-1.5 text-center text-[11px] leading-5 font-bold text-night-950" aria-label="{{ $item['badge'] }} non lus">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</nav>

{{-- Téléphone : barre de navigation fixée en bas de l'écran --}}
<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-white/10 bg-night-950/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl lg:hidden" aria-label="Mon espace">
    <ul class="mx-auto grid max-w-md grid-cols-5">
        @foreach (array_filter($items, fn ($item) => ! ($item['desktop'] ?? false)) as $item)
            @php $active = request()->routeIs(...(array) $item['active']); @endphp
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
                    <span @class(['relative grid h-7 w-12 place-items-center rounded-full transition', 'bg-gold-400/15' => $active])>
                        <flux:icon :icon="$item['icon']" :variant="$active ? 'solid' : 'outline'" class="size-5" />
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="absolute -top-1 right-0.5 min-w-4.5 rounded-full bg-gold-400 px-1 text-center text-[10px] leading-4.5 font-bold text-night-950" aria-label="{{ $item['badge'] }} non lus" data-test="nav-unread">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                        @endif
                    </span>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
