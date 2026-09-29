@php
    use App\Affiliates\AffiliateAccount;
    use App\Support\Money;
    use App\Support\PhoneNumber;

    $minimum = AffiliateAccount::minimumWithdrawal();
    $emailConfirmed = auth()->user()->hasConfirmedEmail();
    $blocker = match (true) {
        ! $emailConfirmed => 'Confirmez d\'abord votre adresse e-mail pour demander un retrait.',
        $affiliate->isSuspended() => 'Les retraits ne sont pas possibles tant que votre compte affilié est suspendu.',
        $hasOpenWithdrawal => 'Vous avez déjà une demande de retrait en cours. Vous pourrez en faire une autre dès qu\'elle aura été traitée.',
        ! $affiliate->hasPayoutDetails() => 'Renseignez d\'abord vos coordonnées de retrait ci-dessous.',
        $summary['balance'] < $minimum => 'Le retrait est possible à partir de '.Money::fcfa($minimum).' de solde.',
        default => null,
    };
    $localPhone = $affiliate->payout_phone ? PhoneNumber::groupDigits(PhoneNumber::split($affiliate->payout_phone)[1]) : '';
@endphp

<x-layouts::site title="Mes retraits" variant="student">
    <div class="mx-auto max-w-3xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">Retraits</h1>
        <p class="mt-1 text-slate-400">Retirez votre solde par Mobile Money. Chaque demande est vérifiée puis payée par notre équipe.</p>

        @include('site.student.affiliate.partials.tabs')

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif
        @error('withdrawal')
            <x-site.alert type="error" class="mt-5">{{ $message }}</x-site.alert>
        @enderror

        {{-- Demande de retrait --}}
        <section class="card mt-6 p-5 sm:p-6">
            <p class="text-sm text-slate-400">Solde disponible</p>
            <p @class(['mt-1 text-3xl font-bold', 'text-gold-300' => $summary['balance'] >= 0, 'text-rose-300' => $summary['balance'] < 0])>{{ Money::fcfa($summary['balance']) }}</p>
            <ul class="mt-3 space-y-1 text-sm text-slate-400">
                <li>Minimum : {{ Money::fcfa($minimum) }}. Une seule demande à la fois.</li>
                <li>Les frais d'envoi Mobile Money sont déduits du montant retiré : le montant reçu est indiqué dans l'historique.</li>
            </ul>
            <form method="POST" action="{{ route('student.affiliate.withdraw') }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-gold w-full disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none disabled:brightness-75 sm:w-auto" @disabled($blocker !== null)>Demander le retrait de {{ Money::fcfa(max(0, $summary['balance'])) }}</button>
            </form>
            @if ($blocker)
                <p class="mt-2 text-sm text-slate-400" data-test="withdrawal-blocker">{{ $blocker }}</p>
                @unless ($emailConfirmed)
                    <a href="{{ route('email-confirmation.show', ['retour' => request()->getRequestUri()]) }}" class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-gold-300 hover:text-gold-200">Confirmer mon adresse <flux:icon.arrow-right class="size-4" /></a>
                @endunless
            @endif
        </section>

        {{-- Coordonnées de retrait --}}
        <section class="card mt-6 p-5 sm:p-6" id="coordonnees">
            <h2 class="font-semibold text-white">Coordonnées de retrait</h2>
            <p class="mt-1 text-sm text-slate-400">Le compte Mobile Money qui recevra vos retraits. Toute modification est signalée à notre équipe, pour votre sécurité.</p>

            <form
                method="POST"
                action="{{ route('student.affiliate.payout') }}"
                class="mt-4 space-y-4"
                x-data="payoutForm(@js([
                    'methods' => $methods,
                    'country' => old('payout_country', $affiliate->payout_country ?? array_key_first($countries)),
                    'method' => old('payout_method', $affiliate->payout_method ?? ''),
                ]))"
            >
                @csrf
                @method('PUT')

                <div class="space-y-1.5">
                    <label for="payout_country" class="field-label">Pays</label>
                    <select id="payout_country" name="payout_country" x-model="country" @change="chooseCountry()" @class(['field-input', 'field-input-error' => $errors->has('payout_country')])>
                        @foreach ($countries as $code => $country)
                            <option value="{{ $code }}">{{ $country['flag'] }} {{ $country['name'] }}</option>
                        @endforeach
                    </select>
                    @error('payout_country') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-1.5">
                    <label for="payout_method" class="field-label">Opérateur</label>
                    <select id="payout_method" name="payout_method" x-model="method" @class(['field-input', 'field-input-error' => $errors->has('payout_method')])>
                        <option value="">Choisir…</option>
                        <template x-for="item in countryMethods" :key="item.key">
                            <option :value="item.key" x-text="item.name" :selected="item.key === method"></option>
                        </template>
                    </select>
                    @error('payout_method') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
                </div>

                <x-site.field name="payout_phone" label="Numéro Mobile Money" type="tel" inputmode="tel" autocomplete="tel" :value="old('payout_phone', $localPhone)" hint="Le numéro qui recevra l'argent, sans l'indicatif du pays. Exemple : 90 12 34 56." />
                <x-site.field name="payout_name" label="Nom du titulaire du compte" :value="old('payout_name', $affiliate->payout_name)" hint="Tel qu'il apparaît sur le compte Mobile Money." />

                <button type="submit" class="btn-ghost w-full sm:w-auto">Enregistrer mes coordonnées</button>
            </form>
        </section>

        {{-- Historique --}}
        <section class="mt-6">
            <h2 class="text-lg font-semibold text-white">Historique des retraits</h2>
            <div class="mt-3 space-y-3">
                @forelse ($withdrawals as $withdrawal)
                    <article class="card p-4" data-test="withdrawal-row">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs text-slate-500">{{ $withdrawal->created_at?->translatedFormat('j F Y à H\hi') }}</p>
                                <p class="mt-0.5 font-semibold text-white">{{ Money::fcfa($withdrawal->amount) }} demandés</p>
                                <p class="text-xs text-slate-400">{{ $withdrawal->payout_method_name }} · {{ $withdrawal->formattedPhone() }}</p>
                            </div>
                            <span class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $withdrawal->status->badgeClasses() }}">{{ $withdrawal->status->label() }}</span>
                        </div>
                        @if ($withdrawal->status === \App\Enums\WithdrawalStatus::Paid)
                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                <dt class="text-slate-400">Frais d'envoi</dt><dd class="text-right text-white">{{ Money::fcfa((int) $withdrawal->fee) }}</dd>
                                <dt class="text-slate-400">Montant reçu</dt><dd class="text-right font-semibold text-emerald-300">{{ Money::fcfa((int) $withdrawal->net_amount) }}</dd>
                                <dt class="text-slate-400">Transaction</dt><dd class="text-right font-mono break-all text-white">{{ $withdrawal->transaction_id }}</dd>
                            </dl>
                        @elseif ($withdrawal->status === \App\Enums\WithdrawalStatus::Rejected)
                            <p class="mt-3 text-sm text-rose-200">Motif : {{ $withdrawal->rejection_reason }}. Le montant est revenu dans votre solde.</p>
                        @else
                            <p class="mt-3 text-sm text-slate-400">En cours de traitement par notre équipe. Vous êtes prévenu par e-mail dès le paiement.</p>
                        @endif
                    </article>
                @empty
                    <div class="card p-5 text-sm text-slate-400">Aucun retrait pour l'instant.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts::site>
