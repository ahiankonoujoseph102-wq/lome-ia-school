{{--
    Paiement Mobile Money manuel, en 4 étapes, une à la fois (pensé pour le téléphone) :
    1. pays, 2. opérateur, 3. montant, numéro et bénéficiaire (+ bouton « Payer maintenant » par code USSD),
    4. envoi de la capture d'écran du paiement, rien d'autre.
    Sert aussi au supplément d'une session en présentiel ($liveSession).
--}}
@php
    use App\Payments\PaymentMethods;
    use App\Support\Device;
    use App\Support\Money;
    use App\Support\WhatsApp;

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
    $helpUrl = WhatsApp::url($liveSession
        ? 'Bonjour Lomé IA School, j\'ai besoin d\'aide pour payer le supplément de la session de la formation « '.$formation->title.' » (montant : '.$amount.').'
        : 'Bonjour Lomé IA School, j\'ai besoin d\'aide pour payer la formation « '.$formation->title.' » (montant : '.$amount.').');
    $initialMethod = $paymentMethods->has((string) old('payment_method')) ? (string) old('payment_method') : null;
    $stepLabels = ['Pays', 'Opérateur', 'Paiement', 'Capture'];
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
            initialMethod: @js($initialMethod),
            initialStep: @js($initialMethod ? ($errors->has('proof') ? 4 : 3) : 1),
            maxBytes: @js((int) $payments['proof_max_kb'] * 1024),
            maxMb: @js($maxMb),
            labels: @js($stepLabels),
        })"
        x-ref="payment"
        @pageshow.window="sending = false"
        class="card scroll-mt-24 overflow-hidden"
        data-test="payment-steps"
    >
        {{-- En-tête : progression --}}
        <div class="border-b border-veil/10 p-5 sm:p-6">
            <h2 class="text-xl font-bold text-white">Payer par Mobile Money</h2>
            <p class="mt-1 text-sm text-slate-400">
                Étape <span x-text="step">1</span> sur 4 :
                <span class="text-slate-200" x-text="labels[step - 1]">{{ $stepLabels[0] }}</span>
            </p>
            <ol class="mt-3 grid grid-cols-4 gap-1.5" aria-hidden="true">
                @foreach ($stepLabels as $label)
                    <li class="h-1.5 rounded-full" :class="step >= {{ $loop->iteration }} ? 'bg-gold-500' : 'bg-veil/15'"></li>
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

            {{-- Étape 1 : pays --}}
            <section x-show="step === 1" @if ($initialMethod) x-cloak @endif aria-labelledby="etape-pays" data-step="1">
                <h3 id="etape-pays" class="text-lg font-semibold text-white">Dans quel pays payez-vous ?</h3>
                <div class="mt-4 grid gap-3" role="list">
                    @foreach ($countries as $code => $country)
                        <button type="button" role="listitem" @click="chooseCountry(@js($code))"
                            class="flex min-h-16 w-full items-center gap-4 rounded-2xl bg-veil/5 px-4 py-3 text-left ring-1 ring-veil/15 transition hover:bg-veil/10 active:scale-[0.99]"
                            data-test="country-{{ $code }}">
                            <span class="text-3xl leading-none" aria-hidden="true">{{ $country['flag'] }}</span>
                            <span class="flex-1 text-lg font-semibold text-white">{{ $country['name'] }}</span>
                            <flux:icon.chevron-right class="size-5 text-slate-400" />
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Étape 2 : opérateur --}}
            <section x-show="step === 2" x-cloak aria-labelledby="etape-operateur" data-step="2">
                <h3 id="etape-operateur" class="text-lg font-semibold text-white">Choisissez votre opérateur</h3>
                <div class="mt-4 grid gap-3">
                    @foreach ($paymentMethods as $method)
                        <button type="button" x-show="country === @js($method->country)" @click="chooseMethod(@js($method->key))"
                            class="flex min-h-16 w-full items-center gap-4 rounded-2xl bg-veil/5 px-4 py-3 text-left ring-1 ring-veil/15 transition hover:bg-veil/10 active:scale-[0.99]"
                            data-test="method-{{ $method->key }}">
                            <span class="method-dot grid size-11 shrink-0 place-items-center rounded-full text-sm font-bold text-night-950" style="background:{{ $method->color }};--dot-inverse:{{ $method->inverseColor() }}" aria-hidden="true">{{ $method->initials() }}</span>
                            <span class="flex-1 text-lg font-semibold text-white">{{ $method->name }}</span>
                            <flux:icon.chevron-right class="size-5 text-slate-400" />
                        </button>
                    @endforeach
                </div>
                <button type="button" @click="goTo(1)" class="mt-4 inline-flex items-center gap-1.5 py-2 text-sm font-semibold text-slate-300 hover:text-white">
                    <flux:icon.arrow-left class="size-4" /> Changer de pays
                </button>
            </section>

            {{-- Étape 3 : montant, numéro, bénéficiaire et bouton « Payer maintenant » --}}
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

                    <div class="divide-y divide-veil/10 rounded-2xl bg-night-900 ring-1 ring-gold-400/40">
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
                                <a href="{{ $ussdUrl }}" x-show="canDial" class="btn-gold w-full" data-test="ussd-button">
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

                    <button type="button" @click="goTo(4)" class="btn w-full bg-gold-400/10 text-gold-100 ring-1 ring-gold-400/60 hover:bg-gold-400/20" data-test="paid-button">
                        J'ai payé : envoyer ma capture <flux:icon.arrow-right class="size-5" />
                    </button>
                    <button type="button" @click="goTo(2)" class="inline-flex items-center gap-1.5 py-2 text-sm font-semibold text-slate-300 hover:text-white">
                        <flux:icon.arrow-left class="size-4" /> Changer d'opérateur
                    </button>
                </section>
            @endforeach

            {{-- Étape 4 : la capture d'écran, rien d'autre --}}
            <form method="POST" action="{{ $payAction }}" enctype="multipart/form-data" x-ref="proofForm" x-show="step === 4" @unless ($initialMethod && $errors->has('proof')) x-cloak @endunless aria-labelledby="etape-capture" data-step="4" class="space-y-4">
                @csrf
                <input type="hidden" name="payment_method" :value="method" value="{{ $initialMethod }}">
                @if ($quote)
                    <input type="hidden" name="promo_code" value="{{ $quote->code->code }}">
                @endif

                <div>
                    <h3 id="etape-capture" class="text-lg font-semibold text-white">Envoyez la capture de votre paiement</h3>
                    <p class="mt-1 text-sm text-slate-300">Faites une capture d'écran du message de confirmation de votre paiement ({{ $amount }}), puis envoyez-la.</p>
                </div>

                <label for="proof" class="btn-gold w-full cursor-pointer !py-5 text-lg" :class="sending && 'pointer-events-none brightness-75'" data-test="send-proof-button">
                    <flux:icon.camera class="size-6" x-show="!sending" />
                    <svg x-show="sending" x-cloak class="size-6 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                    <span x-text="sending ? 'Envoi en cours…' : 'Envoyer la capture de mon paiement'">Envoyer la capture de mon paiement</span>
                </label>
                <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only" @change="sendProof($event)">
                <p class="text-center text-xs text-slate-400">Depuis votre galerie ou l'appareil photo. Image JPG, PNG ou WebP, {{ $maxMb }} Mo maximum.</p>

                <template x-if="preview">
                    <img :src="preview" alt="Capture choisie" class="mx-auto max-h-56 rounded-xl object-contain ring-1 ring-veil/15">
                </template>
                <p x-show="fileError" x-cloak x-text="fileError" class="text-sm text-rose-300" role="alert"></p>
                @error('proof')
                    <p class="text-sm text-rose-300" role="alert">{{ $message }}</p>
                @enderror

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

                <button type="button" x-show="!sending" @click="goTo(3)" class="inline-flex items-center gap-1.5 py-2 text-sm font-semibold text-slate-300 hover:text-white">
                    <flux:icon.arrow-left class="size-4" /> Revoir le numéro et le montant
                </button>
            </form>
        </div>

        {{-- Aide, visible à chaque étape --}}
        <div class="border-t border-veil/10 p-5 sm:p-6">
            <a href="{{ $helpUrl }}" target="_blank" rel="noopener" class="btn-ghost w-full" data-test="payment-help">
                <span class="grid size-8 place-items-center rounded-full bg-[#25D366] text-white" aria-hidden="true"><x-site.whatsapp-icon class="size-4.5" /></span> Besoin d'aide pour payer ?
            </a>
        </div>
    </div>
@endif
