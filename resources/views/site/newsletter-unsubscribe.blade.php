{{-- Page du lien « Se désabonner de ces e-mails » des envois groupés (sans connexion, lien signé). --}}
<x-layouts::site :title="$subscribed ? 'Abonnement rétabli' : 'Désabonnement'" noindex>
    <div class="mx-auto max-w-xl px-4 pt-10 pb-16 sm:px-6">
        <div class="card space-y-4 p-6 text-center" data-test="{{ $subscribed ? 'newsletter-resubscribed' : 'newsletter-unsubscribed' }}">
            <span class="mx-auto grid size-12 place-items-center rounded-full bg-night-900 text-gold-300 ring-1 ring-night-600"><flux:icon.envelope class="size-6" /></span>

            @if ($subscribed)
                <h1 class="text-2xl font-bold text-white">Vous êtes de nouveau abonné(e)</h1>
                <p class="text-slate-300">Vous recevrez de nouveau les e-mails d'information de Lomé IA School.</p>
            @else
                <h1 class="text-2xl font-bold text-white">Vous êtes désabonné(e)</h1>
                <p class="text-slate-300">Vous ne recevrez plus les e-mails d'information envoyés à tous les inscrits.</p>
                <p class="text-sm text-slate-400">Vous continuerez à recevoir les e-mails liés à votre compte et à vos achats : codes de confirmation, mot de passe oublié, paiements, inscriptions, sessions et annonces de vos groupes.</p>
                <form method="POST" action="{{ $resubscribeUrl }}">
                    @csrf
                    <button type="submit" class="btn-ghost w-full sm:w-auto" data-test="newsletter-resubscribe">Je me suis trompé(e) : me réabonner</button>
                </form>
                <p class="text-xs text-slate-400">Vous pouvez aussi vous réabonner plus tard dans Mon espace &gt; Notifications.</p>
            @endif

            <a href="{{ route('home') }}" class="inline-block text-sm text-slate-300 underline underline-offset-4">Retour au site</a>
        </div>
    </div>
</x-layouts::site>
