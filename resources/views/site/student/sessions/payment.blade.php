<x-layouts::site title="Supplément de la session" variant="student">
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <a href="{{ route('student.sessions.show', $session) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Retour à la session
        </a>

        <h1 class="mt-4 text-2xl font-bold text-white">Supplément de la session</h1>

        @if (session('status'))
            <x-site.alert type="info" class="mt-4">{{ session('status') }}</x-site.alert>
        @endif

        <div class="card mt-4 p-5">
            <x-site.session-summary :session="$session" show-formation :show-seats="false" />
            <div class="mt-4 flex items-center justify-between border-t border-veil/10 pt-4">
                <span class="text-sm text-slate-400">À payer</span>
                <span class="text-2xl font-bold text-gold-300">{{ $session->formattedSupplement() }}</span>
            </div>
            <p class="mt-2 text-xs text-slate-400">Place réservée pour vous jusqu'au {{ $registration->reserved_until?->translatedFormat('j F à H\hi') }}.</p>
        </div>

        <div class="mt-6">
            @include('site.checkout.partials.payment', ['liveSession' => $session])
        </div>
    </div>
</x-layouts::site>
