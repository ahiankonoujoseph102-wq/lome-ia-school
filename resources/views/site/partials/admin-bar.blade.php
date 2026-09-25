{{--
    Barre de l'administration (admin et modérateurs) : lien vers l'accueil admin et menu regroupé par thème.
    Style stable face au mode sombre forcé : fonds translucides, textes clairs, contours colorés.
--}}
@php
    $adminUser = auth()->user();
    $showAdminBar = $adminUser instanceof \App\Models\User
        && $adminUser->isStaff()
        && request()->routeIs('admin.*', 'moderation.*');
@endphp

@if ($showAdminBar)
    @php
        $groups = \App\Admin\AdminMenu::groups($adminUser);
        $todo = \App\Admin\AdminMenu::todoTotal($adminUser);
    @endphp
    <div class="border-b border-veil/10 bg-night-900/60" x-data="{ adminMenu: false }" @keydown.escape.window="adminMenu = false" data-test="admin-bar">
        <div class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-2.5 sm:px-6">
            <a href="{{ $adminUser->isAdmin() ? route('admin.index') : route('admin.sessions.index') }}" class="inline-flex min-w-0 items-center gap-2 text-sm font-semibold text-slate-200 hover:text-white">
                <flux:icon.shield-check class="size-5 shrink-0 text-electric-300" />
                <span class="truncate">{{ $adminUser->isAdmin() ? 'Administration' : 'Modération' }}</span>
            </a>
            <button type="button" @click="adminMenu = !adminMenu" :aria-expanded="adminMenu.toString()" aria-controls="menu-admin" class="ml-auto inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white ring-1 ring-veil/20 hover:bg-veil/10">
                <flux:icon.squares-2x2 class="size-4" /> Menu
                @if ($todo > 0)
                    <span class="rounded-full bg-gold-400/20 px-2 py-0.5 text-xs font-bold text-gold-100 ring-1 ring-gold-400/70" aria-label="{{ $todo }} à traiter">{{ $todo }}</span>
                @endif
                <span class="transition" :class="adminMenu && 'rotate-180'"><flux:icon.chevron-down class="size-4 text-slate-400" /></span>
            </button>
        </div>
        <nav id="menu-admin" x-show="adminMenu" x-cloak x-transition.opacity class="border-t border-veil/10" aria-label="Menu de l'administration">
            <div class="mx-auto grid max-w-6xl gap-5 px-4 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                @foreach ($groups as $group)
                    <div>
                        <p class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-slate-400 uppercase">
                            <flux:icon :name="$group['icon']" class="size-4" /> {{ $group['label'] }}
                        </p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($group['links'] as $link)
                                @php $active = request()->routeIs(...$link['active']); @endphp
                                <li>
                                    <a href="{{ route($link['route']) }}" @class([
                                        'flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm',
                                        'bg-electric-500/15 font-semibold text-white ring-1 ring-electric-400/40' => $active,
                                        'text-slate-200 hover:bg-veil/5' => ! $active,
                                    ]) @if ($active) aria-current="page" @endif>
                                        <flux:icon :name="$link['icon']" class="size-4 shrink-0 text-slate-400" />
                                        <span class="min-w-0 flex-1">{{ $link['label'] }}</span>
                                        @if (($link['count'] ?? 0) > 0)
                                            <span class="rounded-full bg-gold-400/20 px-2 py-0.5 text-xs font-bold text-gold-100 ring-1 ring-gold-400/70">{{ $link['count'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>
    </div>
@endif
