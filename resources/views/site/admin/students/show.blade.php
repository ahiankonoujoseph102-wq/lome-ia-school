@php
    use App\Support\Money;
    use App\Support\PhoneNumber;

    $manualErrors = $errors->getBag('manualAccess');
@endphp

<x-admin.page :title="$student->name" :back="route('admin.students.index')" back-label="Élèves" width="max-w-4xl">
    {{-- Coordonnées --}}
    <section class="card space-y-4 p-5 sm:p-6">
        <div class="flex items-start gap-4">
            <x-site.avatar :user="$student" class="size-16 shrink-0 text-lg" />
            <div class="min-w-0 flex-1 space-y-1">
                <p class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-electric-200 ring-1 ring-electric-400/50">{{ $student->role->label() }}</span>
                    @if ($student->isBlocked())
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-rose-200 ring-1 ring-rose-400/50">Bloqué depuis le {{ $student->blocked_at?->translatedFormat('j M Y') }}</span>
                    @endif
                    @if ($student->wants_newsletter)
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-slate-300 ring-1 ring-veil/20">Accepte les annonces</span>
                    @endif
                </p>
                <p class="text-sm text-slate-300">WhatsApp : <span class="font-semibold text-white">{{ PhoneNumber::format($student->phone) }}</span></p>
                <p class="text-sm break-all text-slate-300">E-mail : <span class="font-semibold text-white">{{ $student->email }}</span></p>
                @if ($student->hasConfirmedEmail())
                    <p class="flex items-center gap-1.5 text-sm text-emerald-300" data-test="email-status"><flux:icon.check-badge class="size-4 shrink-0" /> E-mail confirmé le {{ $student->email_verified_at?->translatedFormat('j F Y') }}</p>
                @else
                    <p class="flex items-center gap-1.5 text-sm text-gold-200" data-test="email-status"><flux:icon.exclamation-circle class="size-4 shrink-0" /> E-mail non confirmé : paiements et retraits impossibles</p>
                @endif
                <p class="text-sm text-slate-400">Inscrit le {{ $student->created_at?->translatedFormat('j F Y') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="https://wa.me/{{ ltrim((string) $student->phone, '+') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-[#25D366] ring-1 ring-[#25D366]/50 hover:bg-[#25D366]/10"><x-site.whatsapp-icon class="size-4" /> WhatsApp</a>
            <a href="mailto:{{ $student->email }}" class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-slate-200 ring-1 ring-veil/20 hover:bg-veil/10"><flux:icon.envelope class="size-4" /> E-mail</a>
            @unless ($student->hasConfirmedEmail())
                <form method="POST" action="{{ route('admin.students.confirm-email', $student) }}" onsubmit="return confirm('Confirmer manuellement l\'adresse e-mail de cet élève ? Vérifiez-la d\'abord avec l\'élève (par exemple sur WhatsApp). Cette action est notée dans le journal d\'activité.')">@csrf<button class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-gold-200 ring-1 ring-gold-400/60 hover:bg-gold-400/10" data-test="confirm-email"><flux:icon.check-badge class="size-4" /> Confirmer l'email manuellement</button></form>
            @endunless
            @unless ($student->isAdmin())
                @if ($student->isBlocked())
                    <form method="POST" action="{{ route('admin.users.unblock', $student) }}">@csrf<button class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-emerald-200 ring-1 ring-emerald-400/50 hover:bg-emerald-400/10">Débloquer le compte</button></form>
                @else
                    <form method="POST" action="{{ route('admin.users.block', $student) }}" onsubmit="return confirm('Bloquer ce compte ? L\'élève ne pourra plus se connecter et sera retiré de tous les groupes.')">@csrf<button class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/50 hover:bg-rose-400/10" data-test="block-user">Bloquer le compte</button></form>
                @endif
            @endunless
        </div>
    </section>

    {{-- Accès manuel --}}
    <details class="card group p-5 sm:p-6" @if ($manualErrors->any()) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-white">
            <span class="flex items-center gap-2"><flux:icon.key class="size-5 text-gold-300" /> Donner l'accès à une formation (paiement reçu hors du site)</span>
            <flux:icon.chevron-down class="size-5 text-slate-400 transition group-open:rotate-180" />
        </summary>
        @if ($availableFormations->isEmpty())
            <p class="mt-4 text-sm text-slate-400">{{ $student->name }} a déjà accès à toutes les formations.</p>
        @else
            <form method="POST" action="{{ route('admin.students.access', $student) }}" class="mt-4 space-y-4">
                @csrf
                <p class="text-sm text-slate-400">Crée un achat « validé » marqué « manuel », visible dans les ventes, avec reçu par e-mail et entrée dans le groupe de la formation. Aucune commission d'affiliation.</p>
                <x-admin.field name="formation_id" label="Formation" type="select" bag="manualAccess" :options="['' => 'Choisir…'] + $availableFormations->mapWithKeys(fn ($formation) => [$formation->id => $formation->title.' ('.Money::fcfa($formation->price).')'])->all()" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.field name="amount" label="Montant reçu" type="number" unit="FCFA" bag="manualAccess" hint="0 si l'accès est offert." />
                    <x-admin.field name="method" label="Moyen" type="select" bag="manualAccess" :options="$manualMethods" />
                </div>
                <x-admin.field name="reason" label="Motif (obligatoire)" type="textarea" :rows="2" bag="manualAccess" placeholder="Exemple : payé en espèces au bureau le 3 octobre" />
                <button type="submit" class="btn-gold w-full sm:w-auto">Donner l'accès</button>
            </form>
        @endif
    </details>

    {{-- Formations et progression --}}
    <section class="card p-5 sm:p-6">
        <h2 class="font-semibold text-white">Formations ({{ $student->enrollments->count() }})</h2>
        <ul class="mt-3 space-y-3">
            @forelse ($student->enrollments as $enrollment)
                @php $summary = $progress[$enrollment->formation_id] ?? null; @endphp
                <li>
                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span class="font-semibold text-white">{{ $enrollment->formation->title }}</span>
                        <span class="text-slate-400">{{ $summary?->percent() ?? 0 }} % · {{ $summary?->completed ?? 0 }}/{{ $summary?->total ?? 0 }} leçons · depuis le {{ $enrollment->granted_at->translatedFormat('j M Y') }}</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-veil/10" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $summary?->percent() ?? 0 }}">
                        <div class="h-full rounded-full bg-electric-400" style="width: {{ $summary?->percent() ?? 0 }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">{{ $summary?->hasStarted() ? 'Commencée le '.$summary->startedAt?->translatedFormat('j M Y') : 'Pas encore commencée' }}</p>
                </li>
            @empty
                <li class="text-sm text-slate-400">Aucune formation.</li>
            @endforelse
        </ul>
    </section>

    {{-- Achats --}}
    <section class="card p-5 sm:p-6">
        <h2 class="font-semibold text-white">Achats ({{ $student->purchases->count() }})</h2>
        <ul class="mt-3 divide-y divide-veil/10">
            @forelse ($student->purchases as $purchase)
                <li class="py-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold text-white">{{ $purchase->item_label }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                    </div>
                    <p class="mt-1 text-slate-400">
                        {{ $purchase->formatted_amount }}
                        @if ($purchase->discount_amount)
                            (remise de {{ Money::fcfa($purchase->discount_amount) }}{{ $purchase->promoCode ? ', code '.$purchase->promoCode->code : '' }})
                        @endif
                        · {{ $purchase->payment_method_name }}{{ $purchase->isManual() ? ' (accès manuel)' : '' }} · {{ $purchase->created_at?->translatedFormat('j M Y') }}
                    </p>
                    @if ($purchase->manual_reason)
                        <p class="mt-1 text-slate-400">Motif : {{ $purchase->manual_reason }}</p>
                    @endif
                </li>
            @empty
                <li class="py-3 text-sm text-slate-400">Aucun achat.</li>
            @endforelse
        </ul>
    </section>

    <div class="grid gap-6 sm:grid-cols-2">
        {{-- Groupes --}}
        <section class="card p-5 sm:p-6">
            <h2 class="font-semibold text-white">Groupes de discussion</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($student->chatMemberships as $membership)
                    <li class="flex flex-wrap justify-between gap-2">
                        <span class="text-slate-200">{{ $membership->group?->formation?->title ?? 'Groupe' }}</span>
                        <span class="text-slate-400">{{ $membership->removed_at ? 'Retiré'.($membership->removal_reason ? ' ('.mb_strtolower($membership->removal_reason->label()).')' : '') : ($membership->isMuted() ? 'En sourdine' : 'Membre') }}</span>
                    </li>
                @empty
                    <li class="text-slate-400">Aucun groupe.</li>
                @endforelse
            </ul>
        </section>

        {{-- Sessions --}}
        <section class="card p-5 sm:p-6">
            <h2 class="font-semibold text-white">Sessions</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($student->liveSessionRegistrations as $registration)
                    <li class="flex flex-wrap justify-between gap-2">
                        <span class="text-slate-200">{{ $registration->session?->displayTitle() }} · {{ $registration->session?->starts_at->translatedFormat('j M Y') }}</span>
                        <span class="text-slate-400">{{ $registration->status->label() }}</span>
                    </li>
                @empty
                    <li class="text-slate-400">Aucune inscription.</li>
                @endforelse
            </ul>
        </section>

        {{-- Consultations --}}
        <section class="card p-5 sm:p-6">
            <h2 class="font-semibold text-white">Consultations gratuites</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($consultations as $consultation)
                    <li class="flex flex-wrap justify-between gap-2">
                        <span class="text-slate-200">{{ $consultation->starts_at->translatedFormat('j M Y à H\hi') }} <span class="text-slate-400">· {{ $consultation->requesterSummary() }}</span></span>
                        <span class="text-slate-400">{{ $consultation->isCancelled() ? 'Annulée' : ($consultation->attendance?->label() ?? ($consultation->isPast() ? 'Passée' : 'À venir')) }}</span>
                    </li>
                @empty
                    <li class="text-slate-400">Aucune consultation.</li>
                @endforelse
            </ul>
        </section>

        {{-- Affiliation --}}
        <section class="card p-5 sm:p-6">
            <h2 class="font-semibold text-white">Affiliation</h2>
            @if ($student->affiliate && $affiliateSummary)
                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div><dt class="text-slate-400">Code</dt><dd class="font-mono text-white">{{ $student->affiliate->code }}</dd></div>
                    <div><dt class="text-slate-400">Ventes</dt><dd class="text-white">{{ $affiliateSummary['sales'] }}</dd></div>
                    <div><dt class="text-slate-400">Solde</dt><dd class="text-white">{{ Money::fcfa($affiliateSummary['balance']) }}</dd></div>
                    <div><dt class="text-slate-400">Déjà versé</dt><dd class="text-white">{{ Money::fcfa($affiliateSummary['paid']) }}</dd></div>
                </dl>
                <a href="{{ route('admin.affiliates.index') }}" class="mt-3 inline-block text-sm font-semibold text-electric-200">Voir les affiliés</a>
            @else
                <p class="mt-3 text-sm text-slate-400">Pas d'espace affilié.</p>
            @endif
            @if ($student->referrer)
                <p class="mt-2 text-sm text-slate-400">Venu par le lien de {{ $student->referrer->user->name }}.</p>
            @endif
        </section>
    </div>

    {{-- Historique --}}
    <section class="card p-5 sm:p-6">
        <h2 class="font-semibold text-white">Historique</h2>
        <ol class="mt-3 space-y-3">
            @foreach ($history as $event)
                <li class="flex gap-3 text-sm">
                    <flux:icon :name="$event['icon']" class="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-slate-200">{{ $event['text'] }}</span>
                        <span class="text-xs text-slate-400">{{ $event['at']->translatedFormat('j M Y à H\hi') }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>
</x-admin.page>
