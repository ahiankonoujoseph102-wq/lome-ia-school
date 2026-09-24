{{--
    Paiement Mobile Money manuel : choix du pays et du moyen, instructions, puis formulaire de preuve de paiement.
    Sert aussi au supplément d'une session en présentiel ($liveSession).
--}}
@php
    $liveSession ??= null;
    $user ??= auth()->user();
    $amount = $liveSession ? $liveSession->formattedSupplement() : $formation->formatted_price;
    $payAction = $liveSession ? route('student.sessions.pay', $liveSession) : route('checkout.pay', $formation);
    $payments = config('site.payments');
    $samePayment = $unverifiedPurchase && ($liveSession
        ? $unverifiedPurchase->live_session_id === $liveSession->id
        : $unverifiedPurchase->formation_id === $formation->id && ! $unverifiedPurchase->isSessionSupplement());
@endphp

@if ($unverifiedPurchase)
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
        x-data="mobileMoneyPayment({ methods: @js($methods), initialMethod: @js(old('payment_method')) })"
        class="space-y-6"
    >
        @error('formation')
            <x-site.alert type="error">{{ $message }}</x-site.alert>
        @enderror

        {{-- Étapes 1 et 2 : pays et moyen de paiement --}}
        <section class="card p-5 sm:p-6" aria-labelledby="titre-paiement">
            <div class="flex items-center gap-3">
                <x-site.avatar :user="$user" class="size-11 text-sm" />
                <div class="min-w-0">
                    <p class="text-sm text-slate-400">Connecté en tant que</p>
                    <p class="truncate font-semibold text-white">{{ $user->name }}</p>
                </div>
            </div>

            <h2 id="titre-paiement" class="mt-6 text-xl font-bold text-white">Payer par Mobile Money</h2>
            <p class="mt-1 text-sm text-slate-400">Envoyez le montant sur l'un de nos numéros, puis déposez votre preuve de paiement ci-dessous.</p>

            <h3 class="mt-6 flex items-center gap-2 text-sm font-semibold text-white">
                <span class="grid size-6 place-items-center rounded-full bg-gold-400 text-xs font-bold text-night-950">1</span> Votre pays
            </h3>
            <div class="mt-3 grid grid-cols-3 gap-2" role="radiogroup" aria-label="Pays">
                @foreach ($countries as $code => $country)
                    <button
                        type="button"
                        role="radio"
                        @click="chooseCountry(@js($code))"
                        :aria-checked="(country === @js($code)).toString()"
                        :class="country === @js($code) ? 'bg-electric-600/25 text-white ring-electric-400' : 'bg-veil/5 text-slate-300 ring-veil/10 hover:bg-veil/10'"
                        class="flex flex-col items-center gap-1 rounded-2xl px-2 py-3 text-sm font-semibold ring-1 transition"
                    >
                        <span class="text-2xl leading-none" aria-hidden="true">{{ $country['flag'] }}</span>
                        <span class="leading-tight">{{ $country['name'] }}</span>
                    </button>
                @endforeach
            </div>

            <h3 class="mt-6 flex items-center gap-2 text-sm font-semibold text-white">
                <span class="grid size-6 place-items-center rounded-full bg-gold-400 text-xs font-bold text-night-950">2</span> Votre moyen de paiement
            </h3>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Moyen de paiement">
                <template x-for="item in countryMethods" :key="item.key">
                    <button
                        type="button"
                        role="radio"
                        @click="chooseMethod(item.key)"
                        :aria-checked="(method === item.key).toString()"
                        :class="method === item.key ? 'bg-veil/10 ring-gold-400 ring-2' : 'bg-veil/5 ring-veil/10 hover:bg-veil/10'"
                        class="flex items-center gap-3 rounded-2xl p-3 text-left ring-1 transition"
                    >
                        <span class="method-dot grid size-10 shrink-0 place-items-center rounded-full text-xs font-bold text-night-950" :style="`background:${item.color};--dot-inverse:${item.inverse_color}`" x-text="item.initials" aria-hidden="true"></span>
                        <span class="text-sm leading-tight font-semibold text-white" x-text="item.name"></span>
                    </button>
                </template>
            </div>

            {{-- Instructions du moyen choisi --}}
            <div x-ref="details" class="scroll-mt-24">
                <template x-if="selected">
                    <div class="mt-6 overflow-hidden rounded-3xl border border-gold-400/30 bg-linear-to-br from-gold-400/10 via-night-900 to-night-950">
                        <div class="space-y-5 p-5 sm:p-6">
                            <p x-show="selected.note" class="flex items-start gap-2 rounded-2xl bg-electric-500/10 px-3 py-2.5 text-sm text-electric-100 ring-1 ring-electric-400/30">
                                <flux:icon.user-circle class="mt-0.5 size-4 shrink-0" /> <span x-text="selected.note"></span>
                            </p>

                            <div>
                                <p class="text-sm text-slate-400">Montant exact à envoyer</p>
                                <p class="text-3xl font-bold text-gold-300">{{ $amount }}</p>
                            </div>

                            <div>
                                <p class="text-sm text-slate-400">Au numéro <span x-text="selected.name"></span></p>
                                <div class="mt-1 flex flex-wrap items-center gap-3">
                                    <p x-ref="number" class="text-2xl font-bold tracking-wide text-white tabular-nums sm:text-3xl" x-text="selected.number"></p>
                                    <button type="button" @click="copyNumber()" class="btn-ghost !px-4 !py-2 !text-sm" :aria-label="`Copier le numéro ${selected.number}`">
                                        <flux:icon.clipboard-document class="size-4" x-show="!copied" />
                                        <flux:icon.check class="size-4 text-emerald-300" x-show="copied" x-cloak />
                                        <span x-text="copied ? 'Copié' : 'Copier'">Copier</span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm text-slate-400">Nom du bénéficiaire</p>
                                <p class="text-lg font-semibold text-white" x-text="selected.beneficiary"></p>
                            </div>

                            <p class="flex items-start gap-2 rounded-2xl bg-veil/5 px-3 py-2.5 text-sm text-slate-200 ring-1 ring-veil/10">
                                <flux:icon.information-circle class="mt-0.5 size-4 shrink-0 text-gold-300" />
                                <span>{{ $payments['fees_notice'] }} Le montant reçu doit être exactement de <strong class="text-white">{{ $amount }}</strong>.</span>
                            </p>

                            <ol class="space-y-2.5 text-sm text-slate-300">
                                @foreach ($payments['steps'] as $step)
                                    <li class="flex gap-3">
                                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-veil/10 text-xs font-bold text-white">{{ $loop->iteration }}</span>
                                        <span>{{ $step }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </template>
                <p x-show="!selected" class="mt-4 text-sm text-slate-500">Choisissez un moyen de paiement pour afficher le numéro et le nom du bénéficiaire.</p>
            </div>
        </section>

        {{-- Étape 3 : preuve de paiement --}}
        <form method="POST" action="{{ $payAction }}" enctype="multipart/form-data" class="card space-y-5 p-5 sm:p-6" aria-labelledby="titre-preuve">
            @csrf

            <div>
                <h2 id="titre-preuve" class="flex items-center gap-2 text-lg font-semibold text-white">
                    <span class="grid size-6 place-items-center rounded-full bg-gold-400 text-xs font-bold text-night-950">3</span> Envoyez votre preuve de paiement
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    @if ($liveSession && $needsVerification)
                        Votre place reste réservée pendant la vérification, {{ $payments['verification_hours'] }}, puis elle est confirmée.
                    @elseif ($liveSession)
                        Dès l'envoi, votre place est confirmée à titre provisoire. Votre paiement est ensuite vérifié par notre équipe.
                    @elseif ($needsVerification)
                        Votre accès sera ouvert dès que votre paiement aura été vérifié, {{ $payments['verification_hours'] }}.
                    @else
                        Dès l'envoi, votre formation s'ouvre à titre provisoire. Votre paiement est ensuite vérifié par notre équipe.
                    @endif
                </p>
            </div>

            <div class="space-y-1.5">
                <label for="payment_method" class="field-label">Moyen de paiement utilisé</label>
                <select id="payment_method" name="payment_method" x-model="method" @change="syncCountry()" required @class(['field-input', 'field-input-error' => $errors->has('payment_method')])>
                    <option value="">Choisir…</option>
                    @foreach ($countries as $code => $country)
                        <optgroup label="{{ $country['name'] }}">
                            @foreach ($methods as $item)
                                @if ($item['country'] === $code)
                                    <option value="{{ $item['key'] }}" @selected(old('payment_method') === $item['key'])>{{ $item['name'] }} · {{ $item['number'] }}</option>
                                @endif
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('payment_method')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <x-site.field name="payer_phone" label="Numéro qui a effectué le paiement" type="tel" inputmode="tel" autocomplete="tel" required placeholder="Ex. : 90 12 34 56" hint="Sans l'indicatif si c'est un numéro du pays choisi." />
            <x-site.field name="payer_name" label="Nom de l'expéditeur" required autocomplete="name" placeholder="Nom du titulaire du compte Mobile Money" />
            <x-site.field name="transaction_id" label="Identifiant de la transaction" required autocomplete="off" autocapitalize="characters" placeholder="Tel qu'indiqué dans le SMS de confirmation" hint="Il figure dans le SMS de confirmation de votre paiement." />

            <div class="space-y-1.5">
                <label for="proof" class="field-label">Capture d'écran du paiement</label>
                <label for="proof" @class([
                    'flex cursor-pointer flex-col items-center gap-3 rounded-2xl border border-dashed px-4 py-6 text-center transition hover:bg-veil/5',
                    'border-rose-400/60' => $errors->has('proof'),
                    'border-veil/20' => ! $errors->has('proof'),
                ])>
                    <template x-if="preview">
                        <img :src="preview" alt="Aperçu de la capture choisie" class="max-h-64 rounded-xl object-contain">
                    </template>
                    <span x-show="!preview" class="grid size-12 place-items-center rounded-2xl bg-electric-500/15 text-electric-300">
                        <flux:icon.photo class="size-6" />
                    </span>
                    <span class="text-sm font-semibold text-white" x-text="preview ? 'Changer la capture' : 'Choisir la capture d\'écran'">Choisir la capture d'écran</span>
                    <span class="text-xs text-slate-500">Image JPG, PNG ou WebP, {{ intdiv((int) $payments['proof_max_kb'], 1024) }} Mo maximum.</span>
                </label>
                <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only" @change="previewProof($event)">
                @error('proof')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn-gold w-full" data-test="send-proof-button">
                <flux:icon.paper-airplane class="size-5" /> Envoyer ma preuve de paiement
            </button>

            <p class="flex items-start gap-2 text-xs text-slate-500">
                <flux:icon.lock-closed class="mt-0.5 size-3.5 shrink-0" />
                Votre capture reste privée : seule notre équipe peut la consulter pour vérifier votre paiement.
            </p>
        </form>
    </div>
@endif
