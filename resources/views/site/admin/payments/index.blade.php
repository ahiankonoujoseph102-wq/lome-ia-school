@php
    $quickReasons = [
        'Paiement non reçu sur notre compte.',
        'Montant reçu différent du prix de la formation.',
        'Capture d\'écran illisible ou non conforme.',
        'Identifiant de transaction introuvable.',
    ];
@endphp

<x-layouts::site title="Paiements à vérifier" noindex>
    <div class="mx-auto max-w-5xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Administration
        </a>

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">Paiements à vérifier</h1>
                <p class="mt-1 text-slate-400">Les plus récents en haut. Vérifiez chaque paiement sur votre téléphone Mobile Money avant de valider.</p>
            </div>
            <span class="rounded-full bg-gold-400 px-4 py-1.5 text-sm font-bold text-night-950">{{ $purchases->count() }} à vérifier</span>
        </div>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif
        @error('purchase')
            <x-site.alert type="error" class="mt-5">{{ $message }}</x-site.alert>
        @enderror

        <div class="mt-6 space-y-6">
            @forelse ($purchases as $purchase)
                @php
                    $user = $purchase->user;
                    $errorBag = 'reject'.$purchase->id;
                    $rejectErrors = $errors->getBag($errorBag);
                @endphp

                <article class="card overflow-hidden" id="paiement-{{ $purchase->id }}">
                    {{-- En-tête --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                            <span class="text-xs text-slate-500">{{ $purchase->receipt_number }} · {{ $purchase->created_at?->translatedFormat('j F Y à H\hi') }} ({{ $purchase->created_at?->diffForHumans() }})</span>
                        </div>
                        <p class="text-2xl font-bold text-gold-300">{{ $purchase->formatted_amount }}</p>
                    </div>

                    <div class="space-y-4 p-5">
                        @if ($purchase->viaRepresentative())
                            <p class="flex items-center gap-2 rounded-2xl bg-gold-400/15 px-4 py-3 text-sm font-semibold text-gold-100 ring-1 ring-gold-400/40">
                                <flux:icon.phone class="size-5 shrink-0" /> À confirmer avec le représentant ({{ $purchase->paymentMethod()?->countryName() }}) avant de valider.
                            </p>
                        @endif

                        @if ($alerts[$purchase->id])
                            <ul class="space-y-2">
                                @foreach ($alerts[$purchase->id] as $alert)
                                    <li @class([
                                        'flex items-start gap-2 rounded-xl px-3 py-2 text-sm ring-1',
                                        'bg-rose-400/10 text-rose-200 ring-rose-400/30' => $alert['level'] === 'danger',
                                        'bg-amber-400/10 text-amber-100 ring-amber-400/30' => $alert['level'] === 'warning',
                                        'bg-white/5 text-slate-300 ring-white/10' => $alert['level'] === 'info',
                                    ])>
                                        <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" /> {{ $alert['text'] }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                            {{-- Capture en grand --}}
                            <a href="{{ route('student.purchases.proof', $purchase) }}" target="_blank" rel="noopener" class="group block overflow-hidden rounded-2xl bg-night-950 ring-1 ring-white/10">
                                <img src="{{ route('student.purchases.proof', $purchase) }}" alt="Capture de paiement envoyée par {{ $user?->name }}" class="mx-auto max-h-[36rem] w-full object-contain" loading="lazy">
                                <span class="flex items-center justify-center gap-1.5 py-2 text-xs text-slate-400 group-hover:text-white">
                                    <flux:icon.arrows-pointing-out class="size-4" /> Ouvrir la capture en plein écran
                                </span>
                            </a>

                            {{-- Détails --}}
                            <dl class="divide-y divide-white/10 text-sm">
                                <div class="py-2.5">
                                    <dt class="text-slate-400">Élève</dt>
                                    <dd class="mt-0.5 font-semibold text-white">{{ $user?->name ?? 'Compte supprimé' }}</dd>
                                    @if ($user)
                                        <dd class="text-slate-300">{{ $user->email }}</dd>
                                        @if ($user->phone)
                                            <dd><a href="https://wa.me/{{ ltrim($user->phone, '+') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-[#25D366]"><x-site.whatsapp-icon class="size-4" /> {{ $user->formatted_phone }}</a></dd>
                                        @endif
                                        <dd class="text-xs text-slate-500">Inscrit le {{ $user->created_at?->translatedFormat('j F Y à H\hi') }}</dd>
                                    @endif
                                </div>
                                @foreach ([
                                    'Formation' => $purchase->formation->title,
                                    'Moyen de paiement' => $purchase->payment_method_name.' · '.$purchase->payment_number,
                                    'Bénéficiaire' => $purchase->payment_beneficiary,
                                    'Pays' => $purchase->paymentMethod()?->countryName() ?? strtoupper((string) $purchase->country),
                                    'Numéro qui a payé' => $purchase->payer_phone,
                                    'Nom de l\'expéditeur' => $purchase->payer_name,
                                ] as $label => $value)
                                    <div class="flex flex-wrap justify-between gap-x-4 gap-y-0.5 py-2.5">
                                        <dt class="text-slate-400">{{ $label }}</dt>
                                        <dd class="font-medium text-white">{{ $value }}</dd>
                                    </div>
                                @endforeach
                                <div class="flex flex-wrap justify-between gap-x-4 gap-y-0.5 py-2.5">
                                    <dt class="text-slate-400">Formation commencée</dt>
                                    <dd class="font-medium text-white">{{ $startedAt[$purchase->id] ? 'Oui, 1re leçon le '.$startedAt[$purchase->id]->translatedFormat('j F à H\hi') : 'Non, aucune leçon consultée' }}</dd>
                                </div>
                                <div class="py-2.5">
                                    <dt class="text-slate-400">Identifiant de transaction</dt>
                                    <dd class="mt-1 rounded-xl bg-white/5 px-3 py-2 font-mono text-base font-bold break-all text-white ring-1 ring-white/10">{{ $purchase->transaction_id }}</dd>
                                </div>
                            </dl>
                        </div>

                        {{-- Décision --}}
                        <div class="grid gap-3 border-t border-white/10 pt-4 sm:grid-cols-2" x-data="{ rejecting: @js($rejectErrors->any()), reason: @js($rejectErrors->any() ? old('reason', '') : '') }">
                            <form method="POST" action="{{ route('admin.payments.approve', $purchase) }}" x-show="!rejecting">
                                @csrf
                                <button type="submit" class="btn w-full bg-emerald-500 text-night-950 hover:bg-emerald-400 focus-visible:outline-emerald-300">
                                    <flux:icon.check-circle class="size-5" /> Valider le paiement
                                </button>
                            </form>
                            <button type="button" x-show="!rejecting" @click="rejecting = true" class="btn w-full border border-rose-400/40 bg-rose-500/10 text-rose-200 hover:bg-rose-500/20">
                                <flux:icon.x-circle class="size-5" /> Refuser
                            </button>

                            <form method="POST" action="{{ route('admin.payments.reject', $purchase) }}" x-show="rejecting" x-cloak class="space-y-3 sm:col-span-2">
                                @csrf
                                <p class="text-sm font-semibold text-white">Motif du refus (envoyé à l'élève)</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($quickReasons as $quickReason)
                                        <button type="button" @click="reason = @js($quickReason)" class="rounded-full bg-white/5 px-3 py-1.5 text-xs text-slate-200 ring-1 ring-white/15 hover:bg-white/10">{{ $quickReason }}</button>
                                    @endforeach
                                </div>
                                <label for="motif-{{ $purchase->id }}" class="sr-only">Motif du refus</label>
                                <textarea id="motif-{{ $purchase->id }}" name="reason" x-model="reason" rows="3" required class="field-input" placeholder="Expliquez simplement pourquoi le paiement est refusé."></textarea>
                                @if ($rejectErrors->has('reason'))
                                    <p class="text-sm text-rose-300">{{ $rejectErrors->first('reason') }}</p>
                                @endif
                                @if ($user && ! $user->isAdmin())
                                    <label class="flex items-start gap-3 rounded-2xl bg-white/[0.03] p-3 text-sm text-slate-200 ring-1 ring-white/10">
                                        <input type="checkbox" name="block_user" value="1" class="mt-0.5 size-5 shrink-0 accent-rose-500">
                                        <span>Bloquer aussi le compte de {{ $user->first_name }} (connexion impossible)</span>
                                    </label>
                                @endif
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <button type="submit" class="btn w-full bg-rose-500 text-white hover:bg-rose-400">Confirmer le refus</button>
                                    <button type="button" @click="rejecting = false" class="btn-ghost w-full">Annuler</button>
                                </div>
                                <p class="text-xs text-slate-500">L'accès à la formation sera retiré immédiatement et l'élève prévenu par e-mail.</p>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="card p-10 text-center">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-emerald-400/15 text-emerald-300"><flux:icon.check-badge class="size-7" /></span>
                    <p class="mt-4 font-semibold text-white">Aucun paiement à vérifier pour le moment.</p>
                    <p class="mt-1 text-sm text-slate-400">Vous recevez un e-mail à chaque nouveau paiement.</p>
                </div>
            @endforelse
        </div>

        @if ($recent->isNotEmpty())
            <section class="mt-12">
                <h2 class="text-lg font-semibold text-white">Dernières décisions</h2>
                <ul class="mt-4 divide-y divide-white/10 rounded-3xl border border-white/10">
                    @foreach ($recent as $decision)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-white">{{ $decision->user?->name ?? 'Compte supprimé' }} · {{ $decision->formation->title }}</p>
                                <p class="text-xs text-slate-500">{{ $decision->formatted_amount }} · {{ $decision->verified_at?->translatedFormat('j F Y à H\hi') }}@if ($decision->rejection_reason) · {{ $decision->rejection_reason }}@endif · {{ $startedAt[$decision->id] ? 'formation commencée le '.$startedAt[$decision->id]->translatedFormat('j F') : 'aucune leçon consultée' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $decision->status->badgeClasses() }}">{{ $decision->status->label() }}</span>
                                @if ($decision->user && ! $decision->user->isAdmin())
                                    <form method="POST" action="{{ route($decision->user->isBlocked() ? 'admin.users.unblock' : 'admin.users.block', $decision->user) }}">
                                        @csrf
                                        <button type="submit" @class([
                                            'rounded-full px-3 py-1 text-xs font-semibold ring-1',
                                            'text-slate-300 ring-white/15 hover:bg-white/10' => $decision->user->isBlocked(),
                                            'text-rose-200 ring-rose-400/40 hover:bg-rose-500/10' => ! $decision->user->isBlocked(),
                                        ])>{{ $decision->user->isBlocked() ? 'Débloquer le compte' : 'Bloquer le compte' }}</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts::site>
