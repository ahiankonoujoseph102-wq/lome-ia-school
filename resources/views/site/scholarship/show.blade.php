{{--
    Bourse « Créer des agents IA » : présentation (uniquement les faits fournis par Joseph), compte à rebours et places
    restantes, candidature et paiement des frais d'inscription par Mobile Money avec capture, sans compte.
--}}
@php
    use App\Models\ScholarshipApplication;
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
    $sent = session(\App\Http\Controllers\Site\ScholarshipController::SENT);
    $remaining = $counter['remaining'];
    $full = collect($remaining)->filter(fn (int $left): bool => $left === 0)->map(fn (): string => 'Plus de place')->all();
    $maxMb = intdiv((int) config('site.payments.proof_max_kb', 8192), 1024);
    $preview = $state !== ScholarshipCampaign::OPEN && auth()->user()?->isAdmin();
    $genderPlural = ['female' => 'Femmes', 'male' => 'Hommes'];
@endphp

<x-layouts::site
    :title="$totalSeats.' bourses : formation « '.$formationTitle.' »'"
    :description="$totalSeats.' bourses à 100 % pour la formation « '.$formationTitle.' » : '.($seats).' femmes et '.$seats.' hommes de 20 à 40 ans. Frais d\'inscription : '.$feeLabel.'.'"
>
    @include('site.partials.page-header', [
        'eyebrow' => 'Bourse · '.$totalSeats.' places',
        'title' => $totalSeats.' bourses à 100'."\u{00A0}".'% — Formation '.$quoted,
        'subtitle' => $seats.' femmes et '.$seats.' hommes de 20 à 40 ans suivent la formation pendant un mois. Seuls frais : '.$feeLabel.' d\'inscription.',
    ])

    @if ($state === ScholarshipCampaign::OFF)
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <x-site.alert type="error">Aperçu réservé à l'admin : la bourse est éteinte (Administration &gt; Bourse Agents IA).</x-site.alert>
        </div>
    @endif

    {{-- Compte à rebours et places restantes (remis à jour toutes les 20 secondes) --}}
    <section class="px-4 sm:px-6" aria-label="Places restantes">
        <div
            x-data="scholarshipCounter(@js(['counter' => $counter, 'opensAt' => $opensAt?->toIso8601String(), 'endpoint' => $state === ScholarshipCampaign::OFF ? null : route('scholarship.places')]))"
            class="mx-auto max-w-3xl rounded-[2rem] bg-night-900 p-5 shadow-[inset_0_0_0_1px_var(--color-night-600)] sm:p-7"
            data-test="scholarship-counter"
        >
            <template x-if="state === 'open'">
                <div class="text-center">
                    <p class="text-sm font-semibold text-slate-300">Clôture des candidatures dans</p>
                    <p class="mt-1 font-mono text-4xl font-bold text-gold-200 tabular-nums sm:text-5xl" x-text="countdown" data-test="scholarship-countdown"></p>
                </div>
            </template>
            <template x-if="state === 'soon'">
                <p class="text-center text-lg font-semibold text-white">Les candidatures ouvrent bientôt<span x-show="opensLabel" x-text="' : ' + opensLabel"></span>.</p>
            </template>
            <template x-if="state === 'full' || state === 'closed'">
                <p class="text-center text-2xl font-bold text-white" data-test="scholarship-closed" x-text="state === 'full' ? 'Toutes les places sont prises : candidatures closes' : 'Candidatures closes'"></p>
            </template>

            <dl class="mt-5 grid grid-cols-2 gap-3">
                @foreach (ScholarshipApplication::GENDERS as $gender => $label)
                    <div class="rounded-2xl bg-night-800 p-4 text-center ring-1 ring-night-600">
                        <dt class="text-sm text-slate-300">{{ $genderPlural[$gender] }}</dt>
                        <dd class="mt-1 text-3xl font-bold text-white tabular-nums">
                            <span x-text="remaining.{{ $gender }}">{{ $remaining[$gender] }}</span><span class="text-lg text-slate-400"> / {{ $seats }}</span>
                        </dd>
                        <dd class="text-xs text-slate-400" x-text="remaining.{{ $gender }} > 1 ? 'places restantes' : (remaining.{{ $gender }} === 1 ? 'place restante' : 'plus de place')">places restantes</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-4 text-center text-sm text-slate-300">Premiers payés, premiers servis : chaque paiement envoyé réserve une place pendant sa vérification par notre équipe.</p>
            @if ($closesLabel)
                <p class="mt-1 text-center text-sm text-slate-400">Clôture : {{ $closesLabel }} (heure de Lomé), ou dès que toutes les places sont prises.</p>
            @endif
        </div>
    </section>

    <section class="section">
        <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-2">
            <div class="card p-6" data-test="scholarship-offer">
                <h2 class="text-xl font-bold text-white">Ce que comprend la bourse</h2>
                <ul class="mt-4 space-y-3 text-[15px] text-slate-200">
                    @if ($value > 0)
                        <li class="flex gap-3"><flux:icon.gift class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>La formation {{ $quoted }}, d'une valeur de {{ Money::fcfa($value) }}, offerte à 100&nbsp;%.</span></li>
                    @endif
                    <li class="flex gap-3"><flux:icon.calendar-days class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Un mois de formation en ligne.</span></li>
                    <li class="flex gap-3"><flux:icon.play-circle class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Des vidéos enregistrées, des documents de formation et des directs explicatifs et démonstratifs.</span></li>
                    <li class="flex gap-3"><flux:icon.cpu-chip class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Pendant le mois, tu crées 3 agents IA de A à Z, jusqu'à l'hébergement.</span></li>
                    <li class="flex gap-3"><flux:icon.wrench-screwdriver class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Les abonnements des outils sont pris en charge pendant la formation.</span></li>
                    <li class="flex gap-3"><flux:icon.chat-bubble-left-right class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Accès au groupe de la formation sur le site.</span></li>
                    <li class="flex gap-3"><flux:icon.video-camera class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>À la fin, tu enregistres une vidéo de bilan honnête sur ce que tu as appris.</span></li>
                </ul>
            </div>

            <div id="conditions" class="card scroll-mt-24 p-6" data-test="scholarship-conditions">
                <h2 class="text-xl font-bold text-white">Conditions de la bourse</h2>
                <ul class="mt-4 space-y-3 text-[15px] text-slate-200">
                    <li class="flex gap-3"><flux:icon.users class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>{{ $totalSeats }} places : {{ $seats }} femmes et {{ $seats }} hommes.</span></li>
                    <li class="flex gap-3"><flux:icon.identification class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Avoir entre 20 et 40 ans.</span></li>
                    <li class="flex gap-3"><flux:icon.light-bulb class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Avoir un minimum de connaissances en digital et en intelligence artificielle.</span></li>
                    <li class="flex gap-3"><flux:icon.fire class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Être motivé(e), passionné(e) ou intéressé(e) par la technologie, le digital, les agents IA et la révolution actuelle de l'IA, avec un « pourquoi » fort.</span></li>
                    <li class="flex gap-3"><flux:icon.banknotes class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Payer {{ $feeLabel }} de frais d'inscription par Mobile Money et envoyer la capture du paiement avec la candidature. Les frais de transfert sont à ta charge.</span></li>
                    <li class="flex gap-3"><flux:icon.check-badge class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Premiers payés, premiers servis : ta place est réservée dès l'envoi, puis confirmée quand notre équipe a vérifié ton paiement. Une capture qui ne correspond à aucun paiement reçu est refusée et la place est libérée.</span></li>
                    <li class="flex gap-3"><flux:icon.arrow-uturn-left class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Si tu n'es pas retenu(e), tes {{ $feeLabel }} te sont remboursés.</span></li>
                    <li class="flex gap-3"><flux:icon.video-camera class="mt-0.5 size-5 shrink-0 text-gold-300" /> <span>Si tu es retenu(e), tu acceptes d'enregistrer une vidéo de bilan honnête sur ce que tu as appris, que Lomé IA School pourra publier.</span></li>
                </ul>
            </div>
        </div>
    </section>

    <section id="candidature" class="section !pt-0 scroll-mt-20">
        <div class="mx-auto max-w-3xl rounded-[2rem] bg-night-900 p-5 shadow-[inset_0_0_0_1px_var(--color-night-600)] sm:p-10">
            @if ($sent)
                <div class="text-center" data-test="scholarship-sent" role="status">
                    <span class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-500/15 ring-1 ring-emerald-400/60"><flux:icon.check class="size-7 text-emerald-300" /></span>
                    <h2 class="mt-4 text-2xl font-bold text-white">Candidature envoyée{{ is_string($sent) ? ', '.$sent : '' }} !</h2>
                    <p class="mt-3 text-slate-200">Ta place est réservée pendant que nous vérifions ton paiement. Tu reçois la réponse par e-mail{{ $closesLabel ? ', au plus tard après la clôture ('.$closesLabel.', heure de Lomé)' : '' }}.</p>
                    <p class="mt-2 text-sm text-slate-400">Pense à regarder aussi dans tes courriers indésirables (spam).</p>
                </div>
            @elseif ($state !== ScholarshipCampaign::OPEN && ! $preview)
                <div class="text-center" data-test="scholarship-form-closed">
                    <h2 class="text-2xl font-bold text-white">{{ match ($state) { ScholarshipCampaign::FULL => 'Toutes les places sont prises', ScholarshipCampaign::SOON => 'Les candidatures ne sont pas encore ouvertes', default => 'Candidatures closes' } }}</h2>
                    <p class="mt-3 text-slate-300">Une question ? Écris-nous sur WhatsApp.</p>
                    <a href="{{ WhatsApp::url() }}" target="_blank" rel="noopener" class="btn-ghost mt-5"><x-site.whatsapp-icon class="size-5" /> Écrire à l'école</a>
                </div>
            @else
                <h2 class="text-2xl font-bold text-white">Candidater</h2>
                <p class="mt-2 text-slate-300">Aucun compte à créer : remplis le formulaire, paie {{ $feeLabel }}, puis envoie la capture de ton paiement. Ton compte est créé seulement si tu es retenu(e).</p>
                @if ($preview)
                    <x-site.alert type="error" class="mt-4">Aperçu réservé à l'admin : les candidatures ne sont pas ouvertes, l'envoi sera refusé.</x-site.alert>
                @endif

                <form method="POST" action="{{ route('scholarship.store') }}" enctype="multipart/form-data" class="relative mt-6 space-y-8" novalidate x-data="{ sending: false }" @submit="sending = true" @pageshow.window="sending = false" data-test="scholarship-form">
                    @csrf
                    @if ($errors->any())
                        <x-site.alert type="error">{{ $errors->first('form') ?: 'Vérifie les champs signalés, puis choisis de nouveau ta capture.' }}</x-site.alert>
                    @endif

                    <fieldset class="space-y-5">
                        <legend class="text-lg font-semibold text-white">1. Ta candidature</legend>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-1.5 sm:col-span-2">
                                <label for="bourse-name" class="field-label">Nom et prénom</label>
                                <input id="bourse-name" type="text" name="name" maxlength="120" autocomplete="name" required value="{{ old('name') }}" @class(['field-input', 'field-input-error' => $errors->has('name')])>
                                @error('name') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="bourse-phone" class="field-label">Numéro WhatsApp</label>
                                <input id="bourse-phone" type="tel" name="phone" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="+228 90 00 00 00" required value="{{ old('phone') }}" @class(['field-input', 'field-input-error' => $errors->has('phone')])>
                                @error('phone') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="bourse-email" class="field-label">Adresse e-mail</label>
                                <input id="bourse-email" type="email" name="email" maxlength="255" autocomplete="email" inputmode="email" required value="{{ old('email') }}" @class(['field-input', 'field-input-error' => $errors->has('email')])>
                                <p class="text-xs text-slate-400">Pour recevoir la réponse et, si tu es retenu(e), ton accès.</p>
                                @error('email') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @include('site.scholarship.partials.choices', ['name' => 'gender', 'label' => 'Genre', 'options' => ScholarshipApplication::GENDERS, 'disabled' => $full])

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="bourse-age" class="field-label">Âge</label>
                                <input id="bourse-age" type="number" name="age" min="20" max="40" inputmode="numeric" required value="{{ old('age') }}" @class(['field-input', 'field-input-error' => $errors->has('age')])>
                                <p class="text-xs text-slate-400">De 20 à 40 ans.</p>
                                @error('age') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                            </div>
                            <div class="space-y-1.5">
                                <label for="bourse-city" class="field-label">Ville et pays</label>
                                <input id="bourse-city" type="text" name="city" maxlength="120" placeholder="Lomé, Togo" required value="{{ old('city') }}" @class(['field-input', 'field-input-error' => $errors->has('city')])>
                                @error('city') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @include('site.scholarship.partials.choices', ['name' => 'level', 'label' => 'Ton niveau en digital et en IA', 'options' => ScholarshipApplication::LEVELS, 'columns' => ''])

                        <div class="space-y-1.5">
                            <label for="bourse-tools" class="field-label">Outils numériques et d'IA que tu utilises déjà</label>
                            <textarea id="bourse-tools" name="tools" rows="2" maxlength="1000" required placeholder="Exemple : ChatGPT, Canva, WhatsApp Business…" @class(['field-input', 'field-input-error' => $errors->has('tools')])>{{ old('tools') }}</textarea>
                            @error('tools') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                        </div>

                        @include('site.scholarship.partials.choices', ['name' => 'device', 'label' => 'Ton matériel', 'options' => ScholarshipApplication::DEVICES])

                        <div class="space-y-1.5">
                            <label for="bourse-motivation" class="field-label">Pourquoi veux-tu apprendre à créer des agents IA ? Quel est ton grand « pourquoi » ?</label>
                            <textarea id="bourse-motivation" name="motivation" rows="5" maxlength="3000" required @class(['field-input', 'field-input-error' => $errors->has('motivation')])>{{ old('motivation') }}</textarea>
                            @error('motivation') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="bourse-first-agent" class="field-label">Quel premier agent IA aimerais-tu créer, et pour qui ?</label>
                            <textarea id="bourse-first-agent" name="first_agent" rows="4" maxlength="2000" required @class(['field-input', 'field-input-error' => $errors->has('first_agent')])>{{ old('first_agent') }}</textarea>
                            @error('first_agent') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                        </div>

                        @include('site.scholarship.partials.choices', ['name' => 'available', 'label' => 'Je peux suivre la formation pendant un mois, avec les directs', 'options' => ['yes' => 'Oui', 'no' => 'Non']])

                        <div class="space-y-3">
                            @foreach ([
                                'accepts_video' => 'Si je suis retenu(e), j\'accepte d\'enregistrer une vidéo de bilan honnête sur ce que j\'ai appris, que Lomé IA School pourra publier.',
                                'accepts_terms' => 'J\'ai lu les conditions de la bourse.',
                            ] as $field => $text)
                                <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-200">
                                    <input type="checkbox" name="{{ $field }}" value="1" required @checked(old($field)) class="mt-0.5 size-5 shrink-0 accent-gold-400">
                                    <span>
                                        {{ $text }}
                                        @if ($field === 'accepts_terms') <a href="#conditions" class="font-semibold text-gold-200 underline underline-offset-2">Relire les conditions</a> @endif
                                    </span>
                                </label>
                                @error($field) <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                            @endforeach
                        </div>
                    </fieldset>

                    {{-- 2. Paiement des frais d'inscription : mêmes moyens et mêmes instructions que le paiement des formations --}}
                    <fieldset
                        x-data="mobileMoneyPayment({
                            methods: @js($paymentMethods->map(fn ($method) => ['key' => $method->key, 'country' => $method->country, 'name' => $method->name, 'countryName' => $method->countryName()])->values()),
                            initialMethod: @js($paymentMethods->has((string) old('payment_method')) ? (string) old('payment_method') : null),
                            initialStep: 1,
                            maxBytes: @js($maxMb * 1024 * 1024),
                            maxMb: @js($maxMb),
                            labels: ['Pays', 'Opérateur', 'Paiement', 'Capture'],
                        })"
                        x-ref="payment"
                        class="scroll-mt-24 space-y-5"
                        data-test="scholarship-payment"
                    >
                        <legend class="text-lg font-semibold text-white">2. Paie tes frais d'inscription : {{ $feeLabel }}</legend>
                        <input type="hidden" name="payment_method" :value="method" value="{{ old('payment_method') }}">

                        <div class="space-y-2">
                            <p class="field-label">Dans quel pays paies-tu ?</p>
                            <div class="grid gap-2 sm:grid-cols-3">
                                @foreach ($countries as $code => $country)
                                    <button type="button" @click="chooseCountry(@js($code))" :class="country === @js($code) ? 'bg-gold-400/10 text-white ring-2 ring-gold-400' : 'bg-veil/5 text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10'" class="flex min-h-12 items-center gap-3 rounded-2xl px-4 py-3 text-left font-semibold transition" data-test="scholarship-country-{{ $code }}">
                                        <span class="text-2xl leading-none" aria-hidden="true">{{ $country['flag'] }}</span> {{ $country['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-2" x-show="country" x-cloak>
                            <p class="field-label">Ton opérateur</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($paymentMethods as $method)
                                    <button type="button" x-show="country === @js($method->country)" @click="chooseMethod(@js($method->key))" :class="method === @js($method->key) ? 'bg-gold-400/10 text-white ring-2 ring-gold-400' : 'bg-veil/5 text-slate-200 ring-1 ring-veil/15 hover:bg-veil/10'" class="flex min-h-12 items-center gap-3 rounded-2xl px-4 py-3 text-left font-semibold transition" data-test="scholarship-method-{{ $method->key }}">
                                        <span class="method-dot grid size-9 shrink-0 place-items-center rounded-full text-xs font-bold text-night-950" style="background:{{ $method->color }};--dot-inverse:{{ $method->inverseColor() }}" aria-hidden="true">{{ $method->initials() }}</span>
                                        {{ $method->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @error('payment_method') <p class="text-sm text-rose-300" role="alert">{{ $message }}</p> @enderror

                        @foreach ($paymentMethods as $method)
                            @php
                                $ussdCode = $method->ussdCode($fee);
                                $localNumber = $method->localNumber();
                            @endphp
                            <div x-show="method === @js($method->key)" x-cloak class="space-y-3" data-test="scholarship-pay-{{ $method->key }}">
                                <div class="divide-y divide-veil/10 rounded-2xl bg-night-800 ring-1 ring-gold-400/40" data-ama-avoid>
                                    <div class="p-4">
                                        <p class="text-sm text-slate-400">Montant exact à envoyer</p>
                                        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                                            <p class="text-3xl font-bold text-gold-200 tabular-nums">{{ $feeLabel }}</p>
                                            @include('site.checkout.partials.copy-button', ['value' => (string) $fee, 'id' => $method->key.'-amount', 'label' => 'Copier le montant'])
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <p class="text-sm text-slate-400">Au numéro {{ $method->name }}</p>
                                        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                                            <p class="text-2xl font-bold tracking-wide text-white tabular-nums">{{ $method->number }}</p>
                                            @include('site.checkout.partials.copy-button', ['value' => $localNumber, 'id' => $method->key.'-number', 'label' => 'Copier le numéro'])
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <p class="text-sm text-slate-400">Nom du bénéficiaire</p>
                                        <p class="text-lg font-semibold text-white">{{ $method->beneficiary }}</p>
                                        <p class="mt-1 text-xs text-slate-400">Vérifie que ce nom s'affiche avant de valider le transfert.</p>
                                    </div>
                                </div>
                                @if ($note = $method->representativeNote())
                                    <p class="rounded-2xl bg-electric-500/10 px-3 py-2.5 text-sm text-electric-100 ring-1 ring-electric-400/30">{{ $note }}</p>
                                @endif
                                @if ($ussdCode !== null)
                                    <div class="space-y-2 rounded-2xl bg-night-800 p-4 ring-1 ring-veil/15">
                                        <a href="{{ $method->ussdTelUrl($fee) }}" x-show="canDial" class="btn-gold w-full"><flux:icon.phone class="size-5" /> Ouvrir {{ $method->name }}</a>
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-sm text-slate-200">Code à composer : <span class="font-mono font-bold text-white">{{ $ussdCode }}</span></p>
                                            @include('site.checkout.partials.copy-button', ['value' => $ussdCode, 'id' => $method->key.'-ussd', 'label' => 'Copier', 'small' => true])
                                        </div>
                                        <p class="text-sm text-slate-300">Le menu de ton opérateur s'ouvre : choisis le transfert, saisis ce numéro et ce montant, puis valide avec ton code secret. Téléphone double SIM : utilise la carte SIM de cet opérateur.</p>
                                    </div>
                                @elseif ($method->usesApp())
                                    <p class="text-sm text-slate-300">Ouvre l'application {{ $method->name }} pour envoyer le montant à ce numéro.</p>
                                @endif
                                <p class="text-sm text-slate-300">Le paiement se fait par transfert Mobile Money depuis ton téléphone. Les frais de transfert sont à ta charge.</p>
                            </div>
                        @endforeach

                        <div class="space-y-2" x-show="method" x-cloak>
                            <label for="bourse-proof" class="field-label">Capture d'écran de ton paiement</label>
                            <div x-data="{ fileName: '' }">
                                <input id="bourse-proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required class="sr-only" @change="fileName = $event.target.files[0]?.name ?? ''" data-test="scholarship-proof">
                                <label for="bourse-proof" class="flex min-h-14 w-full cursor-pointer items-center gap-3 rounded-2xl bg-gold-400/10 px-4 py-3 font-semibold text-gold-100 ring-1 ring-gold-400/60 transition hover:bg-gold-400/20">
                                    <flux:icon.camera class="size-6 shrink-0" />
                                    <span class="min-w-0 truncate" x-text="fileName ? 'Capture choisie : ' + fileName : 'Choisir ma capture (galerie ou appareil photo)'">Choisir ma capture (galerie ou appareil photo)</span>
                                </label>
                            </div>
                            <p class="text-xs text-slate-400">Le message de confirmation de ton paiement de {{ $feeLabel }}. Image JPG, PNG ou WebP, {{ $maxMb }} Mo maximum. Elle reste privée : seule notre équipe la voit pour vérifier le paiement.</p>
                            @error('proof') <p class="text-sm text-rose-300" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </fieldset>

                    {{-- Champ piège : invisible pour les personnes, rempli par les robots. --}}
                    <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                        <label for="bourse-website">Ne pas remplir ce champ</label>
                        <input id="bourse-website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="space-y-2">
                        <button type="submit" class="btn-gold w-full !py-4 text-lg" :class="sending && 'pointer-events-none brightness-75'" data-test="scholarship-submit" data-ama-clear>
                            <span x-text="sending ? 'Envoi en cours…' : 'Envoyer ma candidature et ma capture'">Envoyer ma candidature et ma capture</span>
                        </button>
                        <p class="text-center text-xs text-slate-400">Si tu n'es pas retenu(e), tes {{ $feeLabel }} te sont remboursés. Tes réponses servent uniquement à la sélection (voir la <a href="{{ route('legal.privacy') }}" class="underline underline-offset-2 hover:text-white">politique de confidentialité</a>).</p>
                    </div>
                </form>
            @endif
        </div>
    </section>

    <x-site.cta-banner title="Une question sur la bourse ?" text="Écris-nous sur WhatsApp, nous te répondons.">
        <a href="{{ WhatsApp::url() }}" target="_blank" rel="noopener" class="btn-gold"><x-site.whatsapp-icon class="size-5" /> Écrire sur WhatsApp</a>
    </x-site.cta-banner>
</x-layouts::site>
