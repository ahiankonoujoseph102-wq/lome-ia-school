{{--
    Candidature à la bourse PAS À PAS, plein écran (composant Alpine scholarshipApply de site.js) : une question par
    écran, « Suivant » (ou Entrée), « Retour », barre de progression ; puis pays, opérateur, envoi de l'argent et
    capture. Tous les champs restent dans UN seul formulaire, envoyé à la fin (route scholarship.store inchangée).
    Erreur renvoyée par le serveur : le formulaire s'ouvre seul sur la première question à corriger.
--}}
@php
    use App\Models\ScholarshipApplication;

    $maxMb = intdiv((int) config('site.payments.proof_max_kb', 8192), 1024);
    $full = collect($remaining)->filter(fn (int $left): bool => $left === 0)->map(fn (): string => 'Plus de place')->all();

    // Questions (part « candidature ») puis paiement ; « fields » sert à rouvrir la bonne question après une erreur.
    $steps = [
        ['part' => 'answers', 'fields' => ['name']],
        ['part' => 'answers', 'fields' => ['phone']],
        ['part' => 'answers', 'fields' => ['email']],
        ['part' => 'answers', 'fields' => ['gender']],
        ['part' => 'answers', 'fields' => ['age']],
        ['part' => 'answers', 'fields' => ['city']],
        ['part' => 'answers', 'fields' => ['level']],
        ['part' => 'answers', 'fields' => ['tools']],
        ['part' => 'answers', 'fields' => ['device']],
        ['part' => 'answers', 'fields' => ['motivation']],
        ['part' => 'answers', 'fields' => ['first_agent']],
        ['part' => 'answers', 'fields' => ['available']],
        ['part' => 'answers', 'fields' => ['accepts_video', 'accepts_terms']],
        ['part' => 'payment', 'fields' => []],
        ['part' => 'payment', 'fields' => ['payment_method']],
        ['part' => 'payment', 'fields' => []],
        ['part' => 'payment', 'fields' => ['proof', 'form']],
    ];
    $questionCount = collect($steps)->where('part', 'answers')->count();
    $errorStep = 0;
    $errorMessage = '';
    foreach ($steps as $index => $definition) {
        foreach ($definition['fields'] as $field) {
            if ($errors->has($field)) {
                [$errorStep, $errorMessage] = [$index, $errors->first($field)];
                break 2;
            }
        }
    }
    $initialMethod = $paymentMethods->has((string) old('payment_method')) ? (string) old('payment_method') : null;
    $nextLabels = array_fill(0, count($steps), 'Suivant');
    $nextLabels[12] = 'Passer au paiement';
    $nextLabels[15] = 'J\'ai payé : envoyer ma capture';

    $question = fn (int $number, string $title, ?string $hint = null): string => '<p class="text-sm font-semibold text-gold-200">'
        .($number <= $questionCount ? 'Question '.$number.' sur '.$questionCount : 'Paiement · étape '.($number - $questionCount).' sur '.(count($steps) - $questionCount))
        .'</p><h2 class="mt-2 text-2xl leading-snug font-bold text-white sm:text-3xl">'.e($title).'</h2>'
        .($hint ? '<p class="mt-2 text-slate-400">'.e($hint).'</p>' : '');
    $input = 'field-input !py-4 !text-lg';
@endphp

<div
    x-show="open"
    x-cloak
    x-init="initialize(@js([
        'total' => count($steps),
        'questions' => $questionCount,
        'step' => $errorStep,
        'error' => $errorMessage,
        'labels' => $nextLabels,
        'initialMethod' => $initialMethod,
        'methods' => $paymentMethods->map(fn ($method) => ['key' => $method->key, 'country' => $method->country])->values(),
        'maxBytes' => $maxMb * 1024 * 1024,
        'maxMb' => $maxMb,
    ]))"
    @keydown.escape.window="open && close()"
    class="fixed inset-0 z-[60] flex flex-col bg-night-950"
    role="dialog"
    aria-modal="true"
    aria-label="Candidature à la bourse"
    data-test="scholarship-apply-dialog"
>
    {{-- Haut : fermer et progression --}}
    <div class="shrink-0 px-4 pt-[max(0.75rem,env(safe-area-inset-top))] sm:px-6">
        <div class="mx-auto flex max-w-xl items-center gap-3 py-2">
            <button type="button" @click="back()" class="grid size-11 shrink-0 place-items-center rounded-full text-slate-300 hover:bg-veil/10 hover:text-white" aria-label="Question précédente">
                <flux:icon.arrow-left class="size-5" />
            </button>
            <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-night-700" role="progressbar" aria-label="Avancement de la candidature" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-gold-500 transition-[width] duration-300" :style="`width: ${progress}%`"></div>
            </div>
            <button type="button" @click="close()" class="grid size-11 shrink-0 place-items-center rounded-full text-slate-300 hover:bg-veil/10 hover:text-white" aria-label="Fermer (tes réponses sont gardées)">
                <flux:icon.x-mark class="size-6" />
            </button>
        </div>
    </div>

    <form
        x-ref="form"
        method="POST"
        action="{{ route('scholarship.store') }}"
        enctype="multipart/form-data"
        novalidate
        @submit="submit($event)"
        @keydown.enter="enter($event)"
        @change="chose($event)"
        @pageshow.window="sending = false"
        class="flex min-h-0 flex-1 flex-col"
        data-test="scholarship-form"
    >
        @csrf
        <input type="hidden" name="payment_method" :value="method" value="{{ old('payment_method') }}">
        {{-- Champ piège : invisible pour les personnes, rempli par les robots. --}}
        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
            <label for="bourse-website">Ne pas remplir ce champ</label>
            <input id="bourse-website" type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div x-ref="body" class="min-h-0 flex-1 overflow-y-auto px-4 sm:px-6">
            <div class="mx-auto max-w-xl py-6 sm:py-10">

                {{-- 1. Nom --}}
                <div x-show="step === 0" data-step="0" class="space-y-5">
                    <label for="bourse-name" class="block">{!! $question(1, 'Comment t\'appelles-tu ?', 'Ton nom et ton prénom.') !!}</label>
                    <input id="bourse-name" type="text" name="name" maxlength="120" autocomplete="name" required value="{{ old('name') }}" class="{{ $input }}" data-message="Indique ton nom et ton prénom.">
                </div>

                {{-- 2. WhatsApp --}}
                <div x-show="step === 1" x-cloak data-step="1" class="space-y-5">
                    <label for="bourse-phone" class="block">{!! $question(2, 'Ton numéro WhatsApp', 'Avec l\'indicatif du pays.') !!}</label>
                    <input id="bourse-phone" type="tel" name="phone" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="+228 90 00 00 00" required pattern="[+0-9 ().\-]{8,30}" value="{{ old('phone') }}" class="{{ $input }}" data-message="Écris ton numéro WhatsApp avec l'indicatif du pays (ex. +228 90 00 00 00).">
                </div>

                {{-- 3. E-mail --}}
                <div x-show="step === 2" x-cloak data-step="2" class="space-y-5">
                    <label for="bourse-email" class="block">{!! $question(3, 'Ton adresse e-mail', 'Pour recevoir la réponse et, si tu es retenu(e), ton accès.') !!}</label>
                    <input id="bourse-email" type="email" name="email" maxlength="255" autocomplete="email" inputmode="email" required value="{{ old('email') }}" class="{{ $input }}" data-message="Écris une adresse e-mail valide : tu y recevras la réponse.">
                </div>

                {{-- 4. Genre --}}
                <div x-show="step === 3" x-cloak data-step="3" data-auto class="space-y-5">
                    {!! $question(4, 'Tu es…') !!}
                    @include('site.scholarship.partials.choices', ['name' => 'gender', 'label' => 'Genre', 'legendClass' => 'sr-only', 'options' => ScholarshipApplication::GENDERS, 'disabled' => $full])
                    <p class="hidden" data-message-holder>Choisis : Femme ou Homme.</p>
                </div>

                {{-- 5. Âge --}}
                <div x-show="step === 4" x-cloak data-step="4" class="space-y-5">
                    <label for="bourse-age" class="block">{!! $question(5, 'Quel âge as-tu ?', 'La bourse est ouverte de 20 à 40 ans.') !!}</label>
                    <input id="bourse-age" type="number" name="age" min="20" max="40" inputmode="numeric" required value="{{ old('age') }}" class="{{ $input }}" data-message="La bourse est réservée aux personnes de 20 à 40 ans : écris ton âge en chiffres.">
                </div>

                {{-- 6. Ville --}}
                <div x-show="step === 5" x-cloak data-step="5" class="space-y-5">
                    <label for="bourse-city" class="block">{!! $question(6, 'Dans quelle ville et quel pays vis-tu ?') !!}</label>
                    <input id="bourse-city" type="text" name="city" maxlength="120" placeholder="Lomé, Togo" required value="{{ old('city') }}" class="{{ $input }}" data-message="Indique ta ville et ton pays.">
                </div>

                {{-- 7. Niveau --}}
                <div x-show="step === 6" x-cloak data-step="6" data-auto class="space-y-5">
                    {!! $question(7, 'Ton niveau en digital et en IA') !!}
                    @include('site.scholarship.partials.choices', ['name' => 'level', 'label' => 'Ton niveau en digital et en IA', 'legendClass' => 'sr-only', 'options' => ScholarshipApplication::LEVELS, 'columns' => ''])
                    <p class="hidden" data-message-holder>Choisis ton niveau en digital et en IA.</p>
                </div>

                {{-- 8. Outils --}}
                <div x-show="step === 7" x-cloak data-step="7" class="space-y-5">
                    <label for="bourse-tools" class="block">{!! $question(8, 'Quels outils numériques et d\'IA utilises-tu déjà ?', 'Exemple : ChatGPT, Canva, WhatsApp Business… ou « aucun ».') !!}</label>
                    <textarea id="bourse-tools" name="tools" rows="3" maxlength="1000" required class="{{ $input }}" data-message="Indique les outils que tu utilises déjà (ou « aucun »).">{{ old('tools') }}</textarea>
                </div>

                {{-- 9. Matériel --}}
                <div x-show="step === 8" x-cloak data-step="8" data-auto class="space-y-5">
                    {!! $question(9, 'Ton matériel') !!}
                    @include('site.scholarship.partials.choices', ['name' => 'device', 'label' => 'Ton matériel', 'legendClass' => 'sr-only', 'options' => ScholarshipApplication::DEVICES, 'columns' => ''])
                    <p class="hidden" data-message-holder>Indique ton matériel.</p>
                </div>

                {{-- 10. Pourquoi --}}
                <div x-show="step === 9" x-cloak data-step="9" class="space-y-5">
                    <label for="bourse-motivation" class="block">{!! $question(10, 'Pourquoi veux-tu apprendre à créer des agents IA ? Quel est ton grand « pourquoi » ?', 'Quelques phrases suffisent.') !!}</label>
                    <textarea id="bourse-motivation" name="motivation" rows="6" minlength="30" maxlength="3000" required class="{{ $input }}" data-message="Explique ton grand « pourquoi » en quelques phrases (30 caractères au moins).">{{ old('motivation') }}</textarea>
                </div>

                {{-- 11. Premier agent --}}
                <div x-show="step === 10" x-cloak data-step="10" class="space-y-5">
                    <label for="bourse-first-agent" class="block">{!! $question(11, 'Quel premier agent IA aimerais-tu créer, et pour qui ?') !!}</label>
                    <textarea id="bourse-first-agent" name="first_agent" rows="5" minlength="10" maxlength="2000" required class="{{ $input }}" data-message="Décris le premier agent IA que tu aimerais créer (10 caractères au moins).">{{ old('first_agent') }}</textarea>
                </div>

                {{-- 12. Disponibilité --}}
                <div x-show="step === 11" x-cloak data-step="11" data-auto class="space-y-5">
                    {!! $question(12, 'Peux-tu suivre la formation pendant un mois, avec les directs ?') !!}
                    @include('site.scholarship.partials.choices', ['name' => 'available', 'label' => 'Je peux suivre la formation pendant un mois, avec les directs', 'legendClass' => 'sr-only', 'options' => ['yes' => 'Oui', 'no' => 'Non']])
                    <p class="hidden" data-message-holder>Réponds : peux-tu suivre la formation pendant un mois ?</p>
                </div>

                {{-- 13. Engagements et conditions --}}
                <div x-show="step === 12" x-cloak data-step="12" class="space-y-5">
                    {!! $question(13, 'Dernière étape avant le paiement') !!}
                    <details class="group rounded-2xl bg-night-900 shadow-[inset_0_0_0_1px_var(--color-night-700)]">
                        <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-semibold text-gold-100">
                            Lire les conditions de la bourse
                            <flux:icon.chevron-down class="size-5 text-slate-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="px-4 pb-4">@include('site.scholarship.partials.conditions')</div>
                    </details>
                    @foreach ([
                        'accepts_agents' => ['Condition principale : à la fin de la formation, je crée gratuitement 3 agents IA pour Coach Joseph, correctement et de A à Z, selon ce qu\'il me demande.', 'Coche cette case : c\'est la condition principale de la bourse.'],
                        'accepts_video' => ['Si je suis retenu(e), j\'accepte d\'enregistrer une vidéo de bilan honnête sur ce que j\'ai appris, que Lomé IA School pourra publier.', 'Coche cette case pour candidater.'],
                        'accepts_terms' => ['J\'ai lu les conditions de la bourse.', 'Coche cette case après avoir lu les conditions.'],
                    ] as $field => [$text, $message])
                        <label class="flex min-h-14 cursor-pointer items-start gap-3 rounded-2xl bg-veil/5 px-4 py-3.5 text-base text-slate-100 ring-1 ring-veil/15 has-[:checked]:bg-gold-400/10 has-[:checked]:ring-2 has-[:checked]:ring-gold-400">
                            <input type="checkbox" name="{{ $field }}" value="1" required @checked(old($field)) class="mt-1 size-5 shrink-0 accent-gold-400" data-message="{{ $message }}">
                            <span>{{ $text }}</span>
                        </label>
                    @endforeach
                </div>

                {{-- 14. Pays du paiement --}}
                <div x-show="step === 13" x-cloak data-step="13" data-kind="country" class="space-y-5">
                    {!! $question(14, 'Paie tes frais d\'inscription : '.$feeLabel, 'Dans quel pays paies-tu ?') !!}
                    <div class="grid gap-2">
                        @foreach ($countries as $code => $country)
                            <button type="button" @click="chooseCountry(@js($code))" :class="country === @js($code) ? 'bg-gold-400/10 ring-2 ring-gold-400' : 'bg-veil/5 ring-1 ring-veil/15 hover:bg-veil/10'" class="flex min-h-16 w-full items-center gap-4 rounded-2xl px-4 py-3 text-left transition" data-test="scholarship-country-{{ $code }}" data-ama-clear>
                                <span class="text-3xl leading-none" aria-hidden="true">{{ $country['flag'] }}</span>
                                <span class="flex-1 text-lg font-semibold text-white">{{ $country['name'] }}</span>
                                <flux:icon.chevron-right class="size-5 text-slate-400" />
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- 15. Opérateur --}}
                <div x-show="step === 14" x-cloak data-step="14" data-kind="method" class="space-y-5">
                    {!! $question(15, 'Ton opérateur', 'Celui avec lequel tu vas envoyer l\'argent.') !!}
                    <div class="grid gap-2">
                        @foreach ($paymentMethods as $method)
                            <button type="button" x-show="country === @js($method->country)" @click="chooseMethod(@js($method->key))" :class="method === @js($method->key) ? 'bg-gold-400/10 ring-2 ring-gold-400' : 'bg-veil/5 ring-1 ring-veil/15 hover:bg-veil/10'" class="flex min-h-16 w-full items-center gap-4 rounded-2xl px-4 py-3 text-left transition" data-test="scholarship-method-{{ $method->key }}" data-ama-clear>
                                <span class="method-dot grid size-11 shrink-0 place-items-center rounded-full text-sm font-bold text-night-950" style="background:{{ $method->color }};--dot-inverse:{{ $method->inverseColor() }}" aria-hidden="true">{{ $method->initials() }}</span>
                                <span class="flex-1 text-lg font-semibold text-white">{{ $method->name }}</span>
                                <flux:icon.chevron-right class="size-5 text-slate-400" />
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- 16. Envoi de l'argent --}}
                <div x-show="step === 15" x-cloak data-step="15" class="space-y-4">
                    {!! $question(16, 'Envoie '.$feeLabel.' par Mobile Money') !!}
                    @foreach ($paymentMethods as $method)
                        @php
                            $ussdCode = $method->ussdCode($fee);
                            $localNumber = $method->localNumber();
                        @endphp
                        <div x-show="method === @js($method->key)" x-cloak class="space-y-3" data-test="scholarship-pay-{{ $method->key }}">
                            <div class="divide-y divide-veil/10 rounded-2xl bg-night-900 ring-1 ring-gold-400/40" data-ama-avoid>
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
                                <div class="space-y-2 rounded-2xl bg-night-900 p-4 ring-1 ring-veil/15">
                                    <a href="{{ $method->ussdTelUrl($fee) }}" x-show="canDial" class="btn-gold w-full" data-ama-clear><flux:icon.phone class="size-5" /> Ouvrir {{ $method->name }}</a>
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm text-slate-200">Code à composer : <span class="font-mono font-bold text-white">{{ $ussdCode }}</span></p>
                                        @include('site.checkout.partials.copy-button', ['value' => $ussdCode, 'id' => $method->key.'-ussd', 'label' => 'Copier', 'small' => true])
                                    </div>
                                    <p class="text-sm text-slate-300">Le menu de ton opérateur s'ouvre : choisis le transfert, saisis ce numéro et ce montant, puis valide avec ton code secret. Téléphone double SIM : utilise la carte SIM de cet opérateur.</p>
                                </div>
                            @elseif ($method->usesApp())
                                <p class="text-sm text-slate-300">Ouvre l'application {{ $method->name }} pour envoyer le montant à ce numéro.</p>
                            @endif
                            <p class="text-sm text-slate-400">Les frais de transfert sont à ta charge. Ensuite, fais une capture d'écran du message de confirmation.</p>
                        </div>
                    @endforeach
                </div>

                {{-- 17. Capture --}}
                <div x-show="step === 16" x-cloak data-step="16" data-kind="proof" class="space-y-5">
                    {!! $question(17, 'Envoie la capture de ton paiement', 'Le message de confirmation de ton paiement de '.$feeLabel.', depuis ta galerie ou l\'appareil photo.') !!}
                    <input id="bourse-proof" type="file" name="proof" accept="image/jpeg,image/png,image/webp" required class="sr-only" x-ref="proof" @change="pickProof($event)" data-test="scholarship-proof">
                    <template x-if="preview">
                        <img :src="preview" alt="Aperçu de ta capture" class="mx-auto max-h-72 rounded-2xl ring-1 ring-veil/20">
                    </template>
                    <label for="bourse-proof" class="flex min-h-16 w-full cursor-pointer items-center justify-center gap-3 rounded-2xl bg-gold-400/10 px-4 py-3 text-lg font-semibold text-gold-100 ring-1 ring-gold-400/60 transition hover:bg-gold-400/20" data-ama-clear>
                        <flux:icon.camera class="size-6 shrink-0" />
                        <span x-text="preview ? 'Choisir une autre image' : 'Choisir ma capture'">Choisir ma capture</span>
                    </label>
                    <p class="text-xs text-slate-400">Image JPG, PNG ou WebP, {{ $maxMb }} Mo maximum. Elle reste privée : seule notre équipe la voit pour vérifier le paiement. Si tu n'es pas retenu(e), tes {{ $feeLabel }} te sont remboursés. Tes réponses servent uniquement à la sélection (voir la <a href="{{ route('legal.privacy') }}" class="underline underline-offset-2 hover:text-white">politique de confidentialité</a>).</p>
                    @if ($errors->any())
                        <p class="text-sm text-slate-300">Pour des raisons de sécurité, choisis de nouveau ta capture.</p>
                    @endif
                </div>

                <p x-show="error" x-cloak x-text="error" class="mt-4 rounded-2xl bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-100 ring-1 ring-rose-400/50" role="alert" data-test="scholarship-step-error"></p>
            </div>
        </div>

        {{-- Bas : bouton principal, toujours visible au-dessus du clavier --}}
        <div class="shrink-0 bg-night-900/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-1px_0_var(--color-night-700)] sm:px-6">
            <div class="mx-auto flex max-w-xl items-center gap-3">
                <p class="hidden text-sm text-slate-400 sm:block" x-show="step < total - 1">Appuie sur Entrée ↵</p>
                <button type="button" x-show="step < total - 1" @click="next()" class="btn-gold ml-auto w-full !py-4 text-lg sm:w-auto sm:min-w-56" data-test="scholarship-next">
                    <span x-text="labels[step]">Suivant</span> <flux:icon.arrow-right class="size-5" />
                </button>
                <button type="submit" x-show="step === total - 1" x-cloak class="btn-gold ml-auto w-full !py-4 text-lg sm:w-auto sm:min-w-56" :class="sending && 'pointer-events-none brightness-75'" data-test="scholarship-submit">
                    <span x-text="sending ? 'Envoi en cours…' : 'Envoyer ma candidature'">Envoyer ma candidature</span>
                </button>
            </div>
        </div>
    </form>
</div>
