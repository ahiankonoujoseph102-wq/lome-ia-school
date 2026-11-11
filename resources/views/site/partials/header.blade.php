@php
    $links = [
        ['route' => 'formations.index', 'active' => 'formations.*', 'label' => 'Formations'],
        // Bourse « Agents IA » : lien seulement pendant que les candidatures sont ouvertes.
        ...(($scholarshipOpen = \App\Scholarships\ScholarshipCampaign::isOpen()) ? [['route' => 'scholarship', 'active' => 'scholarship', 'label' => 'Bourse IA', 'test' => 'menu-scholarship']] : []),
        // Offres d'accompagnement : lien dès qu'au moins une offre est publiée, même close (la page affiche alors
        // « Inscriptions closes ») ; sans offre publiée, la page n'existe pas.
        ...(($packsOnSale = \App\Packs\PacksPage::published()) ? [['route' => 'packs', 'active' => 'packs', 'label' => 'Accompagnement', 'test' => 'menu-packs']] : []),
        ['route' => 'consultation', 'active' => 'consultation', 'label' => 'Consultation gratuite'],
        ['route' => 'business', 'active' => 'business', 'label' => 'Entreprises'],
        // Talkami, création de Joseph (novembre 2026) : lien tant que la page est affichée.
        // Pas de page à part (décision de Joseph) : le lien mène à la section Talkami de l'accueil.
        ...(\App\Support\Talkami::enabled() ? [['route' => 'home', 'href' => route('home').'#talkami', 'active' => 'talkami-none', 'label' => 'Talkami', 'test' => 'menu-talkami']] : []),
        ['route' => 'about', 'active' => 'about', 'label' => 'À propos'],
        ['route' => 'contact', 'active' => 'contact', 'label' => 'Contact'],
    ];
    // Menu chargé (offres ou Talkami) : le bouton « Voir les formations » n'est plus affiché sur grand écran (xl).
    $compactMenu = $packsOnSale || $scholarshipOpen || \App\Support\Talkami::enabled();
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
        :class="scrolled || open ? 'border-veil/10 bg-night-950/85 backdrop-blur-xl' : 'border-transparent bg-transparent'"
    >
        <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6" aria-label="Navigation principale">
            <x-site.logo />

            <div class="hidden items-center gap-0.5 lg:flex xl:gap-1">
                @foreach ($links as $link)
                    <a
                        href="{{ $link['href'] ?? route($link['route']) }}"
                        @class([
                            'rounded-full px-2.5 py-2 text-sm font-medium whitespace-nowrap transition xl:px-4',
                            'bg-veil/10 text-white' => request()->routeIs($link['active']),
                            'text-slate-300 hover:bg-veil/5 hover:text-white' => ! request()->routeIs($link['active']),
                        ])
                        @if (request()->routeIs($link['active'])) aria-current="page" @endif
                        @isset($link['test']) data-test="{{ $link['test'] }}" @endisset
                    >{{ $link['label'] }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                {{-- WhatsApp dans l'en-tête sur téléphone et tablette (le bouton flottant n'apparaît que sur grand écran). --}}
                <a
                    href="{{ \App\Support\WhatsApp::url($whatsappMessage ?? null) }}"
                    data-whatsapp-page
                    target="_blank"
                    rel="noopener"
                    class="grid size-10 place-items-center rounded-full bg-[#25D366] text-white shadow-lg shadow-black/30 transition active:scale-95 lg:hidden"
                    aria-label="Nous écrire sur WhatsApp"
                >
                    <x-site.whatsapp-icon class="size-5" />
                </a>
                @auth
                    @php
                        // Messages non lus de tous ses groupes : une seule requête par page, partagée avec la barre de Mon espace.
                        $unreadTotal = app(\App\Chat\UnreadCounter::class)->totalFor(auth()->user());
                        $unreadLabel = $unreadTotal > 99 ? '99+' : (string) $unreadTotal;
                    @endphp
                    {{-- Élève connecté : photo ou initiales, avec accès rapide à « Mon espace » --}}
                    <a href="{{ route('dashboard') }}" class="relative rounded-full lg:hidden" aria-label="Mon espace{{ $unreadTotal > 0 ? ' : '.$unreadTotal.' '.($unreadTotal > 1 ? 'messages non lus' : 'message non lu') : '' }}">
                        <x-site.avatar :user="auth()->user()" class="size-10 text-sm" />
                        @if ($unreadTotal > 0)
                            <span class="absolute -top-1 -right-1 min-w-5 rounded-full bg-gold-400 px-1 text-center text-[10px] leading-5 font-bold text-night-950" data-test="header-unread" aria-hidden="true">{{ $unreadLabel }}</span>
                        @endif
                    </a>
                    <div class="relative hidden lg:block" x-data="{ userMenu: false }" @click.outside="userMenu = false" @keydown.escape.window="userMenu = false">
                        <button type="button" @click="userMenu = !userMenu" :aria-expanded="userMenu.toString()" class="flex items-center gap-2 rounded-full py-1 pr-3 pl-1 text-sm font-semibold text-white ring-1 ring-veil/15 transition hover:bg-veil/10">
                            <x-site.avatar :user="auth()->user()" class="size-8 text-xs" />
                            {{ auth()->user()->first_name }}
                            @if ($unreadTotal > 0)
                                <span class="min-w-5 rounded-full bg-gold-400 px-1.5 text-center text-[11px] leading-5 font-bold text-night-950" aria-label="{{ $unreadTotal }} non lus">{{ $unreadLabel }}</span>
                            @endif
                            <flux:icon.chevron-down class="size-4 text-slate-400" />
                        </button>
                        <div x-show="userMenu" x-cloak x-transition.origin.top.right class="absolute right-0 mt-2 w-64 overflow-hidden rounded-2xl border border-veil/10 bg-night-900/95 p-2 shadow-2xl shadow-black/50 backdrop-blur-xl">
                            <div class="px-3 py-2">
                                <p class="truncate font-semibold text-white">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                            </div>
                            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-slate-200 hover:bg-veil/5"><flux:icon.home class="size-4" /> Mon espace</a>
                            <a href="{{ route('student.formations.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-slate-200 hover:bg-veil/5"><flux:icon.academic-cap class="size-4" /> Mes formations</a>
                            <a href="{{ route('chat.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-slate-200 hover:bg-veil/5"><flux:icon.chat-bubble-left-right class="size-4" /> Mes groupes
                                @if ($unreadTotal > 0)
                                    <span class="ml-auto min-w-5 rounded-full bg-gold-400 px-1.5 text-center text-[11px] leading-5 font-bold text-night-950" aria-label="{{ $unreadTotal }} non lus">{{ $unreadLabel }}</span>
                                @endif
                            </a>
                            <a href="{{ route('student.sessions.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-slate-200 hover:bg-veil/5"><flux:icon.calendar-days class="size-4" /> Mes sessions</a>
                            <a href="{{ route('student.purchases.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-slate-200 hover:bg-veil/5"><flux:icon.receipt-percent class="size-4" /> Mes achats</a>
                            <a href="{{ route('student.affiliate.index') }}" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-slate-200 hover:bg-veil/5"><flux:icon.banknotes class="size-4" /> Affiliation</a>
                            <a href="{{ route('student.profile') }}" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-slate-200 hover:bg-veil/5"><flux:icon.user-circle class="size-4" /> Mon profil</a>
                            @include('site.partials.admin-entry-link', ['class' => 'rounded-xl px-3 py-2.5 text-sm'])
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-veil/10 pt-1">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-left text-sm text-slate-300 hover:bg-veil/5"><flux:icon.arrow-right-start-on-rectangle class="size-4" /> Se déconnecter</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="hidden rounded-full px-3 py-2 text-sm font-semibold whitespace-nowrap text-slate-200 transition hover:text-white sm:inline-flex xl:px-4">Connexion</a>
                @endauth
                {{-- Avec le lien « Accompagnement », le menu est plein sur grand écran : le bouton (doublon du lien « Formations ») n'y reste que sur tablette. --}}
                <a href="{{ route('formations.index') }}" @class(['btn-gold hidden !px-5 !py-2.5 !text-sm whitespace-nowrap md:inline-flex lg:hidden', 'xl:inline-flex' => ! $compactMenu])>Voir les formations</a>

                <button
                    type="button"
                    class="grid size-11 place-items-center rounded-full text-white ring-1 ring-veil/15 transition hover:bg-veil/10 lg:hidden"
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
                    <a href="{{ route('home') }}" class="flex items-center justify-between rounded-2xl px-4 py-4 text-lg font-semibold text-white hover:bg-veil/5">
                        Accueil <flux:icon.arrow-right class="size-5 text-slate-500" />
                    </a>
                </li>
                @foreach ($links as $link)
                    <li>
                        <a
                            href="{{ $link['href'] ?? route($link['route']) }}"
                            @isset($link['href']) x-on:click="open = false" @endisset
                            @class([
                                'flex items-center justify-between rounded-2xl px-4 py-4 text-lg font-semibold hover:bg-veil/5',
                                'bg-veil/5 text-electric-300' => request()->routeIs($link['active']),
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
                    <div class="card flex items-center gap-3 p-4">
                        <x-site.avatar :user="auth()->user()" class="size-12" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-white">{{ auth()->user()->name }}</p>
                            <p class="truncate text-sm text-slate-400">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    <a href="{{ route('dashboard') }}" class="btn-ghost w-full">Mon espace</a>
                    <a href="{{ route('chat.index') }}" class="card flex items-center gap-3 px-4 py-3.5 font-semibold text-slate-100" data-test="menu-groups">
                        <flux:icon.chat-bubble-left-right class="size-5 text-slate-400" /> Mes groupes
                        @if ($unreadTotal > 0)
                            <span class="ml-auto min-w-6 rounded-full bg-gold-400 px-1.5 text-center text-xs leading-6 font-bold text-night-950" aria-label="{{ $unreadTotal }} non lus">{{ $unreadLabel }}</span>
                        @endif
                    </a>
                    @include('site.partials.admin-entry-link', ['class' => 'card px-4 py-3.5 font-semibold'])
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-full py-3 text-sm font-semibold text-slate-400 hover:text-white">Se déconnecter</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost w-full">Connexion</a>
                    <a href="{{ route('register') }}" class="w-full py-2 text-center text-sm font-semibold text-electric-300">Créer un compte</a>
                @endauth
            </div>

            <div class="mt-auto pt-10 text-sm text-slate-400">
                <p>Une question ? Écrivez-nous :</p>
                <a href="{{ \App\Support\WhatsApp::url($whatsappMessage ?? null) }}" data-whatsapp-page target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 font-semibold text-[#25D366]">
                    <x-site.whatsapp-icon class="size-5" /> {{ config('site.contact.phones.0') }}
                </a>
            </div>
        </div>
    </div>
</header>
