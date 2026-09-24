@php
    use App\Enums\RegistrationStatus;

    $status = $registration?->status;
    $active = $registration?->isActive() ?? false;
@endphp

<x-layouts::site :title="$session->displayTitle()" variant="student">
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <a href="{{ route('student.sessions.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Mes sessions
        </a>

        @if (session('status'))
            <x-site.alert class="mt-4">{{ session('status') }}</x-site.alert>
        @endif
        @error('session')
            <x-site.alert type="error" class="mt-4">{{ $message }}</x-site.alert>
        @enderror

        <article class="card mt-4 p-5 sm:p-6">
            <x-site.session-summary :session="$session" show-formation heading-tag="h1" :show-seats="$session->isOnsite() && ! $active" />

            @if ($session->description)
                <p class="mt-4 text-sm whitespace-pre-line text-slate-300">{{ $session->description }}</p>
            @endif

            @if ($session->isCancelled())
                <x-site.alert type="error" class="mt-5">Cette session a été annulée.@if ($session->cancellation_reason) Motif : {{ $session->cancellation_reason }}@endif</x-site.alert>
            @elseif ($session->isOnline())
                {{-- En direct : pas d'inscription, lien réservé aux élèves de la formation --}}
                <div class="mt-5 rounded-2xl bg-electric-500/10 p-4 ring-1 ring-electric-400/30">
                    <p class="text-sm font-semibold text-white">Lien Google Meet</p>
                    <p class="mt-1 text-sm break-all text-electric-200" data-test="meet-link">{{ $session->meet_url }}</p>
                    <p class="mt-2 text-xs text-slate-400">Pas d'inscription : tous les élèves de la formation peuvent rejoindre la session. Ne partagez pas ce lien.</p>
                </div>
                @if ($session->canJoinNow())
                    <a href="{{ route('student.sessions.join', $session) }}" target="_blank" rel="noopener" class="btn-gold mt-5 w-full" data-test="join-button"><flux:icon.video-camera class="size-5" /> Rejoindre la session</a>
                @elseif (! $session->isFinished())
                    <span class="btn mt-5 w-full cursor-not-allowed border border-white/10 bg-white/[0.04] text-slate-500" aria-disabled="true" data-test="join-button-disabled">
                        <flux:icon.video-camera class="size-5" /> Rejoindre la session
                    </span>
                    <p class="mt-2 text-center text-xs text-slate-500">Le bouton s'active {{ config('live_sessions.join_window_minutes') }} minutes avant le début, à {{ \App\LiveSessions\SessionTime::hour($session->joinOpensAt()) }} (heure de Lomé).</p>
                @endif
            @else
                {{-- Présentiel : inscription obligatoire --}}
                <div class="mt-5 space-y-3" data-test="registration">
                    @if ($status === RegistrationStatus::Confirmed)
                        <x-site.alert>Vous êtes inscrit(e). Présentez-vous à l'adresse indiquée quelques minutes avant le début.</x-site.alert>
                    @elseif ($registration?->awaitsPayment())
                        <x-site.alert type="info">Votre place est réservée jusqu'au {{ $registration->reserved_until?->translatedFormat('j F à H\hi') }}. Payez le supplément de {{ $session->formattedSupplement() }} pour la confirmer : sans paiement, elle sera proposée à la personne suivante.</x-site.alert>
                        <a href="{{ route('student.sessions.payment', $session) }}" class="btn-gold w-full">Payer le supplément ({{ $session->formattedSupplement() }})</a>
                    @elseif ($registration?->paymentUnderReview())
                        <x-site.alert type="info">Votre paiement est en cours de vérification : votre place reste réservée. Vous serez prévenu(e) par e-mail.</x-site.alert>
                        <a href="{{ route('student.purchases.show', $registration->purchase_id) }}" class="btn-ghost w-full">Suivre mon paiement</a>
                    @elseif ($status === RegistrationStatus::Waitlisted)
                        <x-site.alert type="info">La session est complète. Vous êtes {{ $registration->waitlistPosition() === 1 ? '1re' : $registration->waitlistPosition().'e' }} sur la liste d'attente : si une place se libère, vous serez inscrit(e) automatiquement et prévenu(e).</x-site.alert>
                    @elseif ($session->acceptsRegistrations())
                        <form method="POST" action="{{ route('student.sessions.register', $session) }}">
                            @csrf
                            <button type="submit" class="btn-gold w-full" data-test="register-button">
                                @if ($session->isFull())
                                    Rejoindre la liste d'attente
                                @elseif ($session->requiresPayment())
                                    Réserver ma place ({{ $session->formattedSupplement() }})
                                @else
                                    M'inscrire à la session
                                @endif
                            </button>
                        </form>
                        <p class="text-center text-xs text-slate-500">
                            @if ($session->requiresPayment())
                                Supplément payé par Mobile Money : votre place est réservée {{ config('live_sessions.payment_hold_hours') }} heures le temps d'envoyer votre preuve de paiement.
                            @else
                                Inclus dans votre formation.
                            @endif
                            Annulation possible jusqu'à {{ config('live_sessions.cancel_deadline_hours') }} heures avant la session.
                        </p>
                    @else
                        <p class="text-sm text-slate-400">Les inscriptions sont fermées.</p>
                    @endif

                    @if ($active && ! $registration->paymentUnderReview() && ! $session->hasStarted())
                        @if ($status === RegistrationStatus::Waitlisted || now()->lessThanOrEqualTo($session->cancellationDeadline()))
                            <form method="POST" action="{{ route('student.sessions.cancel', $session) }}" onsubmit="return confirm('Annuler votre inscription ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full rounded-full py-3 text-sm font-semibold text-slate-400 hover:text-rose-200" data-test="cancel-registration">
                                    {{ $status === RegistrationStatus::Waitlisted ? 'Quitter la liste d\'attente' : 'Annuler mon inscription' }}
                                </button>
                            </form>
                            @if ($status !== RegistrationStatus::Waitlisted)
                                <p class="text-center text-xs text-slate-500">Annulation possible jusqu'au {{ $session->cancellationDeadline()->translatedFormat('j F à H\hi') }}.@if ($registration->purchase_id) Le supplément payé n'est pas remboursé en cas d'annulation de votre part.@endif</p>
                            @endif
                        @else
                            <p class="text-center text-xs text-slate-500">Le délai d'annulation est dépassé ({{ config('live_sessions.cancel_deadline_hours') }} heures avant la session).</p>
                        @endif
                    @endif
                </div>
            @endif

            @unless ($session->isCancelled() || $session->isFinished())
                <a href="{{ route('student.sessions.calendar', $session) }}" class="btn-ghost mt-4 w-full" data-test="calendar-button"><flux:icon.calendar-days class="size-5" /> Ajouter à mon agenda</a>
            @endunless
        </article>

        <a href="{{ route('chat.show', $session->formation) }}" class="card card-hover mt-4 flex items-center gap-3 p-4 text-sm">
            <flux:icon.chat-bubble-left-right class="size-5 text-emerald-300" />
            <span class="flex-1 text-slate-200">Questions sur la session ? Écrivez dans le groupe de la formation.</span>
            <flux:icon.arrow-right class="size-4 text-slate-500" />
        </a>
    </div>
</x-layouts::site>
