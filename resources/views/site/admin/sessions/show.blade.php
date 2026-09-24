@php
    use App\Enums\RegistrationStatus;

    $confirmed = $registrations->get(RegistrationStatus::Confirmed->value, collect());
    $reserved = $registrations->get(RegistrationStatus::Reserved->value, collect());
    $waitlist = $registrations->get(RegistrationStatus::Waitlisted->value, collect())->sortBy('waitlisted_at')->values();
@endphp

<x-layouts::site :title="$session->displayTitle()" noindex>
    <div class="mx-auto max-w-3xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.sessions.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Sessions
        </a>

        @if (session('status'))
            <x-site.alert class="mt-4">{{ session('status') }}</x-site.alert>
        @endif
        @if ($errors->any())
            <x-site.alert type="error" class="mt-4">{{ $errors->first() }}</x-site.alert>
        @endif

        <article class="card mt-4 p-5 sm:p-6">
            <x-site.session-summary :session="$session" show-formation heading-tag="h1" />
            @if ($session->isOnline() && $session->meet_url)
                <p class="mt-3 flex items-center gap-2 text-sm break-all text-slate-300"><flux:icon.link class="size-4 shrink-0 text-slate-500" /> {{ $session->meet_url }}</p>
            @endif
            @if ($session->description)
                <p class="mt-4 text-sm whitespace-pre-line text-slate-300">{{ $session->description }}</p>
            @endif
            @if ($session->isCancelled())
                <x-site.alert type="error" class="mt-4">Annulée le {{ $session->cancelled_at?->translatedFormat('j F Y à H\hi') }}@if ($session->cancellation_reason) : {{ $session->cancellation_reason }}@endif</x-site.alert>
            @endif
            <p class="mt-4 text-xs text-slate-500">Créée par {{ $session->creator?->name ?? 'l\'équipe' }} · annonce {{ $session->announcement_message_id ? ($session->announcement_unpinned_at ? 'désépinglée' : 'épinglée') : 'non publiée' }} dans le groupe</p>

            <div class="mt-5 flex flex-wrap gap-2">
                @unless ($session->isCancelled())
                    <a href="{{ route('admin.sessions.edit', $session) }}" class="btn-ghost !px-4 !py-2.5 !text-sm"><flux:icon.pencil-square class="size-4" /> Modifier</a>
                @endunless
                <a href="{{ route('chat.show', $session->formation) }}" class="btn-ghost !px-4 !py-2.5 !text-sm"><flux:icon.chat-bubble-left-right class="size-4" /> Groupe</a>
                @if ($session->isOnsite())
                    <a href="{{ route('admin.sessions.attendance', $session) }}" class="btn-ghost !px-4 !py-2.5 !text-sm"><flux:icon.clipboard-document-check class="size-4" /> Feuille de présence</a>
                @endif
                @can('access-admin')
                    @if ($session->hasStarted() && ! $session->isCancelled())
                        <a href="{{ route('admin.sessions.recording', $session) }}" class="btn-violet !px-4 !py-2.5 !text-sm"><flux:icon.film class="size-4" /> Ajouter l'enregistrement comme leçon</a>
                    @endif
                @endcan
            </div>
        </article>

        @if ($session->isOnsite())
            <section class="card mt-6 p-5 sm:p-6" data-test="registrations">
                <h2 class="text-lg font-bold text-white">Inscrits <span class="text-slate-400">({{ $confirmed->count() + $reserved->count() }} / {{ $session->capacity }})</span></h2>
                <ul class="mt-3 divide-y divide-white/[0.06]">
                    @forelse ($confirmed->concat($reserved) as $registration)
                        <li class="flex items-center gap-3 py-2.5">
                            <x-site.avatar :user="$registration->user" class="size-9 text-xs" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-white">{{ $registration->user->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $registration->user->formatted_phone }}</span>
                            </span>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $registration->status->badgeClasses() }}">
                                {{ $registration->paymentUnderReview() ? 'Paiement à vérifier' : ($registration->awaitsPayment() ? 'Supplément à payer' : $registration->status->label()) }}
                            </span>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-slate-400">Personne pour l'instant.</li>
                    @endforelse
                </ul>

                @if ($waitlist->isNotEmpty())
                    <h3 class="mt-6 font-semibold text-white">Liste d'attente ({{ $waitlist->count() }})</h3>
                    <ol class="mt-2 divide-y divide-white/[0.06]">
                        @foreach ($waitlist as $index => $registration)
                            <li class="flex items-center gap-3 py-2.5">
                                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-electric-500/15 text-xs font-bold text-electric-200">{{ $index + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate text-white">{{ $registration->user->name }}</span>
                                <span class="shrink-0 text-xs text-slate-500">depuis le {{ $registration->waitlisted_at?->translatedFormat('j F à H\hi') }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        @endif

        @if ($refunds->isNotEmpty())
            <section class="card mt-6 p-5 sm:p-6">
                <h2 class="text-lg font-bold text-white">Suppléments à rembourser</h2>
                <p class="mt-1 text-sm text-slate-400">La session est annulée : remboursez chaque élève par Mobile Money, puis marquez le supplément comme remboursé.</p>
                <ul class="mt-3 divide-y divide-white/[0.06]">
                    @foreach ($refunds as $purchase)
                        <li class="flex flex-wrap items-center gap-3 py-3">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">{{ $purchase->user?->name }}</span>
                                <span class="block text-xs text-slate-400">{{ $purchase->formatted_amount }} · payé depuis le {{ $purchase->payer_phone }} ({{ $purchase->payment_method_name }})</span>
                            </span>
                            @can('access-admin')
                                <form method="POST" action="{{ route('admin.sessions.refund', [$session, $purchase]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-full px-3 py-2 text-sm font-semibold text-emerald-200 ring-1 ring-emerald-400/40 hover:bg-emerald-500/10">Marquer comme remboursé</button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @unless ($session->isCancelled() || $session->isFinished())
            <details class="card group mt-6 p-5">
                <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold text-rose-200">
                    Annuler la session
                    <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" />
                </summary>
                <form method="POST" action="{{ route('admin.sessions.cancel', $session) }}" class="mt-4 space-y-4">
                    @csrf
                    <p class="text-sm text-slate-400">Les élèves concernés sont prévenus par e-mail et notification. L'annonce est désépinglée et un message d'annulation est publié dans le groupe.@if ($session->requiresPayment()) Les suppléments payés devront être remboursés.@endif</p>
                    <div class="space-y-1.5">
                        <label for="reason" class="field-label">Motif (facultatif, envoyé aux élèves)</label>
                        <textarea id="reason" name="reason" rows="2" maxlength="500" class="field-input"></textarea>
                    </div>
                    <button type="submit" class="btn w-full bg-rose-600 text-white hover:bg-rose-500 sm:w-auto" data-test="cancel-session">Annuler la session</button>
                </form>
            </details>
        @endunless
    </div>
</x-layouts::site>
