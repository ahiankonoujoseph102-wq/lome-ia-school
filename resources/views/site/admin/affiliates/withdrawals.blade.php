@php
    use App\Support\Money;

    $quickReasons = [
        'Coordonnées de retrait incorrectes : corrigez-les puis refaites la demande.',
        'Nom du titulaire différent du nom sur le compte Mobile Money.',
        'Activité suspecte : contactez-nous.',
    ];
@endphp

<x-layouts::site title="Retraits à payer" noindex>
    <div class="mx-auto max-w-5xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Administration
        </a>

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">Retraits à payer</h1>
                <p class="mt-1 text-slate-400">Envoyez le montant par Mobile Money, puis indiquez les frais réels : ils sont déduits du montant demandé.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.affiliates.index') }}" class="btn-ghost !px-4 !py-2 !text-sm"><flux:icon.users class="size-4" /> Affiliés</a>
                <span class="rounded-full bg-gold-400 px-4 py-1.5 text-sm font-bold text-night-950">{{ $withdrawals->count() }} à payer</span>
            </div>
        </div>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif
        @error('withdrawal')
            <x-site.alert type="error" class="mt-5">{{ $message }}</x-site.alert>
        @enderror

        <div class="mt-6 space-y-6">
            @forelse ($withdrawals as $withdrawal)
                @php
                    $affiliate = $withdrawal->affiliate;
                    $user = $affiliate->user;
                    $summary = $summaries[$withdrawal->id];
                    $payErrors = $errors->getBag('pay'.$withdrawal->id);
                    $rejectErrors = $errors->getBag('reject'.$withdrawal->id);
                @endphp

                <article class="card overflow-hidden" id="retrait-{{ $withdrawal->id }}" data-test="withdrawal">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4">
                        <div>
                            <p class="text-xs text-slate-500">Demandé le {{ $withdrawal->created_at?->translatedFormat('j F Y à H\hi') }} ({{ $withdrawal->created_at?->diffForHumans() }})</p>
                            <p class="mt-0.5 font-semibold text-white">{{ $user->name }} <span class="font-mono text-xs text-slate-400">· {{ $affiliate->code }}</span></p>
                        </div>
                        <p class="text-2xl font-bold text-gold-300">{{ Money::fcfa($withdrawal->amount) }}</p>
                    </div>

                    <div class="space-y-4 p-5">
                        @include('site.partials.alert-list', ['alerts' => $alerts[$withdrawal->id]])

                        <div class="grid gap-5 lg:grid-cols-2">
                            <div class="rounded-2xl bg-gold-400/10 p-4 ring-1 ring-gold-400/30">
                                <p class="text-xs font-semibold tracking-wide text-gold-200 uppercase">Envoyer sur</p>
                                <p class="mt-2 text-lg font-bold text-white">{{ $withdrawal->payout_method_name }}</p>
                                <p class="font-mono text-xl font-bold text-white">{{ $withdrawal->formattedPhone() }}</p>
                                <p class="mt-1 text-sm text-gold-100">Titulaire : {{ $withdrawal->payout_name }}</p>
                                <p class="text-xs text-gold-100/70">{{ config('site.payments.countries.'.$withdrawal->payout_country.'.name') }}</p>
                            </div>

                            <dl class="divide-y divide-white/10 text-sm">
                                <div class="py-2.5">
                                    <dt class="text-slate-400">Affilié</dt>
                                    <dd class="mt-0.5 font-semibold text-white">{{ $user->name }}</dd>
                                    <dd class="text-slate-300">{{ $user->email }}</dd>
                                    @if ($user->phone)
                                        <dd><a href="https://wa.me/{{ ltrim($user->phone, '+') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-[#25D366]"><x-site.whatsapp-icon class="size-4" /> {{ $user->formatted_phone }}</a></dd>
                                    @endif
                                    <dd class="text-xs text-slate-500">Affilié depuis le {{ $affiliate->created_at?->translatedFormat('j F Y') }}</dd>
                                </div>
                                @foreach ([
                                    'Clics · inscriptions · ventes' => $summary['clicks'].' · '.$summary['signups'].' · '.$summary['sales'],
                                    'En attente' => Money::fcfa($summary['pending']),
                                    'Solde restant (hors cette demande)' => Money::fcfa($summary['balance']),
                                    'Déjà payé' => Money::fcfa($summary['paid']),
                                ] as $label => $value)
                                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-0.5 py-2.5">
                                        <dt class="text-slate-400">{{ $label }}</dt>
                                        <dd class="font-medium text-white">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        <div class="grid gap-3 border-t border-white/10 pt-4" x-data="{ mode: @js($rejectErrors->any() ? 'reject' : ($payErrors->any() ? 'pay' : null)), reason: @js($rejectErrors->any() ? old('reason', '') : '') }">
                            <div class="grid gap-3 sm:grid-cols-2" x-show="mode === null">
                                <button type="button" @click="mode = 'pay'" class="btn w-full bg-emerald-500 text-night-950 hover:bg-emerald-400"><flux:icon.check-circle class="size-5" /> Marquer comme payé</button>
                                <button type="button" @click="mode = 'reject'" class="btn w-full border border-rose-400/40 bg-rose-500/10 text-rose-200 hover:bg-rose-500/20"><flux:icon.x-circle class="size-5" /> Refuser</button>
                            </div>

                            <form method="POST" action="{{ route('admin.affiliates.withdrawals.pay', $withdrawal) }}" x-show="mode === 'pay'" x-cloak class="space-y-3" x-data="{ fee: @js((string) old('fee', '')) }">
                                @csrf
                                <p class="text-sm font-semibold text-white">Paiement envoyé</p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="space-y-1.5">
                                        <label for="frais-{{ $withdrawal->id }}" class="field-label">Frais d'envoi réels (FCFA)</label>
                                        <input id="frais-{{ $withdrawal->id }}" name="fee" type="number" min="0" step="1" inputmode="numeric" x-model="fee" required class="field-input">
                                        @if ($payErrors->has('fee')) <p class="text-sm text-rose-300">{{ $payErrors->first('fee') }}</p> @endif
                                    </div>
                                    <div class="space-y-1.5">
                                        <label for="transaction-{{ $withdrawal->id }}" class="field-label">Identifiant de transaction</label>
                                        <input id="transaction-{{ $withdrawal->id }}" name="transaction_id" type="text" value="{{ $payErrors->any() ? old('transaction_id') : '' }}" required class="field-input font-mono">
                                        @if ($payErrors->has('transaction_id')) <p class="text-sm text-rose-300">{{ $payErrors->first('transaction_id') }}</p> @endif
                                    </div>
                                </div>
                                <p class="text-sm text-slate-300">Montant reçu par l'affilié : <span class="font-bold text-emerald-300" x-text="new Intl.NumberFormat('fr-FR').format(Math.max(0, {{ $withdrawal->amount }} - (parseInt(fee) || 0))).replace(/ | /g, ' ') + ' FCFA'"></span></p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button type="submit" class="btn w-full bg-emerald-500 text-night-950 hover:bg-emerald-400">Confirmer le paiement</button>
                                    <button type="button" @click="mode = null" class="btn-ghost w-full">Annuler</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('admin.affiliates.withdrawals.reject', $withdrawal) }}" x-show="mode === 'reject'" x-cloak class="space-y-3">
                                @csrf
                                <p class="text-sm font-semibold text-white">Motif du refus (envoyé à l'affilié)</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($quickReasons as $quickReason)
                                        <button type="button" @click="reason = @js($quickReason)" class="rounded-full bg-white/5 px-3 py-1.5 text-xs text-slate-200 ring-1 ring-white/15 hover:bg-white/10">{{ $quickReason }}</button>
                                    @endforeach
                                </div>
                                <label for="motif-{{ $withdrawal->id }}" class="sr-only">Motif du refus</label>
                                <textarea id="motif-{{ $withdrawal->id }}" name="reason" x-model="reason" rows="3" required class="field-input"></textarea>
                                @if ($rejectErrors->has('reason')) <p class="text-sm text-rose-300">{{ $rejectErrors->first('reason') }}</p> @endif
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button type="submit" class="btn w-full bg-rose-500 text-white hover:bg-rose-400">Confirmer le refus</button>
                                    <button type="button" @click="mode = null" class="btn-ghost w-full">Annuler</button>
                                </div>
                                <p class="text-xs text-slate-500">Le montant revient dans le solde de l'affilié.</p>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="card p-10 text-center">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-emerald-400/15 text-emerald-300"><flux:icon.check-badge class="size-7" /></span>
                    <p class="mt-4 font-semibold text-white">Aucun retrait à payer pour le moment.</p>
                    <p class="mt-1 text-sm text-slate-400">Vous recevez un e-mail à chaque nouvelle demande.</p>
                </div>
            @endforelse
        </div>

        @if ($recent->isNotEmpty())
            <section class="mt-12">
                <h2 class="text-lg font-semibold text-white">Derniers retraits traités</h2>
                <ul class="mt-4 divide-y divide-white/10 rounded-3xl border border-white/10">
                    @foreach ($recent as $done)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-white">{{ $done->affiliate->user->name }} · {{ Money::fcfa($done->amount) }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $done->processed_at?->translatedFormat('j F Y à H\hi') }}
                                    @if ($done->status === \App\Enums\WithdrawalStatus::Paid)
                                        · frais {{ Money::fcfa((int) $done->fee) }} · reçu {{ Money::fcfa((int) $done->net_amount) }} · {{ $done->transaction_id }}
                                    @else
                                        · {{ $done->rejection_reason }}
                                    @endif
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $done->status->badgeClasses() }}">{{ $done->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts::site>
