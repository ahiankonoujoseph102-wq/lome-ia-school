@php
    use App\Support\YouTube;

    $business = (array) config('site.business');
    $videoId = YouTube::id($business['video_url'] ?? null);
    $vertical = YouTube::format($business['video_url'] ?? null, $business['video_format'] ?? null) === 'vertical';
    // Même réservation que la consultation gratuite, avec « Une entreprise » présélectionné (modifiable).
    $bookingUrl = route('consultation', ['profil' => 'entreprise']).'#reserver';
    $bookingNote = 'Appel vidéo gratuit de '.config('site.consultation.duration').' sur Google Meet, au créneau de votre choix.';
    // Réalisations sur mesure (« Nous le faisons pour vous », novembre 2026) : textes de l'admin (site.custom_work).
    $customWork = (array) config('site.custom_work');
    $workItems = array_values(array_filter((array) ($customWork['items'] ?? []), fn ($item): bool => is_array($item) && filled($item['title'] ?? null)));
    $workIcons = ['window', 'computer-desktop', 'cpu-chip', 'arrow-path', 'sparkles', 'cog-6-tooth'];
    $quoteServices = (array) ($customWork['services'] ?? []);
@endphp

<x-layouts::site
    title="Entreprises et réalisations sur mesure"
    description="Sites vitrines, sites professionnels, agents IA et automatisations réalisés pour vous, formation de vos équipes et intégration de l'intelligence artificielle dans votre organisation. Demandez un devis."
>
    @include('site.partials.page-header', [
        'eyebrow' => 'Entreprises et réalisations sur mesure',
        'title' => $business['title'] ?? 'Entreprises et réalisations sur mesure',
    ])

    <section class="mx-auto max-w-4xl px-4 sm:px-6" data-test="business-page">
        @if ($videoId)
            {{-- Vidéo (x-site.lazy-youtube) : seule la miniature est chargée, le lecteur YouTube au toucher.
                 Verticale : miniature 16:9 en grand, puis cadre 9:16 au toucher (sans bandes noires). --}}
            <x-site.lazy-youtube
                :video-id="$videoId"
                title="Présentation vidéo : accompagnement des entreprises et des centres de formation"
                :vertical="$vertical"
                test="business-video"
                data-test="business-video"
                data-format="{{ $vertical ? 'vertical' : 'horizontal' }}"
                data-reveal
            />
        @endif

        <div @class(['space-y-4 text-base leading-relaxed text-slate-300 sm:text-lg', 'mt-10' => $videoId]) data-reveal>
            @foreach ((array) ($business['intro'] ?? []) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row" data-reveal>
            <a href="#devis" class="btn-gold w-full sm:w-auto" data-test="business-quote-button">
                <flux:icon.document-text class="size-5" /> Demander un devis
            </a>
            <a href="{{ $bookingUrl }}" class="btn-ghost w-full sm:w-auto" data-test="business-booking">
                <flux:icon.calendar-days class="size-5" /> Réserver un appel gratuit
            </a>
        </div>
        <p class="mt-3 text-sm text-slate-400" data-reveal>{{ $bookingNote }}</p>
    </section>

    {{-- Nous le faisons pour vous : réalisations sur mesure --}}
    @if ($workItems)
        <section class="section" data-test="business-custom-work">
            <x-site.section-heading eyebrow="Réalisations sur mesure" :title="$customWork['title'] ?? 'Nous le faisons pour vous'" :subtitle="$customWork['intro'] ?? null" />
            <ul class="mx-auto mt-10 grid max-w-5xl gap-4 sm:grid-cols-2">
                @foreach ($workItems as $item)
                    <li class="card card-hover flex gap-4 p-5 sm:p-6" data-reveal style="--reveal-delay: {{ $loop->index * 70 }}ms">
                        <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-night-800 text-gold-300 shadow-[0_0_0_1.5px_var(--color-gold-500)]">
                            <flux:icon :icon="$workIcons[$loop->index % count($workIcons)]" class="size-6" />
                        </span>
                        <span>
                            <span class="block text-lg font-semibold text-white">{{ $item['title'] }}</span>
                            <span class="mt-1 block leading-relaxed text-slate-300">{{ $item['text'] ?? '' }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-8 text-center" data-reveal>
                <a href="#devis" class="btn-gold">Demander un devis <flux:icon.arrow-right class="size-5" /></a>
            </div>
        </section>
    @endif

    <section class="section">
        <x-site.section-heading eyebrow="Accompagnement" title="Former vos équipes et intégrer l'IA" />
        <ul class="mx-auto mt-10 grid max-w-4xl gap-3 sm:grid-cols-2" data-test="business-services">
            @foreach ((array) ($business['services'] ?? []) as $service)
                <li class="flex items-start gap-3 rounded-2xl bg-veil/[0.04] p-4 ring-1 ring-veil/10" data-reveal style="--reveal-delay: {{ min($loop->index, 6) * 40 }}ms">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-electric-500/15 text-electric-200 ring-1 ring-electric-400/30">
                        <flux:icon.check class="size-5" />
                    </span>
                    <span class="pt-1.5 text-slate-100">{{ $service }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Demander un devis (formulaire, sans compte ; demandes dans l'admin « Demandes de devis ») --}}
    <section id="devis" class="section !pt-0 scroll-mt-20" data-test="business-quote">
        <div class="mx-auto max-w-3xl rounded-[2rem] bg-night-900 p-6 shadow-[inset_0_0_0_1px_var(--color-night-600)] sm:p-10" data-reveal>
            <h2 class="text-2xl font-bold tracking-tight text-balance text-white sm:text-3xl">Demander un devis</h2>
            <p class="mt-3 text-slate-300">Décrivez votre projet en quelques lignes : nous étudions votre demande et vous répondons par e-mail ou sur WhatsApp.</p>

            @if (session('quote_sent'))
                <x-site.alert class="mt-6" data-test="business-quote-sent">{{ session('quote_sent') }}</x-site.alert>
            @else
                <form method="POST" action="{{ route('business.quote') }}" class="relative mt-6 space-y-5" novalidate>
                    @csrf
                    @if ($errors->any())
                        <x-site.alert type="error">Vérifiez les champs signalés.</x-site.alert>
                    @endif

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="devis-name" class="field-label">Votre nom</label>
                            <input id="devis-name" type="text" name="name" maxlength="120" autocomplete="name" required value="{{ old('name') }}" @class(['field-input', 'field-input-error' => $errors->has('name')])>
                            @error('name') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="devis-organization" class="field-label">Entreprise ou structure (facultatif)</label>
                            <input id="devis-organization" type="text" name="organization" maxlength="160" autocomplete="organization" value="{{ old('organization') }}" class="field-input">
                        </div>
                        <div class="space-y-1.5">
                            <label for="devis-email" class="field-label">Adresse e-mail</label>
                            <input id="devis-email" type="email" name="email" maxlength="255" autocomplete="email" inputmode="email" required value="{{ old('email') }}" @class(['field-input', 'field-input-error' => $errors->has('email')])>
                            @error('email') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="devis-phone" class="field-label">Numéro WhatsApp (facultatif)</label>
                            <input id="devis-phone" type="tel" name="phone" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="+228 90 00 00 00" value="{{ old('phone') }}" @class(['field-input', 'field-input-error' => $errors->has('phone')])>
                            @error('phone') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="devis-service" class="field-label">De quoi avez-vous besoin ?</label>
                        <select id="devis-service" name="service" required @class(['field-input', 'field-input-error' => $errors->has('service')])>
                            <option value="">Choisissez</option>
                            @foreach ($quoteServices as $value => $label)
                                <option value="{{ $value }}" @selected(old('service') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('service') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="devis-message" class="field-label">Votre projet</label>
                        <textarea id="devis-message" name="message" rows="5" maxlength="3000" required placeholder="Exemple : un site pour présenter mon cabinet, avec un formulaire de prise de rendez-vous." @class(['field-input', 'field-input-error' => $errors->has('message')])>{{ old('message') }}</textarea>
                        @error('message') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                    </div>

                    {{-- Champ piège : invisible pour les personnes, rempli par les robots. --}}
                    <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                        <label for="devis-website">Ne pas remplir ce champ</label>
                        <input id="devis-website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <button type="submit" class="btn-gold w-full" data-test="business-quote-submit">Envoyer ma demande de devis</button>
                    <p class="text-center text-xs text-slate-400">Vos informations servent uniquement à répondre à votre demande (voir la <a href="{{ route('legal.privacy') }}" class="underline underline-offset-2 hover:text-white">politique de confidentialité</a>).</p>
                </form>
            @endif
        </div>
    </section>

    <x-site.cta-banner title="Vous préférez en parler de vive voix ?" text="Réservez un appel gratuit : nous verrons ensemble comment avancer.">
        <a href="{{ $bookingUrl }}" class="btn-gold"><flux:icon.calendar-days class="size-5" /> Réserver un appel gratuit</a>
    </x-site.cta-banner>
</x-layouts::site>
