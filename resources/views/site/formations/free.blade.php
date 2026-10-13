{{--
    Page d'une formation gratuite (prix 0 FCFA) : inscription avec choix d'une option à places limitées.
    Aucun prix de vente, aucune garantie de remboursement, aucun bouton « Acheter ». La page des formations
    payantes (show.blade.php) n'est pas utilisée ici.
    Formation terminée (interrupteur de l'admin) : pastille « Formation terminée », ni prix, ni options, ni places, ni
    annulation ; bouton réglé dans l'admin (« Découvrir l'accompagnement » par défaut) à la place de l'inscription,
    masqué si sa destination est /accompagnement et qu'aucune offre n'est en vente.
    Style stable face au mode sombre forcé : fonds foncés ou translucides, textes clairs, contours en « ring ».
--}}
@php
    use App\Support\FormationShare;
    use App\Support\Placeholder;
    use App\Support\WhatsApp;
    use Illuminate\Support\Str;

    $user = auth()->user();
    // Formation terminée (interrupteur de l'admin) : page au passé, aucun appel à s'inscrire, bouton vers
    // l'accompagnement (ou la destination réglée) ; null si aucune offre n'est en vente.
    $ended = $formation->isFreeEnded();

    // Photo d'illustration en grand en haut de la page (sinon : visuel à droite, comme avant).
    $heroPhoto = \App\Support\FormationPhotos::for($formation);
    $endedButton = $formation->endedButton();
    $confirmed = $user && $user->hasConfirmedEmail();
    $allFull = $options->isNotEmpty() && $availableOptions->isEmpty();
    // « Je m'inscris » : création du compte d'abord (visiteur), confirmation de l'e-mail, puis choix de l'option.
    $registerUrl = match (true) {
        $registration !== null || $closed || $allFull => route('formations.show', $formation).'#inscription',
        $confirmed && $availableOptions->isNotEmpty() => route('free-registration.options', $formation),
        $confirmed => route('formations.show', $formation).'#inscription',
        default => route('free-registration.start', $formation),
    };
    $registerIcon = str_contains($registerUrl, '#') ? 'arrow-down' : 'arrow-right';
    // Compte créé mais pas encore inscrit : rappel bien visible en haut de la page.
    $showPendingReminder = $user && $registration === null && ! $closed && $availableOptions->isNotEmpty();
    $whatsappMessage = \App\Support\WhatsAppMessages::free($formation);
    $trainer = config('site.trainer');
    $date = $formation->eventDateLabel();
    $hasOnsite = $options->contains(fn ($option) => $option->isOnsite());
    $hasOnline = $options->contains(fn ($option) => ! $option->isOnsite());

    // Données structurées : aucune offre ni prix de vente (formation gratuite).
    $course = [
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => $formation->title,
        'description' => Str::limit($formation->description, 300),
        'inLanguage' => 'fr',
        'url' => FormationShare::url($formation),
        'image' => FormationShare::imageUrl($formation),
        'isAccessibleForFree' => true,
        'provider' => ['@type' => 'EducationalOrganization', 'name' => config('app.name'), 'url' => \App\Support\AppUrl::to(route('home'))],
    ];

    $included = array_values(array_filter([
        $hasOnsite && $hasOnline ? 'La formation, en présentiel à Lomé ou en ligne sur Google Meet, selon l\'option choisie' : null,
        'L\'accès au groupe de discussion des inscrits',
        'Un e-mail de confirmation avec votre option, la date et les horaires',
    ]));

    $faq = array_values(array_filter([
        ['question' => 'Faut-il payer quelque chose ?', 'answer' => 'Non, la formation est gratuite. Il suffit de créer votre compte, de confirmer votre adresse e-mail puis de choisir votre option.'],
        $options->count() > 1 ? ['question' => 'Puis-je changer d\'option ?', 'answer' => 'Oui : annulez votre inscription sur cette page, puis inscrivez-vous de nouveau en choisissant une autre option, tant qu\'il y reste des places.'] : null,
        $hasOnline ? ['question' => 'Où vais-je recevoir le lien Google Meet ?', 'answer' => 'Le lien Google Meet sera publié dans le groupe des inscrits, que vous rejoignez dès votre inscription.'] : null,
        $hasOnsite && $formation->event_location ? ['question' => 'Où a lieu le présentiel ?', 'answer' => $formation->event_location.'.'] : null,
        ['question' => 'Je ne peux plus venir : que faire ?', 'answer' => 'Annulez votre inscription sur cette page : votre place est aussitôt libérée pour quelqu\'un d\'autre.'],
    ]));
@endphp

<x-layouts::site
    :title="$formation->title"
    :description="Str::limit($formation->subtitle ?: $formation->description, 155)"
    :image="FormationShare::imageUrl($formation)"
    :image-type="FormationShare::imageType($formation)"
    :json-ld="[$course]"
    :sticky-cta="true"
    :whatsapp-message="$whatsappMessage"
    :noindex="! $formation->is_active"
>
    @unless ($formation->is_active)
        <div class="bg-night-900 px-4 py-3 text-center text-sm text-gold-200 shadow-[inset_0_-1px_0_var(--color-gold-500)]" data-test="free-draft-notice">
            Formation masquée : seule l'administration voit cette page. Publiez-la dans l'admin (Formations) pour ouvrir les inscriptions.
        </div>
    @endunless

    @if ($showPendingReminder)
        <div class="mx-auto max-w-6xl px-4 pt-4 sm:px-6" data-ama-avoid data-ama-clear data-install-avoid>
            <div class="flex flex-col gap-3 rounded-2xl bg-night-900 p-4 ring-2 ring-gold-500/80 sm:flex-row sm:items-center sm:gap-4" role="status" data-test="free-pending-reminder">
                <span class="hidden size-10 shrink-0 place-items-center rounded-full bg-gold-500/10 text-gold-200 ring-1 ring-gold-500/60 ring-inset sm:grid">
                    <flux:icon.bell-alert class="size-5" />
                </span>
                <p class="min-w-0 flex-1 text-base font-semibold text-white">
                    Votre compte est créé, mais vous n'êtes pas encore inscrit.
                    <span class="text-gold-200">{{ $confirmed ? 'Choisissez votre option.' : 'Confirmez votre e-mail, puis choisissez votre option.' }}</span>
                </p>
                <a href="{{ $confirmed ? route('free-registration.options', $formation) : route('free-registration.start', $formation) }}" class="btn-gold w-full shrink-0 sm:w-auto" data-test="free-pending-button">
                    {{ $confirmed ? 'Choisir mon option' : 'Confirmer mon e-mail' }} <flux:icon.arrow-right class="size-5" />
                </a>
            </div>
        </div>
    @endif

    {{-- En-tête de la formation --}}
    <section class="relative isolate overflow-hidden" @if ($heroPhoto) data-test="formation-photo-hero" @endif>
        @if ($heroPhoto)
            @include('site.formations.partials.photo-hero-background', ['photo' => $heroPhoto])
        @else
            <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
            <div class="grid-pattern pointer-events-none absolute inset-0 -z-10"></div>
        @endif

        <div class="mx-auto max-w-6xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10 lg:pb-16">
            <nav aria-label="Fil d'Ariane" class="text-sm text-slate-400">
                <ol class="flex flex-wrap items-center gap-1.5">
                    <li><a href="{{ route('home') }}" class="hover:text-white">Accueil</a></li>
                    <li aria-hidden="true">/</li>
                    <li><a href="{{ route('formations.index') }}" class="hover:text-white">Formations</a></li>
                </ol>
            </nav>

            <div @class(['mt-6 grid items-center gap-8 lg:gap-12', 'lg:grid-cols-[1.15fr_0.85fr]' => ! $heroPhoto, 'pt-10 sm:pt-16 lg:pt-24' => $heroPhoto])>
                <div @class(['order-2 lg:order-1', 'max-w-3xl' => $heroPhoto])>
                    <span class="eyebrow" data-reveal data-test="free-eyebrow">
                        @if ($ended)
                            <flux:icon.check-badge class="size-3.5" /> Formation terminée
                        @else
                            <flux:icon.gift class="size-3.5" /> Formation gratuite
                        @endif
                    </span>
                    <h1 class="mt-4 text-3xl leading-tight font-bold tracking-tight text-balance text-white sm:text-5xl" data-reveal style="--reveal-delay: 60ms">
                        {{ $formation->title }}
                    </h1>
                    @if ($formation->subtitle)
                        <p class="mt-4 text-lg text-pretty text-slate-300" data-reveal style="--reveal-delay: 120ms">{{ $formation->subtitle }}</p>
                    @endif

                    <ul class="mt-5 space-y-2 text-slate-200" data-reveal style="--reveal-delay: 160ms" data-test="free-event-facts">
                        @if ($date)
                            <li class="flex items-center gap-2"><flux:icon.calendar-days class="size-5 shrink-0 text-gold-400" /> {{ $date }}</li>
                        @endif
                        @if ($hasOnsite && $formation->event_location)
                            <li class="flex items-center gap-2"><flux:icon.map-pin class="size-5 shrink-0 text-gold-400" /> En présentiel : {{ $formation->event_location }}</li>
                        @endif
                        @if ($hasOnline)
                            <li class="flex items-center gap-2"><flux:icon.video-camera class="size-5 shrink-0 text-gold-400" /> En ligne sur Google Meet</li>
                        @endif
                    </ul>

                    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center" data-reveal style="--reveal-delay: 200ms">
                        @unless ($ended)
                            <p class="text-4xl font-bold text-gold-300" data-test="free-price">Gratuit</p>
                        @endunless
                        <div @class(['flex flex-col gap-3 sm:flex-row', 'sm:ml-auto lg:ml-8' => ! $ended])>
                            @if ($ended)
                                @if ($endedButton)
                                    <a href="{{ $endedButton['url'] }}" class="btn-gold !py-4 text-lg" data-test="free-ended-button" data-ama-avoid data-ama-clear data-install-avoid>{{ $endedButton['label'] }} <flux:icon.arrow-right class="size-5" /></a>
                                @endif
                            @else
                                <a href="{{ $registerUrl }}" class="btn-gold" data-test="free-hero-register">{{ $registration ? 'Voir mon inscription' : 'Je m\'inscris gratuitement' }} <flux:icon :icon="$registerIcon" class="size-5" /></a>
                            @endif
                            <a href="{{ WhatsApp::url($whatsappMessage) }}" target="_blank" rel="noopener" class="btn-ghost">
                                <x-site.whatsapp-icon class="size-5 text-[#25D366]" /> Poser une question
                            </a>
                        </div>
                    </div>

                    @if ($formation->is_active)
                        <x-site.share-formation :formation="$formation" class="mt-4" data-reveal style="--reveal-delay: 220ms" />
                    @endif
                </div>

                @unless ($heroPhoto)
                    <div class="order-1 lg:order-2" data-reveal>
                        <x-site.formation-cover :formation="$formation" class="aspect-[16/10] rounded-3xl shadow-2xl shadow-electric-900/50 ring-1 ring-veil/10" eager />
                    </div>
                @endunless
            </div>
        </div>
    </section>

    <div class="mx-auto grid max-w-6xl gap-12 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_380px] lg:py-20">
        <div class="min-w-0 space-y-16">
            {{-- Vidéo de présentation (si réglée dans l'admin), puis inscription : choix de l'option, places restantes --}}
            <div>
                @include('site.formations.partials.free-video')

                <section id="inscription" class="scroll-mt-2">
                    @include('site.formations.partials.free-registration')
                </section>
            </div>

            {{-- Présentation --}}
            <section data-reveal>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Présentation</h2>
                <p class="mt-4 text-base leading-relaxed text-pretty text-slate-300 sm:text-lg">{{ $formation->description }}</p>
            </section>

            {{-- Ce que vous allez apprendre --}}
            @if ($formation->outcomes)
                <section>
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>{{ $ended ? 'Au programme de la formation' : 'Ce que vous allez apprendre' }}</h2>
                    <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach ($formation->outcomes as $outcome)
                            <li class="card flex gap-3 p-4" data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-electric-500/20 text-electric-300">
                                    <flux:icon.check class="size-4" />
                                </span>
                                <span class="text-slate-200">{{ $outcome }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Pour qui --}}
            @if ($formation->audience)
                <section>
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Pour qui est cette formation ?</h2>
                    <ul class="mt-6 space-y-3">
                        @foreach ($formation->audience as $person)
                            <li class="flex items-start gap-3 text-slate-200" data-reveal>
                                <flux:icon.user-group class="mt-0.5 size-5 shrink-0 text-gold-400" /> {{ $person }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Inclus (masqué une fois la formation terminée : il invite à s'inscrire) --}}
            @unless ($ended)
            <section class="card p-6 sm:p-8" data-reveal data-test="free-included">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-white">
                    <flux:icon.check-badge class="size-7 text-gold-300" /> Inclus avec l'inscription
                </h2>
                <ul class="mt-5 space-y-3">
                    @foreach ($included as $item)
                        <li class="flex items-start gap-3 text-slate-200">
                            <flux:icon.check-circle class="mt-0.5 size-5 shrink-0 text-gold-300" /> {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </section>
            @endunless

            {{-- Formateur --}}
            <section data-reveal>
                <h2 class="text-2xl font-bold text-white sm:text-3xl">Votre formateur</h2>
                <div class="card mt-6 flex flex-col gap-5 p-6 sm:flex-row sm:items-center">
                    @include('site.partials.trainer-photo', ['size' => 'size-20'])
                    <div>
                        <p class="text-lg font-semibold text-white">{{ $trainer['name'] }}</p>
                        <p class="text-sm text-electric-300">{{ $trainer['role'] }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-300">{{ Placeholder::highlight($trainer['short_bio']) }}</p>
                        <a href="{{ route('about') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-electric-300 hover:text-white">
                            En savoir plus <flux:icon.arrow-right class="size-4" />
                        </a>
                    </div>
                </div>
            </section>

            {{-- Témoignages : les mêmes que sur l'accueil et les autres pages (gérés dans l'admin), masqués s'il n'y en a aucun --}}
            @if ($testimonials = \App\Support\Testimonials::all())
                <section data-test="free-testimonials">
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Ce qu'en disent les élèves</h2>
                    <x-site.testimonials :items="$testimonials" class="mt-6 lg:!columns-2" />
                </section>
            @endif

            {{-- Questions fréquentes propres à l'inscription gratuite (masquées une fois la formation terminée) --}}
            @unless ($ended)
                <section>
                    <h2 class="text-2xl font-bold text-white sm:text-3xl" data-reveal>Questions fréquentes</h2>
                    <x-site.faq :items="$faq" class="mt-6" data-test="free-faq" />
                </section>
            @endunless
        </div>

        {{-- Récapitulatif (écrans larges) --}}
        <aside class="hidden lg:block">
            <div class="card sticky top-24 space-y-5 p-6">
                <p @class(['font-bold text-gold-300', 'text-4xl' => ! $ended, 'text-2xl' => $ended])>{{ $ended ? 'Formation terminée' : 'Gratuit' }}</p>
                @if ($date)
                    <p class="-mt-3 flex items-center gap-2 text-sm text-slate-300"><flux:icon.calendar-days class="size-4 text-gold-400" /> {{ $date }}</p>
                @endif
                @if ($ended)
                    @if ($endedButton)
                        <a href="{{ $endedButton['url'] }}" class="btn-gold w-full">{{ $endedButton['label'] }}</a>
                    @endif
                @else
                    <a href="{{ $registerUrl }}" class="btn-gold w-full">{{ $registration ? 'Voir mon inscription' : 'Je m\'inscris gratuitement' }}</a>
                @endif
                <div class="border-t border-veil/10 pt-5">
                    <p class="text-sm text-slate-400">Une question ?</p>
                    <a href="{{ WhatsApp::url($whatsappMessage) }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-[#25D366]">
                        <x-site.whatsapp-icon class="size-5" /> Écrire sur WhatsApp
                    </a>
                </div>
            </div>
        </aside>
    </div>

    {{-- Appel à l'action final --}}
    <x-site.cta-banner
        :title="$ended ? 'Cette formation est terminée' : 'Rejoignez « '.$formation->title.' »'"
        :text="$ended ? 'Les inscriptions sont fermées. Une question ? Écrivez-nous sur WhatsApp.' : 'Créez votre compte, confirmez votre e-mail, choisissez votre option et rejoignez le groupe des inscrits. C\'est gratuit.'"
    >
        @if ($ended)
            @if ($endedButton)
                <a href="{{ $endedButton['url'] }}" class="btn-gold">{{ $endedButton['label'] }}</a>
            @endif
        @else
            <a href="{{ $registerUrl }}" class="btn-gold">{{ $registration ? 'Voir mon inscription' : 'Je m\'inscris gratuitement' }}</a>
        @endif
        <a href="{{ WhatsApp::url($whatsappMessage) }}" target="_blank" rel="noopener" class="btn-ghost">
            <x-site.whatsapp-icon class="size-5 text-[#25D366]" /> Poser une question
        </a>
    </x-site.cta-banner>

    {{-- Autres formations --}}
    @if ($related->isNotEmpty())
        <section class="section !pt-0">
            <x-site.section-heading title="Vous aimerez aussi" align="left" />
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $item)
                    <x-site.formation-card :formation="$item" :delay="$loop->index * 70" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Barre fixée en bas de l'écran (mobile et tablette) --}}
    <x-slot:after>
        <div data-bottom-bar data-install-avoid class="fixed inset-x-0 bottom-0 z-40 border-t border-veil/10 bg-night-950/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl lg:hidden">
            <div class="mx-auto flex max-w-xl items-center gap-3">
                <div class="min-w-0 flex-1">
                    @if ($ended)
                        {{-- Formation terminée : le libellé seul (le titre est en haut de page), sur deux lignes au besoin. --}}
                        <p class="text-base leading-tight font-bold text-gold-300" data-test="free-bar-price">Formation terminée</p>
                    @else
                        <p class="truncate text-xs text-slate-400">{{ $formation->title }}</p>
                        <p class="text-lg leading-tight font-bold text-gold-300" data-test="free-bar-price">Gratuit</p>
                    @endif
                </div>
                @if ($ended)
                    @if ($endedButton)
                        <a href="{{ $endedButton['url'] }}" class="btn-gold shrink-0 !px-4 !py-3 text-sm" data-test="free-bar-ended">{{ $endedButton['label'] }}</a>
                    @endif
                @else
                    <a href="{{ $registerUrl }}" class="btn-gold shrink-0 !px-5 !py-3" data-test="free-bar-register">{{ $registration ? 'Mon inscription' : 'Je m\'inscris' }}</a>
                @endif
            </div>
        </div>
    </x-slot:after>
</x-layouts::site>
