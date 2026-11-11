{{--
    Bourse « Créer des agents IA » : page de vente courte (uniquement les faits fournis par Joseph) et candidature
    PAS À PAS, une question par écran (comme un formulaire Typeform), demandée par Joseph en novembre 2026 :
    gros bouton « Je postule maintenant » dès le haut de la page, puis questions, paiement Mobile Money et capture,
    sans compte. Un seul formulaire envoyé à la fin (même route, mêmes règles côté serveur qu'avant).
--}}
@php
    use App\Http\Controllers\Site\ScholarshipController;
    use App\Scholarships\ScholarshipCampaign;
    use App\Support\Money;
    use App\Support\WhatsApp;

    $state = $counter['state'];
    $seats = $counter['seats'];
    $totalSeats = ScholarshipCampaign::totalSeats();
    $fee = ScholarshipCampaign::fee();
    $feeLabel = Money::fcfa($fee);
    $value = ScholarshipCampaign::value();
    $opensAt = ScholarshipCampaign::opensAt();
    $closesAt = ScholarshipCampaign::closesAt();
    $closesLabel = $closesAt?->translatedFormat('l j F à H \h i');
    $formationTitle = $formation->title ?? 'Créer des agents IA';
    // Espaces insécables : jamais « 100 » et « % », ni un guillemet seul, sur deux lignes (téléphone).
    $quoted = "«\u{00A0}".$formationTitle."\u{00A0}»";
    $sent = session(ScholarshipController::SENT);
    $remaining = $counter['remaining'];
    $preview = $state !== ScholarshipCampaign::OPEN && auth()->user()?->isAdmin();
    $canApply = ! $sent && ($state === ScholarshipCampaign::OPEN || $preview);
    $genderPlural = ['female' => 'Femmes', 'male' => 'Hommes'];
    $benefits = array_values(array_filter([
        $value > 0 ? ['gift', 'Formation offerte à 100'."\u{00A0}".'%', 'Valeur : '.Money::fcfa($value)] : null,
        ['calendar-days', 'Un mois de formation en ligne', 'Vidéos enregistrées, documents et directs explicatifs et démonstratifs'],
        ['cpu-chip', '3 agents IA créés de A à Z', 'Pendant le mois, jusqu\'à l\'hébergement'],
        ['wrench-screwdriver', 'Outils pris en charge', 'Les abonnements des outils sont payés pendant la formation'],
        ['chat-bubble-left-right', 'Groupe de la formation', 'Accès au groupe de la formation sur le site'],
        ['video-camera', 'Ta vidéo de bilan', 'À la fin, tu enregistres une vidéo de bilan honnête sur ce que tu as appris'],
    ]));
@endphp

<x-layouts::site
    :title="$totalSeats.' bourses : formation « '.$formationTitle.' »'"
    :description="$totalSeats.' bourses à 100 % pour la formation « '.$formationTitle.' » : '.($seats).' femmes et '.$seats.' hommes de 20 à 40 ans. Frais d\'inscription : '.$feeLabel.'.'"
    :sticky-cta="$canApply"
>
    <div x-data="scholarshipCounter(@js(['counter' => $counter, 'opensAt' => $opensAt?->toIso8601String(), 'endpoint' => $state === ScholarshipCampaign::OFF ? null : route('scholarship.places')]))">
        <div x-data="scholarshipApply(@js(['autoOpen' => $canApply && $errors->any()]))">

            {{-- En-tête : promesse, compte à rebours, places restantes et bouton principal, dans le premier écran --}}
            <section class="relative isolate overflow-hidden">
                <div class="glow pointer-events-none absolute inset-0 -z-10"></div>
                <div class="mx-auto max-w-3xl px-4 pt-8 pb-10 text-center sm:px-6 sm:pt-14 sm:pb-14">
                    <p class="inline-flex items-center gap-2 rounded-full bg-gold-400/10 px-3 py-1 text-xs font-bold tracking-wide text-gold-100 uppercase ring-1 ring-gold-400/50">
                        <flux:icon.sparkles class="size-3.5" /> Bourse · {{ $totalSeats }} places
                    </p>
                    <h1 class="mt-4 text-[1.9rem] leading-tight font-extrabold text-white sm:text-5xl">{{ $totalSeats.' bourses à 100'."\u{00A0}".'% — Formation '.$quoted }}</h1>
                    <p class="mx-auto mt-3 max-w-xl text-base text-slate-300 sm:text-lg">{{ $seats }} femmes et {{ $seats }} hommes de 20 à 40 ans suivent la formation pendant un mois.</p>

                    @if ($state === ScholarshipCampaign::OFF)
                        <x-site.alert type="error" class="mt-5 text-left">Aperçu réservé à l'admin : la bourse est éteinte (Administration &gt; Bourse Agents IA).</x-site.alert>
                    @endif

                    {{-- Compte à rebours et places restantes (remis à jour toutes les 20 secondes) --}}
                    <div class="mx-auto mt-6 max-w-md rounded-3xl bg-night-900 p-4 shadow-[inset_0_0_0_1px_var(--color-night-600)]" data-test="scholarship-counter">
                        <template x-if="state === 'open'">
                            <p class="flex items-baseline justify-center gap-2 text-sm text-slate-300">
                                Clôture dans <span class="font-mono text-2xl font-bold text-gold-200 tabular-nums" x-text="countdown" data-test="scholarship-countdown"></span>
                            </p>
                        </template>
                        <template x-if="state === 'soon'">
                            <p class="text-sm font-semibold text-white">Les candidatures ouvrent bientôt<span x-show="opensLabel" x-text="' : ' + opensLabel"></span>.</p>
                        </template>
                        <template x-if="state === 'full' || state === 'closed'">
                            <p class="text-lg font-bold text-white" data-test="scholarship-closed" x-text="state === 'full' ? 'Toutes les places sont prises : candidatures closes' : 'Candidatures closes'"></p>
                        </template>
                        <dl class="mt-3 grid grid-cols-2 gap-2">
                            @foreach ($genderPlural as $gender => $label)
                                <div class="rounded-2xl bg-night-800 px-3 py-2.5 ring-1 ring-night-600">
                                    <dt class="text-xs text-slate-400">{{ $label }}</dt>
                                    <dd class="text-lg font-bold text-white tabular-nums">
                                        <span x-text="remaining.{{ $gender }}">{{ $remaining[$gender] }}</span><span class="text-sm font-semibold text-slate-400"> / {{ $seats }}</span>
                                        <span class="block text-xs font-normal text-slate-400" x-text="remaining.{{ $gender }} > 1 ? 'places restantes' : (remaining.{{ $gender }} === 1 ? 'place restante' : 'plus de place')">places restantes</span>
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    <div id="candidature" class="mt-6 scroll-mt-24" data-sticky-hide>
                        @if ($sent)
                            <div class="mx-auto max-w-md rounded-3xl bg-night-900 p-6 text-center shadow-[inset_0_0_0_1px_var(--color-emerald-500)]" data-test="scholarship-sent" role="status">
                                <span class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-500/15 ring-1 ring-emerald-400/60"><flux:icon.check class="size-7 text-emerald-300" /></span>
                                <h2 class="mt-4 text-2xl font-bold text-white">Candidature envoyée{{ is_string($sent) ? ', '.$sent : '' }} !</h2>
                                <p class="mt-3 text-slate-200">Ta place est réservée pendant que nous vérifions ton paiement. Tu reçois la réponse par e-mail{{ $closesLabel ? ', au plus tard après la clôture ('.$closesLabel.', heure de Lomé)' : '' }}.</p>
                                <p class="mt-2 text-sm text-slate-400">Pense à regarder aussi dans tes courriers indésirables (spam).</p>
                            </div>
                        @elseif ($canApply)
                            <button type="button" @click="start()" class="btn-gold btn-shine w-full max-w-md !py-4 text-lg" x-show="state === 'open' || @js($preview)" data-test="scholarship-apply" data-ama-clear>
                                Je postule maintenant <flux:icon.arrow-right class="size-5" />
                            </button>
                            <p class="mt-3 text-sm text-slate-300">Frais d'inscription : <strong class="text-white">{{ $feeLabel }}</strong> · Sans créer de compte</p>
                            <p class="mt-1 text-sm text-slate-400">Premiers payés, premiers servis. Remboursé si tu n'es pas retenu(e).</p>
                            <p class="mx-auto mt-3 max-w-md text-sm font-semibold text-gold-100">En échange : à la fin de la formation, tu crées gratuitement 3 agents IA pour Coach Joseph.</p>
                            @if ($preview)
                                <x-site.alert type="error" class="mx-auto mt-4 max-w-md text-left">Aperçu réservé à l'admin : les candidatures ne sont pas ouvertes, l'envoi sera refusé.</x-site.alert>
                            @endif
                        @else
                            <div class="mx-auto max-w-md rounded-3xl bg-night-900 p-6 shadow-[inset_0_0_0_1px_var(--color-night-600)]" data-test="scholarship-form-closed">
                                <h2 class="text-xl font-bold text-white">{{ match ($state) { ScholarshipCampaign::FULL => 'Toutes les places sont prises', ScholarshipCampaign::SOON => 'Les candidatures ne sont pas encore ouvertes', default => 'Candidatures closes' } }}</h2>
                                <p class="mt-2 text-slate-300">Une question ? Écris-nous sur WhatsApp.</p>
                                <a href="{{ WhatsApp::url() }}" target="_blank" rel="noopener" class="btn-ghost mt-4"><x-site.whatsapp-icon class="size-5" /> Écrire à l'école</a>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Condition principale (Joseph, novembre 2026) : la contrepartie de la bourse, mise en avant --}}
            <section class="px-4 pb-12 sm:px-6" data-test="scholarship-main-condition">
                <div class="mx-auto max-w-3xl rounded-3xl bg-night-900 p-6 text-center shadow-[inset_0_0_0_2px_var(--color-gold-500)] sm:p-8">
                    <p class="inline-flex items-center gap-1.5 rounded-full bg-gold-400/10 px-3 py-1 text-xs font-bold tracking-wide text-gold-100 uppercase ring-1 ring-gold-400/50"><flux:icon.star class="size-3.5" /> La condition principale</p>
                    <h2 class="mt-3 text-2xl font-bold text-white sm:text-3xl">En échange : 3 agents IA pour Coach Joseph</h2>
                    <p class="mx-auto mt-3 max-w-xl text-slate-200">En échange de la bourse, à la fin de la formation, tu crées gratuitement 3 agents IA pour Coach Joseph, correctement et de A à Z. C'est lui qui te dit quels agents créer.</p>
                </div>
            </section>

            {{-- Ce que tu reçois : six cartes courtes --}}
            <section class="px-4 pb-12 sm:px-6" data-test="scholarship-offer">
                <div class="mx-auto max-w-5xl">
                    <h2 class="text-center text-2xl font-bold text-white sm:text-3xl">Ce que tu reçois</h2>
                    <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($benefits as [$icon, $title, $text])
                            <li class="flex gap-4 rounded-3xl bg-night-900 p-5 shadow-[inset_0_0_0_1px_var(--color-night-700)]">
                                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-gold-400/10 ring-1 ring-gold-400/40">
                                    <flux:icon :name="$icon" class="size-5 text-gold-200" />
                                </span>
                                <span>
                                    <span class="block font-semibold text-white">{{ $title }}</span>
                                    <span class="mt-0.5 block text-sm text-slate-400">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- Comment ça marche : les trois étapes réelles --}}
            <section class="px-4 pb-12 sm:px-6" data-test="scholarship-steps">
                <div class="mx-auto max-w-3xl">
                    <h2 class="text-center text-2xl font-bold text-white sm:text-3xl">Comment postuler</h2>
                    <ol class="mt-6 space-y-3">
                        @foreach ([
                            ['Réponds aux questions', 'Une question à la fois, sans créer de compte.'],
                            ['Paie '.$feeLabel.' par Mobile Money', 'Montant, numéro et nom du bénéficiaire affichés, avec « Copier ».'],
                            ['Envoie la capture de ton paiement', 'Ta place est réservée. Réponse par e-mail ; si tu n\'es pas retenu(e), tes '.$feeLabel.' te sont remboursés.'],
                        ] as $index => [$title, $text])
                            <li class="flex gap-4 rounded-3xl bg-night-900 p-5 shadow-[inset_0_0_0_1px_var(--color-night-700)]">
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-gold-400/10 text-sm font-bold text-gold-100 ring-1 ring-gold-400/60" aria-hidden="true">{{ $index + 1 }}</span>
                                <span>
                                    <span class="block font-semibold text-white">{{ $title }}</span>
                                    <span class="mt-0.5 block text-sm text-slate-400">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>

                    {{-- Conditions complètes, repliées pour garder la page légère --}}
                    <details id="conditions" class="group mt-6 scroll-mt-24 rounded-3xl bg-night-900 shadow-[inset_0_0_0_1px_var(--color-night-700)]" data-test="scholarship-conditions">
                        <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold text-white">
                            Conditions de la bourse
                            <flux:icon.chevron-down class="size-5 text-slate-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="px-5 pb-5">
                            @include('site.scholarship.partials.conditions')
                        </div>
                    </details>

                    @if ($canApply)
                        <div class="mt-8 text-center" data-sticky-hide>
                            <button type="button" @click="start()" class="btn-gold w-full max-w-md !py-4 text-lg" data-test="scholarship-apply-bottom" data-ama-clear>
                                Je postule maintenant <flux:icon.arrow-right class="size-5" />
                            </button>
                            <p class="mt-2 text-sm text-slate-400" x-show="state === 'open'">Il reste <span class="font-semibold text-white" x-text="remaining.female + remaining.male">{{ array_sum($remaining) }}</span> places sur {{ $totalSeats }}.</p>
                        </div>
                    @endif
                </div>
            </section>

            <x-site.cta-banner title="Une question sur la bourse ?" text="Écris-nous sur WhatsApp, nous te répondons.">
                <a href="{{ WhatsApp::url() }}" target="_blank" rel="noopener" class="btn-gold"><x-site.whatsapp-icon class="size-5" /> Écrire sur WhatsApp</a>
            </x-site.cta-banner>

            @if ($canApply)
                {{-- Barre fixe sur téléphone : le bouton reste à portée quand ceux de la page ne sont plus à l'écran --}}
                <div x-data="packsStickyBar" x-show="away && !open" x-cloak x-transition.opacity class="fixed inset-x-0 bottom-0 z-30 bg-night-900/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-1px_0_var(--color-night-700)] backdrop-blur lg:hidden" data-bottom-bar data-install-avoid data-test="scholarship-sticky-bar">
                    <div class="mx-auto flex max-w-md items-center gap-3">
                        <p class="min-w-0 flex-1 text-sm text-slate-300"><span class="font-bold text-white" x-text="remaining.female + remaining.male">{{ array_sum($remaining) }}</span> places restantes</p>
                        <button type="button" @click="start()" class="btn-gold shrink-0 !px-5 !py-3">Je postule</button>
                    </div>
                </div>

                @include('site.scholarship.partials.apply')
            @endif
        </div>
    </div>
</x-layouts::site>
