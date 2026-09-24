{{-- Carte d'une session dans l'espace élève (liste et accueil). --}}
@php
    $registration = $registrations[$session->id] ?? null;
@endphp

<article class="card p-5">
    <x-site.session-summary :session="$session" show-formation :show-seats="$session->isOnsite() && ! $registration?->isActive()" />

    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-white/10 pt-4">
        @if ($session->isOnline())
            @if ($session->canJoinNow())
                <a href="{{ route('student.sessions.join', $session) }}" target="_blank" rel="noopener" class="btn-gold !px-4 !py-2.5 !text-sm" data-test="join-button"><flux:icon.video-camera class="size-4" /> Rejoindre la session</a>
            @else
                <span class="btn cursor-not-allowed border border-white/10 bg-white/[0.04] !px-4 !py-2.5 !text-sm text-slate-500" aria-disabled="true" data-test="join-button-disabled">
                    <flux:icon.video-camera class="size-4" /> Rejoindre · actif {{ config('live_sessions.join_window_minutes') }} min avant
                </span>
            @endif
        @elseif ($registration?->isActive())
            <span class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold ring-1 {{ $registration->status->badgeClasses() }}">
                {{ $registration->awaitsPayment() ? 'Place réservée · supplément à payer' : ($registration->paymentUnderReview() ? 'Place réservée · paiement en vérification' : ($registration->waitlistPosition() ? 'Liste d\'attente · '.$registration->waitlistPosition().'e' : $registration->status->label())) }}
            </span>
        @endif
        <a href="{{ route('student.sessions.show', $session) }}" class="btn-ghost !px-4 !py-2.5 !text-sm">{{ $session->isOnsite() && ! $registration?->isActive() ? 'S\'inscrire' : 'Voir la session' }}</a>
    </div>
</article>
