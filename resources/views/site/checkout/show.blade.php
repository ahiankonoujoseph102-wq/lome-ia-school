@php
    $user = auth()->user();
    $activeTab = old('form') === 'login' ? 'login' : 'register';
    $moduleCount = $formation->publicCurriculum()->count();
    $lessonCount = $formation->orderedPublishedLessons()->count();
    $paying = $user && ! $alreadyEnrolled;
    // Message WhatsApp de l'en-tête : formation et étape affichée (mis à jour par le paiement à chaque étape).
    $whatsappMessage = \App\Support\WhatsAppMessages::payment($formation, null, \App\Support\WhatsAppMessages::paymentStep($user, $unverifiedPurchase ?? null, (bool) $alreadyEnrolled));
@endphp

<x-layouts::site title="Récapitulatif de commande" :whatsapp-message="$whatsappMessage" noindex>
    <section class="relative isolate overflow-hidden">
        <div class="glow pointer-events-none absolute inset-0 -z-10 opacity-70"></div>

        <div class="mx-auto max-w-5xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
            <a href="{{ route('formations.show', $formation) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
                <flux:icon.arrow-left class="size-4" /> Retour à la formation
            </a>

            <h1 class="mt-4 text-2xl font-bold tracking-tight text-white sm:text-4xl">Récapitulatif de commande</h1>

            {{-- Étapes du parcours (le paiement a ensuite ses propres étapes numérotées) --}}
            @guest
            <ol class="mt-6 flex items-center gap-2 text-xs font-semibold sm:text-sm" aria-label="Étapes de la commande">
                @foreach (['Mon compte', 'Paiement', 'Accès à la formation'] as $step)
                    @php $done = $loop->first && $user; $current = ($loop->first && ! $user) || ($loop->index === 1 && $user); @endphp
                    <li class="flex items-center gap-2">
                        <span @class([
                            'grid size-7 place-items-center rounded-full ring-1',
                            'bg-emerald-400/15 text-emerald-300 ring-emerald-400/40' => $done,
                            'bg-gold-400 text-night-950 ring-gold-300' => $current,
                            'bg-veil/5 text-slate-400 ring-veil/15' => ! $done && ! $current,
                        ])>
                            @if ($done)
                                <flux:icon.check class="size-4" />
                            @else
                                {{ $loop->iteration }}
                            @endif
                        </span>
                        <span @class(['text-white' => $current, 'text-slate-400' => ! $current])>{{ $step }}</span>
                        @unless ($loop->last)
                            <span class="h-px w-4 bg-veil/15 sm:w-10" aria-hidden="true"></span>
                        @endunless
                    </li>
                @endforeach
            </ol>
            @endguest

            <div class="mt-6 grid sm:mt-8 items-start gap-6 lg:grid-cols-[1fr_380px]">
                {{-- Colonne principale --}}
                <div class="order-2 space-y-6 lg:order-1">
                    @if ($alreadyEnrolled)
                        <div class="card p-6">
                            <x-site.alert>Vous avez déjà accès à cette formation.</x-site.alert>
                            <a href="{{ route('student.formations.show', $formation) }}" class="btn-gold mt-5 w-full sm:w-auto">Ouvrir la formation</a>
                        </div>
                    @elseif ($user)
                        @include('site.checkout.partials.payment')
                    @else
                        {{-- Création de compte ou connexion sans quitter la page --}}
                        <div class="card overflow-hidden" x-data="{ tab: @js($activeTab) }">
                            <div class="p-6 pb-0">
                                <h2 class="text-lg font-semibold text-white">Votre compte</h2>
                                <p class="mt-1 text-sm text-slate-400">Il vous permet de retrouver votre formation à tout moment. Vous revenez ici juste après.</p>
                            </div>
                            <div class="mx-6 mt-5 grid grid-cols-2 gap-1 rounded-full bg-veil/5 p-1 ring-1 ring-veil/10" role="tablist">
                                <button type="button" role="tab" @click="tab = 'register'" :aria-selected="(tab === 'register').toString()"
                                    :class="tab === 'register' ? 'bg-electric-600 text-white shadow' : 'text-slate-300 hover:text-white'"
                                    class="rounded-full px-3 py-2.5 text-sm font-semibold transition">Créer un compte</button>
                                <button type="button" role="tab" @click="tab = 'login'" :aria-selected="(tab === 'login').toString()"
                                    :class="tab === 'login' ? 'bg-electric-600 text-white shadow' : 'text-slate-300 hover:text-white'"
                                    class="rounded-full px-3 py-2.5 text-sm font-semibold transition">J'ai déjà un compte</button>
                            </div>
                            <div class="p-6">
                                <div x-show="tab === 'register'" @if ($activeTab !== 'register') x-cloak @endif>
                                    @include('site.auth.partials.register-form', ['prefix' => 'commande-inscription', 'showErrors' => $activeTab === 'register'])
                                </div>
                                <div x-show="tab === 'login'" @if ($activeTab !== 'login') x-cloak @endif>
                                    @include('site.auth.partials.login-form', ['prefix' => 'commande-connexion', 'showErrors' => $activeTab === 'login'])
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Résumé de la commande --}}
                <aside class="order-1 lg:sticky lg:top-24 lg:order-2">
                    <div class="card overflow-hidden">
                        {{-- Sur téléphone, pendant le paiement, le résumé reste court : titre, code promo et total --}}
                        <x-site.formation-cover :formation="$formation" :class="\Illuminate\Support\Arr::toCssClasses(['aspect-[16/7]', 'hidden lg:block' => $paying])" eager />
                        <div class="space-y-5 p-5">
                            <div>
                                <p class="text-xs font-semibold text-electric-300">{{ $formation->category->label() }}</p>
                                <h2 class="mt-1 text-lg leading-snug font-semibold text-white">{{ $formation->title }}</h2>
                                <x-site.formation-formats :formation="$formation" class="mt-2" />
                            </div>

                            <div @class(['hidden lg:block' => $paying])>
                                <p class="text-sm font-semibold text-white">Ce qui est inclus</p>
                                <ul class="mt-3 space-y-2.5 text-sm text-slate-300">
                                    @if ($needsVerification)
                                        <li class="flex gap-2"><flux:icon.shield-check class="size-4 shrink-0 text-gold-400" /> Accès ouvert après vérification du paiement</li>
                                    @else
                                        <li class="flex gap-2"><flux:icon.bolt class="size-4 shrink-0 text-gold-400" /> Accès immédiat dès l'envoi de la preuve de paiement</li>
                                    @endif
                                    @if ($moduleCount > 0)
                                        <li class="flex gap-2"><flux:icon.play-circle class="size-4 shrink-0 text-gold-400" /> Programme : {{ $moduleCount }} {{ $moduleCount > 1 ? 'modules' : 'module' }}, {{ $lessonCount }} {{ $lessonCount > 1 ? 'leçons' : 'leçon' }}</li>
                                    @endif
                                    @if ($liveOffer = \App\Support\LiveOffer::for($formation))
                                        <li class="flex gap-2" data-test="checkout-live-offer"><flux:icon.video-camera class="size-4 shrink-0 text-gold-400" /> En direct sur Google Meet : {{ \Illuminate\Support\Str::lcfirst($liveOffer->period()) }}, {{ \Illuminate\Support\Str::lcfirst($liveOffer->schedule()) }}</li>
                                        @foreach ($liveOffer->included() as $item)
                                            <li class="flex gap-2"><flux:icon.check-circle class="size-4 shrink-0 text-gold-400" /> {{ $item }}</li>
                                        @endforeach
                                    @elseif ($formation->isPack())
                                        {{-- Offre d'accompagnement : formations incluses et Groupe VIP --}}
                                        @foreach ($formation->includedFormations as $included)
                                            <li class="flex gap-2" data-test="checkout-pack-item"><flux:icon.check-circle class="size-4 shrink-0 text-gold-400" /> {{ $included->title }}</li>
                                        @endforeach
                                    @else
                                        <li class="flex gap-2"><flux:icon.chat-bubble-left-right class="size-4 shrink-0 text-gold-400" /> Groupe privé d'accompagnement</li>
                                    @endif
                                    @foreach ($formation->bonuses as $bonus)
                                        <li class="flex gap-2"><flux:icon.gift class="size-4 shrink-0 text-gold-400" /> {{ $bonus }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            {{-- Code promo : le montant à payer tient compte de la remise --}}
                            @unless ($alreadyEnrolled)
                                <details class="group border-t border-veil/10 pt-4" @if ($promoCode !== '') open @endif>
                                    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold text-electric-200">
                                        Vous avez un code promo ?
                                        <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" />
                                    </summary>
                                    <form method="GET" action="{{ route('checkout.show', $formation) }}" class="mt-3 flex gap-2">
                                        <label for="code-promo" class="sr-only">Code promo</label>
                                        <input id="code-promo" name="code" value="{{ $promoCode }}" maxlength="40" autocomplete="off" autocapitalize="characters" placeholder="Code promo" @class(['field-input min-w-0 flex-1 uppercase', 'field-input-error' => $promoError])>
                                        <button type="submit" class="shrink-0 rounded-full px-4 text-sm font-semibold text-white ring-1 ring-veil/20 hover:bg-veil/10">Appliquer</button>
                                    </form>
                                    @if ($promoError)
                                        <p class="mt-2 text-sm text-rose-300" data-test="promo-error">{{ $promoError }}</p>
                                    @elseif ($quote)
                                        <p class="mt-2 flex items-center justify-between gap-2 text-sm text-emerald-200" data-test="promo-applied">
                                            <span class="flex items-center gap-1.5"><flux:icon.check-circle class="size-4" /> Code {{ $quote->code->code }} appliqué</span>
                                            <a href="{{ route('checkout.show', $formation) }}" class="text-slate-400 underline hover:text-white">Retirer</a>
                                        </p>
                                    @endif
                                </details>
                            @endunless

                            <div class="border-t border-veil/10 pt-4">
                                @if ($quote)
                                    <div class="flex items-center justify-between text-sm text-slate-400">
                                        <span>Prix de la formation</span>
                                        <s data-test="original-price">{{ $formation->formatted_price }}</s>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between text-sm text-emerald-200">
                                        <span>Remise ({{ $quote->code->valueLabel() }})</span>
                                        <span>-{{ \App\Support\Money::fcfa($quote->discount) }}</span>
                                    </div>
                                @endif
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-slate-300">Total à payer</span>
                                    <span class="text-2xl font-bold text-gold-300" data-test="total-to-pay">{{ $payAmount }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</x-layouts::site>
