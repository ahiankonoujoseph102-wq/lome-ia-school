{{--
    Paiement Mobile Money manuel, sur une seule page en 3 étapes (pensé pour le téléphone) :
    1. opérateur (pays deviné d'après le numéro WhatsApp du compte, modifiable),
    2. montant, numéro et bénéficiaire (+ bouton « Payer maintenant » par code USSD, aide « premier paiement »),
    3. capture d'écran du paiement : aperçu, puis « Envoyer cette capture ».
    En interne, 4 phases (pays, opérateur, envoi, capture) gardées pour Ama, WhatsApp et les bulles d'aide.
    Sert aussi au supplément d'une session en présentiel ($liveSession).
--}}
@php
    use App\Payments\PaymentMethods;
    use App\Support\Device;
    use App\Support\Money;
    use App\Support\WhatsAppMessages;

    $liveSession ??= null;
    $user ??= auth()->user();
    $quote ??= null;
    // Montant réellement à envoyer (remise du code promo comprise), en entier et affiché.
    $amountValue = $liveSession ? (int) $liveSession->supplement_amount : (int) ($amountValue ?? $formation->price);
    $amount = Money::fcfa($amountValue);
    $payAction = $liveSession ? route('student.sessions.pay', $liveSession) : route('checkout.pay', $formation);
    $payments = config('site.payments');
    $paymentMethods = PaymentMethods::enabled();
    $isIos = Device::isIos(request()->userAgent());
    $samePayment = $unverifiedPurchase && ($liveSession
        ? $unverifiedPurchase->live_session_id === $liveSession->id
        : $unverifiedPurchase->formation_id === $formation->id && ! $unverifiedPurchase->isSessionSupplement());
    // « Besoin d'aide pour payer ? » : un message WhatsApp par étape (formation et étape affichée).
    $helpUrls = WhatsAppMessages::paymentUrls($formation, $liveSession);
    $initialMethod = $paymentMethods->has((string) old('payment_method')) ? (string) old('payment_method') : null;
    $stepLabels = ['Votre opérateur', 'Envoyez le paiement', 'Votre capture'];
    // Pays deviné d'après le numéro WhatsApp du compte (seulement un pays où l'on peut payer).
    $guessedCountry = PaymentMethods::countryForPhone($user->phone);
    $initialCountry = $initialMethod ? $paymentMethods->get($initialMethod)?->country : $guessedCountry;
    $initialPhase = $initialMethod ? ($errors->has('proof') ? 4 : 3) : ($initialCountry ? 2 : 1);
    $maxMb = intdiv((int) $payments['proof_max_kb'], 1024);
@endphp

@if (! $user->hasConfirmedEmail())
    {{-- Adresse e-mail pas encore confirmée : paiement impossible (reçus et accès envoyés à cette adresse) --}}
    @include('site.partials.email-confirmation-required', ['action' => $liveSession ? 'payer ce supplément' : 'payer cette formation'])
@elseif ($unverifiedPurchase)
    {{-- Un seul paiement non vérifié à la fois --}}
    <div class="card p-6">
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $unverifiedPurchase->status->badgeClasses() }}">
            <flux:icon.clock class="size-3.5" /> {{ $unverifiedPurchase->status->label() }}
        </span>
        @if ($samePayment)
            <h2 class="mt-4 text-lg font-semibold text-white">Votre paiement {{ $liveSession ? 'pour cette session' : 'pour cette formation' }} est en cours de vérification</h2>
            <p class="mt-2 text-sm text-slate-300">Nous l'avons bien reçu. Vous serez prévenu par e-mail dès qu'il aura été vérifié ({{ $payments['verification_hours'] }}).</p>
        @else
            <h2 class="mt-4 text-lg font-semibold text-white">Un paiement est déjà en cours de vérification</h2>
            <p class="mt-2 text-sm text-slate-300">
                Votre paiement pour « {{ $unverifiedPurchase->item_label }} » est en cours de vérification.
                Vous pourrez payer {{ $liveSession ? 'ce supplément' : 'cette formation' }} dès qu'il aura été vérifié ({{ $payments['verification_hours'] }}).
            </p>
        @endif
        <a href="{{ route('student.purchases.show', $unverifiedPurchase) }}" class="btn-gold mt-5 w-full sm:w-auto">Suivre mon paiement</a>
    </div>
@else
    <div
        x-data="mobileMoneyPayment({
            methods: @js($paymentMethods->map(fn ($method) => ['key' => $method->key, 'country' => $method->country, 'name' => $method->name, 'countryName' => $method->countryName()])->values()),
            countries: @js(collect($countries)->map(fn ($country) => ['name' => $country['name'], 'flag' => $country['flag']])),
            initialMethod: @js($initialMethod),
            initialCountry: @js($initialCountry),
            initialStep: @js($initialPhase),
            maxBytes: @js((int) $payments['proof_max_kb'] * 1024),
            maxMb: @js($maxMb),
            labels: @js($stepLabels),
            helpUrls: @js($helpUrls),
        })"
        x-ref="payment"
        @pageshow.window="sending = false"
        class="card scroll-mt-24 overflow-hidden"
        data-test="payment-steps"
    >
        {{-- En-tête : progression en 3 étapes --}}
        <div class="border-b border-veil/10 p-5 sm:p-6">
            <h2 class="text-xl font-bold text-white">Payer par Mobile Money</h2>
            <p class="mt-1 text-sm text-slate-400">
                Étape <span x-text="shown">{{ $initialPhase <= 2 ? 1 : $initialPhase - 1 }}</span> sur 3 :
                <span class="text-slate-200" x-text="labels[shown - 1]">{{ $stepLabels[$initialPhase <= 2 ? 0 : $initialPhase - 2] }}</span>
            </p>
            <ol class="mt-3 grid grid-cols-3 gap-1.5" aria-hidden="true">
                @foreach ($stepLabels as $label)
                    <li class="h-1.5 rounded-full" :class="shown >= {{ $loop->iteration }} ? 'bg-gold-500' : 'bg-veil/15'"></li>
                @endforeach
            </ol>
        </div>

        <div class="space-y-5 p-5 sm:p-6">
            @error('formation')
                <x-site.alert type="error">{{ $message }}</x-site.alert>
            @enderror
            @error('promo_code')
                <x-site.alert type="error">{{ $message }}</x-site.alert>
            @enderror
            @error('payment_method')
                <x-site.alert type="error">{{ $message }}</x-site.alert>
            @enderror

            {{-- Étapes déjà faites : rappel du pays et de l'opérateur, modifiables --}}
            <div x-show="step >= 3" @unless ($initialMethod) x-cloak @endunless class="flex items-center gap-3 rounded-2xl bg-night-900 px-4 py-3 ring-1 ring-veil/15" data-test="payment-choice-summary">
                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-400/40" aria-hidden="true"><flux:icon.check class="size-4" /></span>
                <p class="min-w-0 flex-1 text-sm text-slate-200">
                    <span x-text="countryFlag" aria-hidden="true"></span>
                    <span class="font-semibold text-white" x-text="countryName"></span> · <span x-text="methodName"></span>
                </p>
                <button type="button" @click="goTo(2)" class="min-h-11 shrink-0 px-2 text-sm font-semibold text-gold-200 hover:text-white">Modifier</button>
            </div>

            {{-- Étape 1 (a) : pays, seulement s'il n'a pas pu être deviné ou si l'élève veut le changer --}}
            <section x-show="step === 1" @if ($initialPhase !== 1) x-cloak @endif aria-labelledby="etape-pays" data-step="1">
                <h3 id="etape-pays" class="text-lg font-semibold text-white">Dans quel pays payez-vous ?</h3>
                <div class="mt-4 grid gap-3" role="list">
                    @foreach ($countries as $code => $country)
                        <button type="button" role="listitem" @click="chooseCountry(@js($code))"
                            class="flex min-h-16 w-full items-center gap-4 rounded-2xl bg-veil/5 px-4 py-3 text-left ring-1 ring-veil/15 transition hover:bg-veil/10 active:scale-[0.99]"
                            data-test="country-{{ $code }}" data-ama-clear>
                            <span class="text-3xl leading-none" aria-hidden="true">{{ $country['flag'] }}</span>
                            <span class="flex-1 text-lg font-semibold text-white">{{ $country['name'] }}</span>
                            <flux:icon.chevron-right class="size-5 text-slate-400" />
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Étape 1 (b) : opérateur du pays choisi ou deviné --}}
            <section x-show="step === 2" @if ($initialPhase !== 2) x-cloak @endif aria-labelledby="etape-operateur" data-step="2">
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-night-900 px-4 py-2.5 ring-1 ring-veil/15" data-test="payment-country">
                    <p class="text-sm text-slate-300">
                        Pays : <span class="font-semibold text-white"><span x-text="countryFlag" aria-hidden="true">{{ $initialCountry ? $countries[$initialCountry]['flag'] : '' }}</span> <span x-text="countryName">{{ $initialCountry ? $countries[$initialCountry]['name'] : '' }}</span></span>
                        @if ($guessedCountry && ! $initialMethod)
                            <span x-show="guessed" class="block text-xs text-slate-400" data-test="payment-country-guessed">D'après votre numéro WhatsApp</span>
                        @endif
                    </p>
                    <button type="button" @click="goTo(1)" class="min-h-11 px-1 text-sm font-semibold text-gold-200 hover:text-white" data-test="change-country">Changer de pays</button>
                </div>
                <h3 id="etape-operateur" class="mt-5 text-lg font-semibold text-white">Choisissez votre opérateur</h3>
                <p class="mt-1 text-sm text-slate-400">Celui avec lequel vous allez envoyer l'argent.</p>
                <div class="mt-4 grid gap-3">
                    @foreach ($paymentMethods as $method)
                        <button type="button" x-show="country === @js($method->country)" @if ($initialCountry !== $method->country) x-cloak @endif @click="chooseMethod(@js($method->key))"
                            class="flex min-h-16 w-full items-center gap-4 rounded-2xl bg-veil/5 px-4 py-3 text-left ring-1 ring-veil/15 transition hover:bg-veil/10 active:scale-[0.99]"
                            data-test="method-{{ $method->key }}" data-ama-clear>
                            <span class="method-dot grid size-11 shrink-0 place-items-center rounded-full text-sm font-bold text-night-950" style="background:{{ $method->color }};--dot-inverse:{{ $method->inverseColor() }}" aria-hidden="true">{{ $method->initials() }}</span>
                            <span class="flex-1 text-lg font-semibold text-white">{{ $method->name }}</span>
                            <flux:icon.chevron-right class="size-5 text-slate-400" />
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Étape 2 : montant, numéro, bénéficiaire et bouton « Payer maintenant » --}}
            @foreach ($paymentMethods as $method)
                @php
                    $ussdCode = $method->ussdCode($amountValue);
                    $ussdUrl = $method->ussdTelUrl($amountValue);
                    $localNumber = $method->localNumber();
                    $note = $method->representativeNote();
                @endphp
                <section x-show="step === 3 && method === @js($method->key)" @if ($initialMethod !== $method->key || $errors->has('proof')) x-cloak @endif class="space-y-4" aria-label="Paiement {{ $method->name }}" data-step="3" data-method="{{ $method->key }}">
                    <div class="flex items-center gap-3">
                        <span class="method-dot grid size-10 shrink-0 place-items-center rounded-full text-xs font-bold text-night-950" style="background:{{ $method->color }};--dot-inverse:{{ $method->inverseColor() }}" aria-hidden="true">{{ $method->initials() }}</span>
                        <h3 class="text-lg font-semibold text-white">Envoyez le paiement par {{ $method->name }}</h3>
                    </div>

                    <div class="divide-y divide-veil/10 rounded-2xl bg-night-900 ring-1 ring-gold-400/40" data-ama-avoid>
                        <div class="p-4">
                            <p class="text-sm text-slate-400">Montant exact à envoyer</p>
                            <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-3xl font-bold text-gold-200 tabular-nums" data-test="amount-to-send">{{ $amount }}</p>
                                @include('site.checkout.partials.copy-button', ['value' => (string) $amountValue, 'id' => $method->key.'-amount', 'label' => 'Copier le montant'])
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
                            <p class="mt-1 text-xs text-slate-400">Vérifiez que ce nom s'affiche avant de valider le transfert.</p>
                        </div>
                    </div>

                    @if ($note)
                        <p class="flex items-start gap-2 rounded-2xl bg-electric-500/10 px-3 py-2.5 text-sm text-electric-100 ring-1 ring-electric-400/30">
                            <flux:icon.user-circle class="mt-0.5 size-4 shrink-0" /> <span>{{ $note }}</span>
                        </p>
                    @endif

                    <p class="flex items-start gap-2 rounded-2xl bg-veil/5 px-3 py-2.5 text-sm text-slate-200 ring-1 ring-veil/10">
                        <flux:icon.information-circle class="mt-0.5 size-4 shrink-0 text-gold-300" />
                        <span>Le paiement se fait par transfert Mobile Money depuis votre téléphone. Les frais de transfert sont à votre charge.</span>
                    </p>

                    @if ($ussdCode !== null)
                        {{-- Bouton « Payer maintenant » : ouvre le clavier du téléphone avec le code déjà tapé (jamais de code secret) --}}
                        <div class="space-y-3 rounded-2xl bg-night-900 p-4 ring-1 ring-veil/15" data-test="ussd-block">
                            <p class="font-semibold text-white">Payer maintenant</p>
                            @unless ($isIos)
                                <a href="{{ $ussdUrl }}" x-show="canDial" class="btn-gold w-full" data-test="ussd-button" data-ama-clear>
                                    <flux:icon.phone class="size-5" /> Ouvrir {{ $method->name }}
                                </a>
                            @endunless
                            <p @unless ($isIos) x-show="!canDial" x-cloak @endunless class="text-sm text-slate-200" data-test="ussd-manual">
                                Tapez ce code sur le clavier de votre téléphone :
                            </p>
                            <dl class="divide-y divide-veil/10 rounded-xl bg-veil/5 ring-1 ring-veil/10">
                                @foreach ([
                                    ['Code à composer', $ussdCode, 'ussd'],
                                    ['Numéro à saisir', $localNumber, 'ussd-number'],
                                    ['Montant', (string) $amountValue, 'ussd-amount'],
                                ] as [$label, $value, $suffix])
                                    <div class="flex items-center justify-between gap-3 px-3 py-2">
                                        <div class="min-w-0">
                                            <dt class="text-xs text-slate-400">{{ $label }}</dt>
                                            <dd class="font-mono text-lg font-bold break-all text-white" @if ($suffix === 'ussd') data-test="ussd-code" @endif>{{ $value }}</dd>
                                        </div>
                                        @include('site.checkout.partials.copy-button', ['value' => $value, 'id' => $method->key.'-'.$suffix, 'label' => 'Copier', 'small' => true])
                                    </div>
                                @endforeach
                            </dl>
                            <p class="text-sm text-slate-300">Le menu de votre opérateur va s'ouvrir : choisissez le transfert, saisissez ce numéro et ce montant, puis validez avec votre code secret.</p>
                            <p class="flex items-start gap-2 text-sm text-slate-300">
                                <flux:icon.device-phone-mobile class="mt-0.5 size-4 shrink-0 text-gold-300" />
                                <span>Téléphone double SIM : utilisez la carte SIM de cet opérateur.</span>
                            </p>
                        </div>
                    @else
                        <p class="flex items-start gap-2 rounded-2xl bg-night-900 px-3 py-3 text-sm text-slate-200 ring-1 ring-veil/15" data-test="app-instructions">
                            <flux:icon.device-phone-mobile class="mt-0.5 size-4 shrink-0 text-gold-300" />
                            <span>
                                @if ($method->usesApp())
                                    Ouvrez l'application {{ $method->name }} pour envoyer le montant à ce numéro.
                                @else
                                    Ouvrez le menu {{ $method->name }} de votre téléphone pour envoyer le montant à ce numéro. Téléphone double SIM : utilisez la carte SIM de cet opérateur.
                                @endif
                            </span>
                        </p>
                    @endif

                    @include('site.checkout.partials.first-payment-help', ['method' => $method, 'amount' => $amount, 'ussdCode' => $ussdCode])

                    <button type="button" @click="goTo(4)" class="btn w-full bg-gold-400/10 text-gold-100 ring-1 ring-gold-400/60 hover:bg-gold-400/20" data-test="paid-button" data-ama-clear>
                        J'ai payé : envoyer ma capture <flux:icon.arrow-right class="size-5" />
                    </button>
                </section>
            @endforeach

            {{-- Étape 3 : la capture d'écran, avec un aperçu avant l'envoi --}}
            <form method="POST" action="{{ $payAction }}" enctype="multipart/form-data" x-ref="proofForm" x-show="step === 4" @unless ($initialMethod && $errors->has('proof')) x-cloak @endunless @submit="sending = true" aria-labelledby="etape-capture" data-step="4" class="space-y-4">
                @csrf
                <input type="hidden" name="payment_method" :value="method" value="{{ $initialMethod }}">
                @if ($quote)
                    <input type="hidden" name="promo_code" value="{{ $quote->code->code }}">
                @endif

                <div>
                    <h3 id="etape-capture" class="text-lg font-semibold text-white">Envoyez la capture de votre paiement</h3>
                    <p class="mt-1 text-sm text-slate-300">Faites une capture d'écran du message de confirmation de votre paiement ({{ $amount }}), puis envoyez-la.</p>
                </div>

                <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only" x-ref="proof" @change="pickProof($event)">

                {{-- Aucune image choisie : un seul gros bouton --}}
                <label for="proof" x-show="!preview" class="btn-gold w-full cursor-pointer !py-5 text-lg" data-test="send-proof-button" data-ama-clear>
                    <flux:icon.camera class="size-6" />
                    <span>Envoyer la capture de mon paiement</span>
                </label>
                <p x-show="!preview" class="text-center text-xs text-slate-400">Depuis votre galerie ou l'appareil photo. Image JPG, PNG ou WebP, {{ $maxMb }} Mo maximum.</p>

                {{-- Image choisie : aperçu, puis envoi --}}
                <div x-show="preview" x-cloak class="space-y-3 rounded-2xl bg-night-900 p-4 ring-1 ring-veil/15" data-test="proof-preview">
                    <p class="text-sm font-semibold text-white">Vérifiez votre capture</p>
                    <p class="text-xs text-slate-400">Le montant et le nom du bénéficiaire doivent être lisibles.</p>
                    <img :src="preview" alt="Capture choisie" class="mx-auto max-h-72 rounded-xl object-contain ring-1 ring-veil/15">
                    <button type="submit" class="btn-gold w-full !py-4 text-lg" :class="sending && 'pointer-events-none brightness-75'" :disabled="sending" data-test="confirm-proof-button" data-ama-clear>
                        <flux:icon.paper-airplane class="size-5" x-show="!sending" />
                        <svg x-show="sending" x-cloak class="size-6 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        <span x-text="sending ? 'Envoi en cours…' : 'Envoyer cette capture'">Envoyer cette capture</span>
                    </button>
                    <label for="proof" x-show="!sending" class="btn-ghost w-full cursor-pointer" data-test="change-proof-button">
                        <flux:icon.photo class="size-5" /> Choisir une autre image
                    </label>
                </div>

                <p x-show="fileError" x-cloak x-text="fileError" class="text-sm text-rose-300" role="alert"></p>
                @error('proof')
                    <p class="text-sm text-rose-300" role="alert">{{ $message }}</p>
                @enderror

                <details class="group rounded-2xl bg-veil/5 ring-1 ring-veil/10" data-test="screenshot-help">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 py-2.5 text-sm font-semibold text-slate-200">
                        <span class="flex items-center gap-2"><flux:icon.question-mark-circle class="size-4 text-gold-300" /> Comment faire une capture d'écran ?</span>
                        <flux:icon.chevron-down class="size-4 text-slate-400 transition group-open:rotate-180" />
                    </summary>
                    <div class="space-y-2 px-3 pb-3 text-sm text-slate-300">
                        <p>Ouvrez le message de confirmation de votre paiement (SMS ou application), puis :</p>
                        <p><span class="font-semibold text-white">Sur Android</span> : appuyez en même temps sur le bouton marche/arrêt et le bouton volume bas.</p>
                        <p><span class="font-semibold text-white">Sur iPhone</span> : appuyez en même temps sur le bouton latéral et le bouton volume haut.</p>
                        <p>La capture est ensuite dans votre galerie.</p>
                    </div>
                </details>

                <p class="flex items-start gap-2 rounded-2xl bg-veil/5 px-3 py-2.5 text-sm text-slate-200 ring-1 ring-veil/10">
                    <flux:icon.bolt class="mt-0.5 size-4 shrink-0 text-gold-300" />
                    <span>
                        @if ($liveSession && $needsVerification)
                            Votre place reste réservée pendant la vérification de votre paiement ({{ $payments['verification_hours'] }}), puis elle est confirmée.
                        @elseif ($liveSession)
                            Dès l'envoi, votre place est confirmée à titre provisoire. Votre paiement est ensuite vérifié par notre équipe.
                        @elseif ($needsVerification)
                            Votre accès sera ouvert dès que votre paiement aura été vérifié et validé ({{ $payments['verification_hours'] }}).
                        @else
                            Dès l'envoi, votre formation s'ouvre à titre provisoire. Votre paiement est ensuite vérifié par notre équipe.
                        @endif
                    </span>
                </p>

                <p class="flex items-start gap-2 text-xs text-slate-400">
                    <flux:icon.lock-closed class="mt-0.5 size-3.5 shrink-0" />
                    Votre capture reste privée : seule notre équipe peut la consulter pour vérifier votre paiement.
                </p>

                <button type="button" x-show="!sending" @click="goTo(3)" class="inline-flex min-h-11 items-center gap-1.5 py-2 text-sm font-semibold text-slate-300 hover:text-white">
                    <flux:icon.arrow-left class="size-4" /> Revoir le numéro et le montant
                </button>
            </form>
        </div>

        {{-- Aide, visible à chaque étape --}}
        <div class="border-t border-veil/10 p-5 sm:p-6">
            <a href="{{ $helpUrls[$initialPhase - 1] }}" :href="helpUrls[step - 1]" target="_blank" rel="noopener" class="btn-ghost w-full" data-test="payment-help">
                <span class="grid size-8 place-items-center rounded-full bg-[#25D366] text-white" aria-hidden="true"><x-site.whatsapp-icon class="size-4.5" /></span> Besoin d'aide pour payer ?
            </a>
        </div>
    </div>
@endif
