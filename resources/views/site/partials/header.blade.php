@php
    $links = [
        ['route' => 'formations.index', 'active' => 'formations.*', 'label' => 'Formations'],
        ['route' => 'consultation', 'active' => 'consultation', 'label' => 'Consultation gratuite'],
        ['route' => 'about', 'active' => 'about', 'label' => 'À propos'],
        ['route' => 'contact', 'active' => 'contact', 'label' => 'Contact'],
    ];
@endphp

<header
    x-data="{ open: false, scrolled: false }"
    x-init="scrolled = window.scrollY > 10"
    @scroll.window.passive="scrolled = window.scrollY > 10"
    @keydown.escape.window="open = false"
    x-effect="document.documentElement.classList.toggle('overflow-hidden', open)"
    class="sticky top-0 z-50"
>
    <div
        class="border-b transition-colors duration-300"
        :class="scrolled || open ? 'border-white/10 bg-night-950/85 backdrop-blur-xl' : 'border-transparent bg-transparent'"
    >
        <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6" aria-label="Navigation principale">
            <x-site.logo />

            <div class="hidden items-center gap-1 lg:flex">
                @foreach ($links as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        @class([
                            'rounded-full px-4 py-2 text-sm font-medium transition',
                            'bg-white/10 text-white' => request()->routeIs($link['active']),
                            'text-slate-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs($link['active']),
                        ])
                        @if (request()->routeIs($link['active'])) aria-current="page" @endif
                    >{{ $link['label'] }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden rounded-full px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/10 sm:inline-flex">Mon espace</a>
                @else
                    <a href="{{ route('login') }}" class="hidden rounded-full px-4 py-2 text-sm font-semibold text-slate-200 transition hover:text-white sm:inline-flex">Connexion</a>
                @endauth
                <a href="{{ route('formations.index') }}" class="btn-gold hidden !px-5 !py-2.5 !text-sm md:inline-flex">Voir les formations</a>

                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-full text-white ring-1 ring-white/15 transition hover:bg-white/10 lg:hidden"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="menu-mobile"
                    aria-label="Ouvrir le menu"
                >
                    <flux:icon.bars-3 class="size-6" x-show="!open" />
                    <flux:icon.x-mark class="size-6" x-show="open" x-cloak />
                </button>
            </div>
        </nav>
    </div>

    {{-- Menu mobile --}}
    <div
        id="menu-mobile"
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-300 ease-out"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-200 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-x-0 top-16 bottom-0 overflow-y-auto bg-night-950/97 backdrop-blur-xl lg:hidden"
    >
        <div class="glow pointer-events-none absolute inset-0 opacity-60"></div>
        <div class="relative flex min-h-full flex-col px-4 pt-6 pb-10">
            <ul class="space-y-1">
                <li>
                    <a href="{{ route('home') }}" class="flex items-center justify-between rounded-2xl px-4 py-4 text-lg font-semibold text-white hover:bg-white/5">
                        Accueil <flux:icon.arrow-right class="size-5 text-slate-500" />
                    </a>
                </li>
                @foreach ($links as $link)
                    <li>
                        <a
                            href="{{ route($link['route']) }}"
                            @class([
                                'flex items-center justify-between rounded-2xl px-4 py-4 text-lg font-semibold hover:bg-white/5',
                                'bg-white/5 text-electric-300' => request()->routeIs($link['active']),
                                'text-white' => ! request()->routeIs($link['active']),
                            ])
                        >
                            {{ $link['label'] }} <flux:icon.arrow-right class="size-5 text-slate-500" />
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 grid gap-3">
                <a href="{{ route('formations.index') }}" class="btn-gold w-full">Voir les formations</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-ghost w-full">Mon espace</a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost w-full">Connexion</a>
                @endauth
            </div>

            <div class="mt-auto pt-10 text-sm text-slate-400">
                <p>Une question ? Écrivez-nous :</p>
                <a href="{{ \App\Support\WhatsApp::url() }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 font-semibold text-[#25D366]">
                    <x-site.whatsapp-icon class="size-5" /> {{ config('site.contact.phones.0') }}
                </a>
            </div>
        </div>
    </div>
</header>
