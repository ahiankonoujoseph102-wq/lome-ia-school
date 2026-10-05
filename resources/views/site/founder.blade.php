@php
    use App\Support\Founder;

    $name = Founder::text('name');
    $nickname = Founder::text('nickname');
    $photo = Founder::photoUrl();
    $intro = Founder::lines('intro');
    $journey = Founder::lines('journey');
    $books = Founder::lines('books');
    $services = Founder::services();
    $links = Founder::links();
    $whatsappUrl = Founder::whatsappUrl();
    $whatsapp = Founder::contact('whatsapp');
    $email = Founder::contact('email');
    $office = Founder::contact('office');
@endphp

<x-layouts::site
    :title="Founder::text('title')"
    exact-title
    :description="Founder::text('description')"
    :image="route('og.founder')"
    image-type="image/jpeg"
    type="profile"
    :json-ld="[Founder::jsonLd()]"
>
    {{-- Haut de page : photo, nom, métier --}}
    <section class="relative isolate overflow-hidden" data-test="founder-hero">
        <div class="glow pointer-events-none absolute inset-0 -z-10 opacity-70"></div>
        <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
        <div class="mx-auto grid max-w-5xl items-center gap-8 px-4 pt-10 pb-12 sm:px-6 sm:pt-16 md:grid-cols-[260px_1fr] md:gap-12">
            <div class="mx-auto w-44 sm:w-56 md:w-full" data-reveal>
                @if ($photo)
                    <img src="{{ $photo }}" alt="{{ Founder::text('photo_alt') }}" width="{{ Founder::PHOTO_SIZE }}" height="{{ Founder::PHOTO_SIZE }}" class="aspect-square w-full rounded-3xl object-cover shadow-xl shadow-black/40 ring-2 ring-electric-400/40" loading="eager" decoding="async" data-test="founder-photo">
                @else
                    <span class="grid aspect-square w-full place-items-center rounded-3xl bg-linear-to-br from-electric-500 via-electric-700 to-night-700 text-5xl font-bold text-white ring-2 ring-electric-400/40" aria-hidden="true" data-test="founder-initials">{{ Founder::initials() }}</span>
                @endif
            </div>
            <div class="text-center md:text-left">
                @if ($nickname !== '')
                    <span class="eyebrow" data-reveal>{{ $nickname }}</span>
                @endif
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-balance text-white sm:text-4xl lg:text-5xl" data-reveal style="--reveal-delay: 60ms">{{ Founder::display(Founder::text('title')) }}</h1>
                @if (Founder::text('tagline') !== '')
                    <p class="mx-auto mt-4 max-w-2xl text-base text-pretty text-slate-300 sm:text-lg md:mx-0" data-reveal style="--reveal-delay: 120ms">{{ Founder::display(Founder::text('tagline')) }}</p>
                @endif
                <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-center md:justify-start" data-reveal style="--reveal-delay: 180ms">
                    <a href="{{ route('formations.index') }}" class="btn-gold">Voir ses formations <flux:icon.arrow-right class="size-5" /></a>
                    <a href="#contact" class="btn-ghost">Le contacter</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Présentation --}}
    @if ($intro)
        <section class="section !pt-4" aria-labelledby="presentation" data-test="founder-intro">
            <div class="mx-auto max-w-3xl" data-reveal>
                <span class="eyebrow">Présentation</span>
                <h2 id="presentation" class="mt-4 text-2xl font-bold text-white sm:text-3xl">Qui est {{ $name }}&nbsp;?</h2>
                <div class="prose-site mt-5">
                    @foreach ($intro as $paragraph)
                        <p>{{ Founder::display($paragraph) }}</p>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Parcours --}}
    @if ($journey)
        <section class="border-y border-veil/10 bg-veil/[0.02]" aria-labelledby="parcours" data-test="founder-journey">
            <div class="section">
                <div class="mx-auto max-w-3xl" data-reveal>
                    <span class="eyebrow">Parcours</span>
                    <h2 id="parcours" class="mt-4 text-2xl font-bold text-white sm:text-3xl">Parti de zéro</h2>
                    <ol class="mt-8 space-y-6 border-l border-electric-400/30 pl-6">
                        @foreach ($journey as $step)
                            <li class="relative">
                                <span class="absolute top-1.5 -left-[31px] size-3 rounded-full bg-gold-500 shadow-[0_0_0_4px_var(--color-night-950)]" aria-hidden="true"></span>
                                <p class="text-base leading-relaxed text-slate-200 sm:text-lg">{{ Founder::display($step) }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>
    @endif

    {{-- Livres --}}
    @if ($books)
        <section class="section" aria-labelledby="livres" data-test="founder-books">
            <div class="mx-auto max-w-5xl">
                <div class="max-w-3xl" data-reveal>
                    <span class="eyebrow">Livres</span>
                    <h2 id="livres" class="mt-4 text-2xl font-bold text-white sm:text-3xl">Ses livres</h2>
                </div>
                <ul class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($books as $book)
                        <li class="card flex items-center gap-4 p-4" data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30"><flux:icon.book-open class="size-5" /></span>
                            <span class="font-semibold text-white">{{ $book }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- Services --}}
    @if ($services)
        <section class="border-y border-veil/10 bg-veil/[0.02]" aria-labelledby="services" data-test="founder-services">
            <div class="section">
                <div class="mx-auto max-w-5xl">
                    <div class="max-w-3xl" data-reveal>
                        <span class="eyebrow">Services</span>
                        <h2 id="services" class="mt-4 text-2xl font-bold text-white sm:text-3xl">Ce qu'il propose</h2>
                    </div>
                    <div class="mt-8 grid gap-4 md:grid-cols-3">
                        @foreach ($services as $service)
                            <div class="card p-6" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                                <span class="text-sm font-bold text-gold-300">0{{ $loop->iteration }}</span>
                                <h3 class="mt-3 text-lg font-semibold text-white">{{ $service['title'] }}</h3>
                                @if ($service['text'] !== '')
                                    <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ Founder::display($service['text']) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row" data-reveal>
                        <a href="{{ route('formations.index') }}" class="btn-gold">Découvrir les formations</a>
                        <a href="{{ route('business') }}" class="btn-ghost">Entreprises : site web, formation</a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Contact et liens --}}
    <section id="contact" class="section scroll-mt-20" aria-labelledby="contact-titre" data-test="founder-contact">
        <div class="mx-auto max-w-3xl" data-reveal>
            <span class="eyebrow">Contact</span>
            <h2 id="contact-titre" class="mt-4 text-2xl font-bold text-white sm:text-3xl">Contacter {{ $nickname ?: $name }}</h2>
            <ul class="mt-6 space-y-3">
                @if ($whatsapp !== '' && $whatsappUrl)
                    <li>
                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="card flex items-center gap-4 p-4 hover:bg-veil/5" data-test="founder-whatsapp">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-400/30"><x-site.whatsapp-icon class="size-5" /></span>
                            <span class="min-w-0"><span class="block text-xs text-slate-400">WhatsApp</span><span class="block font-semibold text-white">{{ $whatsapp }}</span></span>
                        </a>
                    </li>
                @endif
                @if ($email !== '')
                    <li>
                        <a href="mailto:{{ $email }}" class="card flex items-center gap-4 p-4 hover:bg-veil/5" data-test="founder-email">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30"><flux:icon.envelope class="size-5" /></span>
                            <span class="min-w-0"><span class="block text-xs text-slate-400">E-mail</span><span class="block font-semibold break-all text-white">{{ $email }}</span></span>
                        </a>
                    </li>
                @endif
                @if ($office !== '')
                    <li>
                        <a href="{{ config('site.contact.map_url') }}" target="_blank" rel="noopener" class="card flex items-center gap-4 p-4 hover:bg-veil/5" data-test="founder-office">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30"><flux:icon.map-pin class="size-5" /></span>
                            <span class="min-w-0"><span class="block text-xs text-slate-400">Bureau</span><span class="block font-semibold text-white">{{ $office }}</span></span>
                        </a>
                    </li>
                @endif
            </ul>

            @if ($links)
                <h3 class="mt-10 text-lg font-semibold text-white">Le suivre</h3>
                <ul class="mt-4 flex flex-wrap gap-2" data-test="founder-links">
                    @foreach ($links as $link)
                        <li><a href="{{ $link['url'] }}" target="_blank" rel="noopener me" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10 hover:text-white">{{ $link['label'] }} <flux:icon.arrow-up-right class="size-4 text-slate-400" /></a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <x-site.cta-banner />
</x-layouts::site>
