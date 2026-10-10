@php
    use App\Packs\PackDeadline;
    use App\Packs\PacksPage;
    use App\Settings\SiteSettings;
    use App\Support\Money;
    use App\Support\RichText;

    /**
     * Page de vente des offres d'accompagnement : en-tête (affiche, promesse, compte à rebours, bouton), cartes
     * compactes, témoignages, « Pourquoi nous faire confiance », « Comment s'inscrire », « Dans toutes les offres », dernier bouton, et barre fixe sur téléphone.
     *
     * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Formation> $packs
     * @var \App\Models\Formation|null $countdownPack offre ouverte dont la date de fin est la plus proche
     * @var int|null $closesAt fin de la dernière offre ouverte (ms) : les boutons communs se masquent à cette heure
     * @var list<array<string, mixed>> $testimonials témoignages actifs (App\Support\Testimonials)
     * @var list<array<string, mixed>> $trust faits du bloc de confiance (App\Packs\PackTrust)
     */
    // Textes fixes (config/site.php, clé packs_page ; App\Packs\PackSalesTexts). Une clé absente d'un réglage
    // enregistré plus ancien prend la valeur de départ.
    $page = (array) config('site.packs_page') + (array) SiteSettings::default('site.packs_page');
    $countdownDeadline = $countdownPack?->registrationDeadline();
    $lastIndex = $packs->count() - 1;
    // Fermeture des boutons communs quand le compte à rebours de la dernière offre ouverte arrive à zéro.
    $closeOn = $closesAt === null ? 'false' : '$event.detail.deadline >= '.$closesAt;
@endphp

<x-layouts::site
    title="Accompagnement IA"
    description="Trois offres d'accompagnement en intelligence artificielle avec Coach Joseph : plusieurs formations réunies, un groupe VIP et un accompagnement à vie. Paiement par Mobile Money."
    :sticky-cta="$open"
>
    {{-- En-tête : affiche (s'il y en a une), titre, promesse, compte à rebours et bouton vers les offres. --}}
    <section class="relative isolate overflow-hidden" data-test="packs-hero">
        <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>

        <div class="mx-auto max-w-6xl px-4 pt-6 pb-10 sm:px-6 sm:pt-10 lg:pb-14" @if ($posterUrl) data-test="packs-hero-poster" @endif>
            <div @class(['grid items-center gap-6 lg:gap-12', 'lg:grid-cols-[1.15fr_0.85fr]' => $posterUrl])>
                <div @class(['order-2 lg:order-1', 'mx-auto max-w-3xl text-center' => ! $posterUrl])>
                    <span class="eyebrow" data-reveal>{{ $page['eyebrow'] }}</span>
                    <h1 class="mt-4 text-3xl leading-tight font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">{{ $page['title'] }}</h1>
                    <p @class(['mt-4 text-base text-pretty text-slate-300 sm:text-lg', 'mx-auto max-w-2xl' => ! $posterUrl]) data-reveal style="--reveal-delay: 120ms" data-test="packs-promise">{{ $page['subtitle'] }}</p>

                    @if ($countdownPack)
                        <x-site.pack-deadline :pack="$countdownPack" :class="'mt-6 max-w-md text-left'.($posterUrl ? '' : ' mx-auto')" />
                    @endif

                    @if ($open)
                        <div class="mt-6" x-data="{ closed: false }" x-on:pack-registrations-closed.window="if ({{ $closeOn }}) closed = true">
                            <a href="#offres" class="btn-gold w-full !py-4 text-lg sm:w-auto sm:min-w-80" x-show="! closed" data-test="packs-hero-button" data-sticky-hide data-ama-avoid data-install-avoid>Je m'inscris maintenant</a>
                            <p class="rounded-2xl bg-night-900 px-4 py-3 text-center font-semibold text-slate-200 shadow-[inset_0_0_0_1px_var(--color-night-600)]" x-show="closed" x-cloak>Inscriptions closes</p>
                        </div>
                    @elseif ($allClosed)
                        <p @class(['mt-6 rounded-2xl bg-night-900 px-4 py-3 text-center font-semibold text-slate-200 shadow-[inset_0_0_0_1px_var(--color-night-600)]', 'mx-auto max-w-md' => ! $posterUrl, 'max-w-md' => $posterUrl]) data-test="packs-closed">Inscriptions closes</p>
                    @endif
                </div>

                @if ($posterUrl)
                    <div class="order-1 lg:order-2" data-reveal>
                        <div class="relative aspect-[16/10] overflow-hidden rounded-3xl bg-night-800 shadow-2xl shadow-electric-900/50 ring-1 ring-veil/10" data-test="packs-poster">
                            <img src="{{ $posterUrl }}" alt="{{ $page['title'] }}" class="absolute inset-0 size-full object-cover" loading="eager" decoding="async" width="800" height="500">
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Les offres. --}}
    <section id="offres" class="mx-auto max-w-6xl px-4 sm:px-6" data-test="packs-page">
        @if ($preview)
            <div class="mx-auto mb-6 max-w-3xl rounded-2xl bg-night-900 px-4 py-3 text-sm text-gold-100 shadow-[inset_0_0_0_1px_var(--color-gold-500)]" data-test="packs-preview">
                Aperçu réservé à l'administrateur : ces offres sont masquées et cette page n'est pas visible par le public. Publiez-les dans « Formations et prix » (case « Visible sur le site »).
            </div>
        @endif

        <div class="text-center">
            <h2 class="text-2xl font-bold tracking-tight text-balance text-white sm:text-3xl">{{ $page['offers_title'] }}</h2>
            @if (filled($page['offers_text'] ?? null))
                <p class="mt-2 text-sm text-slate-300 sm:text-base">{{ $page['offers_text'] }}</p>
            @endif
        </div>

        <ol class="mt-8 grid gap-6 lg:grid-cols-3 lg:items-start">
            @foreach ($packs as $pack)
                @php
                    $included = $pack->includedFormations->reject->isGroupOnly()->values();
                    $hasVip = $pack->includedFormations->contains->isGroupOnly();
                    $separate = $pack->separatePrice();
                    $isOwned = in_array($pack->id, $owned, true);
                    $summary = RichText::summary($pack->description);
                    $deadline = $pack->registrationDeadline();
                    $featured = $loop->index === $lastIndex && $lastIndex > 0;
                    // Date de cette offre déjà affichée en haut de page : pas de deuxième compte à rebours sur la carte.
                    $ownDeadline = $deadline !== null && ($pack->registrationsClosed() || $countdownDeadline === null || ! $deadline->equalTo($countdownDeadline));
                @endphp
                <li
                    id="{{ $pack->slug }}"
                    @class([
                        'card relative flex scroll-mt-24 flex-col p-5 sm:p-6',
                        'mt-3 shadow-[inset_0_0_0_2px_var(--color-gold-500)] lg:mt-0' => $featured,
                    ])
                    data-test="pack-card"
                    data-reveal
                    style="--reveal-delay: {{ $loop->index * 60 }}ms"
                >
                    @if ($featured)
                        <p class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-night-900 px-4 py-1 text-xs font-bold whitespace-nowrap text-gold-100 shadow-[inset_0_0_0_1px_var(--color-gold-500)]" data-test="pack-featured">Le plus complet</p>
                    @endif

                    @if ($pack->image_url)
                        {{-- Affiche propre à l'offre (envoyée dans sa fiche de l'admin). --}}
                        <x-site.formation-cover :formation="$pack" class="mb-5 aspect-[16/10] rounded-2xl ring-1 ring-veil/10" data-test="pack-card-poster" />
                    @endif

                    @if ($ownDeadline)
                        {{-- Date différente de celle du haut de page, ou inscriptions closes : affichée sur la carte. --}}
                        <x-site.pack-deadline :pack="$pack" class="mb-4" />
                    @endif

                    <p class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full bg-night-900 px-3 py-1 text-xs font-semibold text-gold-200 ring-1 ring-gold-500/60">Niveau {{ $loop->iteration }}</span>
                        @unless ($pack->is_active)
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold text-slate-300 ring-1 ring-veil/20">Masquée</span>
                        @endunless
                    </p>
                    <h3 class="mt-3 text-xl leading-snug font-bold text-balance text-white sm:text-2xl" data-test="pack-name">{{ PacksPage::shortName($pack->title) }}</h3>

                    <div class="mt-3">
                        <p class="text-4xl font-bold tracking-tight text-gold-200 tabular-nums" data-test="pack-price">{{ $pack->formatted_price }}</p>
                        @if ($separate > $pack->price)
                            <p class="mt-1 text-sm text-slate-400" data-test="pack-separate-price">
                                Formations achetées séparément : <s class="tabular-nums">{{ Money::fcfa($separate) }}</s>
                            </p>
                        @endif
                        <p class="mt-2 flex items-center gap-1.5 text-sm text-slate-300">
                            @if ($pack->needsPaymentVerification())
                                <flux:icon.shield-check class="size-4 shrink-0 text-gold-400" /> Accès ouvert après vérification du paiement
                            @else
                                <flux:icon.bolt class="size-4 shrink-0 text-gold-400" /> Accès dès l'envoi de la capture du paiement
                            @endif
                        </p>
                    </div>

                    {{-- Accroche et quatre points forts au plus (description de l'offre, App\Support\RichText::summary). --}}
                    <div class="mt-4" data-test="pack-description">
                        @if ($summary['hook'] !== [])
                            <p class="text-base leading-relaxed text-pretty text-slate-200">
                                @foreach ($summary['hook'] as $line)
                                    {{ $line }}@unless ($loop->last)<br>@endunless
                                @endforeach
                            </p>
                        @endif
                        @if ($summary['points'] !== [])
                            <ul class="mt-4 space-y-2.5 text-sm text-white" data-test="pack-points">
                                @foreach ($summary['points'] as $point)
                                    <li class="flex gap-2.5"><flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-emerald-400" /> <span class="min-w-0">{{ $point }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="mt-6 lg:mt-auto lg:pt-6">
                        @if ($isOwned)
                            <a href="{{ route('student.formations.index') }}" class="btn-ghost w-full" data-test="pack-owned">Offre déjà active : voir mes formations</a>
                        @elseif ($pack->registrationsClosed())
                            {{-- Date de fin des inscriptions passée : plus de bouton d'achat (le serveur refuse aussi toute commande). --}}
                            <p class="rounded-2xl bg-night-900 px-4 py-3 text-center font-semibold text-slate-200 shadow-[inset_0_0_0_1px_var(--color-night-600)]" data-test="pack-closed">Inscriptions closes</p>
                        @elseif ($pack->is_active)
                            <div x-data="{ closed: false }" x-on:pack-registrations-closed.window="if ($event.detail.slug === @js($pack->slug) || {{ $deadline ? '$event.detail.deadline >= '.$deadline->getTimestampMs() : 'false' }}) closed = true">
                                <a href="{{ route('checkout.show', $pack) }}" class="btn-gold w-full !py-4 text-lg" x-show="! closed" data-test="pack-choose" data-ama-avoid data-install-avoid>Je m'inscris maintenant</a>
                                <p class="rounded-2xl bg-night-900 px-4 py-3 text-center font-semibold text-slate-200 shadow-[inset_0_0_0_1px_var(--color-night-600)]" x-show="closed" x-cloak>Inscriptions closes</p>
                            </div>
                        @else
                            <span class="btn-ghost w-full cursor-not-allowed opacity-60">Je m'inscris maintenant (offre masquée)</span>
                        @endif

                        {{-- Tout ce qui est inclus, replié pour garder la carte courte (fonctionne sans JavaScript). --}}
                        <details class="group mt-4" data-test="pack-more">
                            <summary class="mx-auto flex w-fit cursor-pointer list-none items-center gap-1.5 rounded-full px-3 py-2 text-sm font-semibold text-electric-200 [&::-webkit-details-marker]:hidden" data-test="pack-more-toggle">
                                <span class="group-open:hidden">Voir tout ce qui est inclus</span>
                                <span class="hidden group-open:inline">Masquer le détail</span>
                                <flux:icon.chevron-down class="size-4 transition group-open:rotate-180 motion-reduce:transition-none" />
                            </summary>
                            <div class="mt-3 rounded-2xl bg-night-900/70 p-4 shadow-[inset_0_0_0_1px_var(--color-night-700)]">
                                <p class="text-sm font-semibold text-white">Formations incluses</p>
                                <ul class="mt-3 space-y-2.5 text-sm text-slate-200" data-test="pack-included">
                                    @foreach ($included as $formation)
                                        <li class="flex gap-2"><flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-emerald-400" /> <span>{{ $formation->title }}</span></li>
                                    @endforeach
                                    @if ($hasVip)
                                        <li class="flex gap-2"><flux:icon.chat-bubble-left-right class="mt-0.5 size-4 shrink-0 text-gold-400" /> <span>Groupe VIP</span></li>
                                    @endif
                                </ul>
                                @if ($summary['rest'] !== [])
                                    {{-- Le reste de la description (paragraphes suivants, points au-delà de quatre). --}}
                                    <x-site.rich-text :blocks="$summary['rest']" class="mt-4 text-sm leading-relaxed text-pretty text-slate-300" data-test="pack-description-more" />
                                @endif
                            </div>
                        </details>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Témoignages : les mêmes que sur l'accueil et les pages des formations (gérés dans l'admin, Témoignages),
         section masquée tant qu'il n'y en a aucun. Vidéos chargées seulement au toucher. --}}
    @if ($testimonials)
        <section class="mx-auto max-w-6xl px-4 pt-14 sm:px-6 sm:pt-20" data-test="packs-testimonials">
            <x-site.section-heading eyebrow="Témoignages" title="Ce qu'en disent les élèves" />
            <x-site.testimonials :items="$testimonials" class="mt-8 sm:mt-10" />
        </section>
    @endif

    {{-- Pourquoi nous faire confiance : faits lus dans les réglages du site (App\Packs\PackTrust) ; une information
         absente n'est pas affichée. --}}
    @if ($trust)
        @include('site.packs.partials.trust', ['facts' => $trust])
    @endif

    {{-- Comment s'inscrire : le vrai parcours de paiement Mobile Money du site (sans objet une fois tout clos). --}}
    @unless ($allClosed)
    <section class="mx-auto max-w-3xl px-4 pt-14 sm:px-6 sm:pt-20" data-test="packs-steps">
        <h2 class="text-center text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $page['steps_title'] }}</h2>
        <ol class="mt-6 space-y-3">
            @foreach ((array) ($page['steps'] ?? []) as $step)
                <li class="flex items-start gap-3 rounded-2xl bg-night-900/70 p-4 shadow-[inset_0_0_0_1px_var(--color-night-700)]">
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-night-950 text-sm font-bold text-gold-200 shadow-[inset_0_0_0_1px_var(--color-gold-500)]" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span class="pt-1 text-sm text-pretty text-slate-200 sm:text-base">{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    </section>
    @endunless

    {{-- Dans toutes les offres : une ligne chacun. --}}
    <section class="mx-auto max-w-3xl px-4 pt-14 sm:px-6 sm:pt-20" data-test="packs-common">
        <h2 class="text-center text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $page['common_title'] }}</h2>
        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
            @foreach ((array) ($page['common'] ?? []) as $item)
                <li class="flex items-center gap-3 rounded-2xl bg-veil/[0.04] p-3.5 ring-1 ring-veil/10">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30">
                        <flux:icon :icon="$item['icon']" class="size-5" />
                    </span>
                    <span class="text-sm font-semibold text-white">
                        {{ $item['title'] }}
                        @if (filled($item['text'] ?? null))
                            <span class="mt-0.5 block text-sm font-normal text-slate-300">{{ $item['text'] }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
        @if (filled($page['payment_note'] ?? null))
            {{-- Ancien texte (réglage enregistré avant la refonte). --}}
            <p class="mx-auto mt-6 max-w-2xl text-center text-sm text-slate-400">{{ $page['payment_note'] }}</p>
        @endif
    </section>

    {{-- Dernier bouton. --}}
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6 sm:py-20" data-test="packs-final">
        <div class="rounded-3xl bg-night-900 p-6 text-center shadow-[inset_0_0_0_1px_var(--color-gold-500)] sm:p-10">
            <h2 class="text-2xl font-bold tracking-tight text-balance text-white sm:text-3xl">{{ $page['final_title'] }}</h2>
            @if (filled($page['final_text'] ?? null))
                <p class="mt-2 text-sm text-pretty text-slate-300 sm:text-base">{{ $page['final_text'] }}</p>
            @endif
            @if ($open)
                <div class="mt-6" x-data="{ closed: false }" x-on:pack-registrations-closed.window="if ({{ $closeOn }}) closed = true">
                    <div x-show="! closed">
                        @if ($countdownDeadline)
                            <p class="mb-4 text-sm font-semibold text-gold-100">Inscriptions ouvertes jusqu'au {{ PackDeadline::label($countdownDeadline) }}</p>
                        @endif
                        <a href="#offres" class="btn-gold w-full !py-4 text-lg sm:w-auto sm:min-w-80" data-test="packs-final-button" data-sticky-hide data-ama-avoid data-install-avoid>Je m'inscris maintenant</a>
                    </div>
                    <p class="rounded-2xl bg-night-950 px-4 py-3 font-semibold text-slate-200 shadow-[inset_0_0_0_1px_var(--color-night-600)]" x-show="closed" x-cloak>Inscriptions closes</p>
                </div>
            @elseif ($allClosed)
                <p class="mt-6 rounded-2xl bg-night-950 px-4 py-3 font-semibold text-slate-200 shadow-[inset_0_0_0_1px_var(--color-night-600)]" data-test="packs-final-closed">Inscriptions closes</p>
            @endif
        </div>
    </section>

    @if ($open)
        {{-- Barre fixée en bas de l'écran (téléphone et tablette) : temps restant et bouton vers les offres ; masquée tant
             que le gros bouton du haut ou du bas est à l'écran (Alpine packsStickyBar). --}}
        <x-slot:after>
            @php $left = $countdownDeadline ? PackDeadline::remaining($countdownDeadline) : null; @endphp
            <div
                data-bottom-bar
                data-install-avoid
                class="fixed inset-x-0 bottom-0 z-40 border-t border-veil/10 bg-night-950/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl transition duration-300 motion-reduce:transition-none lg:hidden"
                x-data="packsStickyBar"
                x-show="! closed"
                :class="away ? '' : 'pointer-events-none translate-y-full opacity-0'"
                :aria-hidden="away ? 'false' : 'true'"
                x-on:pack-registrations-closed.window="if ({{ $closeOn }}) closed = true"
                data-test="packs-sticky-bar"
            >
                <div class="mx-auto flex max-w-xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        @if ($countdownPack && $left)
                            <div x-data="packCountdown(@js(['deadline' => $countdownDeadline->getTimestampMs(), 'serverNow' => now()->getTimestampMs(), 'slug' => $countdownPack->slug]))" data-test="packs-sticky-countdown">
                                <p class="text-xs text-slate-400">Clôture dans</p>
                                <p class="sr-only" x-text="label">{{ PackDeadline::spokenRemaining($left) }}</p>
                                <p class="text-sm leading-tight font-bold whitespace-nowrap text-white tabular-nums" aria-hidden="true" data-test="packs-sticky-time"><span x-text="days">{{ $left['days'] }}</span>&nbsp;j <span x-text="pad(hours)">{{ sprintf('%02d', $left['hours']) }}</span>&nbsp;h <span x-text="pad(minutes)">{{ sprintf('%02d', $left['minutes']) }}</span>&nbsp;min <span x-text="pad(seconds)" class="countdown-second" :class="seconds % 2 ? 'countdown-tick-a' : 'countdown-tick-b'">{{ sprintf('%02d', $left['seconds']) }}</span>&nbsp;s</p>
                            </div>
                        @else
                            <p class="truncate text-xs text-slate-400">{{ $page['eyebrow'] }}</p>
                            <p class="text-sm leading-tight font-bold text-white">Inscriptions ouvertes</p>
                        @endif
                    </div>
                    <a href="#offres" class="btn-gold shrink-0 !px-4 !py-3 text-sm" data-test="packs-sticky-button">Je m'inscris maintenant</a>
                </div>
            </div>
        </x-slot:after>
    @endif
</x-layouts::site>
