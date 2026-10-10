@php
    use App\Support\Money;

    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Formation> $packs */
    // Textes fixes de la page (config/site.php, clé packs_page ; nouveaux textes : app:pack-texts-apply).
    $page = (array) config('site.packs_page');
@endphp

<x-layouts::site
    title="Accompagnement IA"
    description="Trois offres d'accompagnement en intelligence artificielle avec Coach Joseph : plusieurs formations réunies, un groupe VIP et un accompagnement à vie. Paiement par Mobile Money."
>
    @if ($posterUrl)
        {{-- Avec une affiche : même en-tête que la page d'une formation (affiche en premier sur téléphone, à droite sur ordinateur). --}}
        <section class="relative isolate overflow-hidden" data-test="packs-hero-poster">
            <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
            <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>

            <div class="mx-auto max-w-6xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10 lg:pb-16">
                <div class="grid items-center gap-8 lg:grid-cols-[1.15fr_0.85fr] lg:gap-12">
                    <div class="order-2 lg:order-1">
                        <span class="eyebrow" data-reveal>{{ $page['eyebrow'] }}</span>
                        <h1 class="mt-4 text-3xl leading-tight font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">{{ $page['title'] }}</h1>
                        <p class="mt-4 max-w-2xl text-base text-pretty text-slate-300 sm:text-lg" data-reveal style="--reveal-delay: 120ms">{{ $page['subtitle'] }}</p>
                    </div>

                    <div class="order-1 lg:order-2" data-reveal>
                        <div class="relative aspect-[16/10] overflow-hidden rounded-3xl bg-night-800 shadow-2xl shadow-electric-900/50 ring-1 ring-veil/10" data-test="packs-poster">
                            <img src="{{ $posterUrl }}" alt="{{ $page['title'] }}" class="absolute inset-0 size-full object-cover" loading="eager" decoding="async" width="800" height="500">
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @else
        @include('site.partials.page-header', [
            'eyebrow' => $page['eyebrow'],
            'title' => $page['title'],
            'subtitle' => $page['subtitle'],
        ])
    @endif

    <section class="mx-auto max-w-6xl px-4 sm:px-6" data-test="packs-page">
        @if ($preview)
            <div class="mx-auto mb-6 max-w-3xl rounded-2xl bg-night-900 px-4 py-3 text-sm text-gold-100 shadow-[inset_0_0_0_1px_var(--color-gold-500)]" data-test="packs-preview">
                Aperçu réservé à l'administrateur : ces offres sont masquées et cette page n'est pas visible par le public. Publiez-les dans « Formations et prix » (case « Visible sur le site »).
            </div>
        @endif

        <ol class="grid gap-5 lg:grid-cols-3 lg:items-start">
            @foreach ($packs as $pack)
                @php
                    $included = $pack->includedFormations->reject->isGroupOnly()->values();
                    $hasVip = $pack->includedFormations->contains->isGroupOnly();
                    $separate = $pack->separatePrice();
                    $isOwned = in_array($pack->id, $owned, true);
                @endphp
                <li id="{{ $pack->slug }}" class="card flex scroll-mt-24 flex-col p-5 sm:p-6" data-test="pack-card" data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms">
                    @if ($pack->image_url)
                        {{-- Affiche propre à l'offre (envoyée dans sa fiche de l'admin) ; sans elle, la carte reste telle quelle. --}}
                        <x-site.formation-cover :formation="$pack" class="mb-5 aspect-[16/10] rounded-2xl ring-1 ring-veil/10" data-test="pack-card-poster" />
                    @endif
                    <p>
                        <span class="inline-flex rounded-full bg-night-900 px-3 py-1 text-xs font-semibold text-gold-200 ring-1 ring-gold-500/60">Niveau {{ $loop->iteration }}</span>
                        @unless ($pack->is_active)
                            <span class="ml-1 inline-flex rounded-full px-3 py-1 text-xs font-semibold text-slate-300 ring-1 ring-veil/20">Masquée</span>
                        @endunless
                    </p>
                    <h2 class="mt-3 text-xl leading-snug font-bold text-balance text-white sm:text-2xl">{{ $pack->title }}</h2>

                    <div class="mt-4">
                        <p class="text-3xl font-bold tracking-tight text-gold-200 tabular-nums" data-test="pack-price">{{ $pack->formatted_price }}</p>
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

                    <x-site.rich-text :text="$pack->description" class="mt-4 text-sm leading-relaxed text-pretty text-slate-300" data-test="pack-description" />

                    <div class="mt-5 rounded-2xl bg-night-900/70 p-4 shadow-[inset_0_0_0_1px_var(--color-night-700)]">
                        <p class="text-sm font-semibold text-white">Ce qui est inclus</p>
                        <ul class="mt-3 space-y-2.5 text-sm text-slate-200" data-test="pack-included">
                            @foreach ($included as $formation)
                                <li class="flex gap-2"><flux:icon.check-circle class="mt-0.5 size-4 shrink-0 text-emerald-400" /> <span>{{ $formation->title }}</span></li>
                            @endforeach
                            @if ($hasVip)
                                <li class="flex gap-2"><flux:icon.chat-bubble-left-right class="mt-0.5 size-4 shrink-0 text-gold-400" /> <span>Groupe VIP</span></li>
                            @endif
                        </ul>
                    </div>

                    <div class="mt-6 lg:mt-auto lg:pt-6">
                        @if ($isOwned)
                            <a href="{{ route('student.formations.index') }}" class="btn-ghost w-full" data-test="pack-owned">Offre déjà active : voir mes formations</a>
                        @elseif ($pack->is_active)
                            <a href="{{ route('checkout.show', $pack) }}" class="btn-gold w-full" data-test="pack-choose" data-ama-avoid data-install-avoid>Choisir cette offre</a>
                        @else
                            <span class="btn-ghost w-full cursor-not-allowed opacity-60">Choisir cette offre (offre masquée)</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="section">
        <x-site.section-heading :eyebrow="$page['common_eyebrow']" :title="$page['common_title']" />
        <ul class="mx-auto mt-10 grid max-w-4xl gap-3 sm:grid-cols-2" data-test="packs-common">
            @foreach ((array) ($page['common'] ?? []) as $item)
                <li class="flex items-start gap-3 rounded-2xl bg-veil/[0.04] p-4 ring-1 ring-veil/10" data-reveal style="--reveal-delay: {{ $loop->index * 40 }}ms">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30">
                        <flux:icon :icon="$item['icon']" class="size-5" />
                    </span>
                    <span class="pt-1.5">
                        <span class="block font-semibold text-white">{{ $item['title'] }}</span>
                        @if (filled($item['text'] ?? null))
                            <span class="mt-0.5 block text-sm text-slate-300">{{ $item['text'] }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
        <p class="mx-auto mt-8 max-w-2xl text-center text-sm text-slate-400">
            {{ $page['payment_note'] }}
        </p>
    </section>
</x-layouts::site>
