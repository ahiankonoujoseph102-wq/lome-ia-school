<x-admin.page title="Envoi d'e-mails" subtitle="Un e-mail à toutes les personnes qui ont un compte (adresse confirmée, non désabonnées), envoyé progressivement selon le quota de Brevo." width="max-w-4xl">
    <x-slot:actions>
        <a href="{{ route('admin.newsletters.create') }}" class="btn-gold" data-test="newsletter-new"><flux:icon.plus class="size-5" /> Nouvel e-mail</a>
    </x-slot:actions>

    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <section class="card space-y-1 p-4 text-sm text-slate-300" data-test="newsletter-quota">
        <p>Aujourd'hui : <strong class="text-white tabular-nums">{{ $sentToday }}</strong> e-mails groupés envoyés sur <strong class="text-white tabular-nums">{{ $dailyLimit }}</strong> au maximum ; encore <strong class="text-white tabular-nums">{{ $availableToday }}</strong> possibles.</p>
        <p class="text-xs text-slate-400">Les codes de confirmation, mots de passe oubliés, inscriptions, paiements et annonces des groupes passent toujours en priorité. Plafond réglable dans <a href="{{ route('admin.settings.edit', 'emails') }}" class="underline underline-offset-4">Réglages du site &gt; E-mails envoyés</a>.</p>
    </section>

    <ul class="space-y-3">
        @forelse ($newsletters as $newsletter)
            @php($stats = $newsletter->isDraft() ? null : $newsletter->stats())
            <li class="card p-4 sm:p-5" data-test="newsletter-row">
                <a href="{{ route('admin.newsletters.show', $newsletter) }}" class="block space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-white">{{ $newsletter->subject }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-slate-200 ring-1 ring-night-600">{{ $newsletter->status->label() }}</span>
                    </div>
                    <p class="text-xs text-slate-400">{{ ($newsletter->started_at ?? $newsletter->created_at)->timezone('Africa/Lome')->translatedFormat('j F Y à H \h i') }}</p>
                    @if ($stats)
                        <dl class="grid grid-cols-3 gap-2 text-center text-xs sm:grid-cols-5">
                            @foreach ([['Destinataires', $newsletter->recipients_count], ['Envoyés', $stats['sent']], ['En attente', $stats['pending']], ['En échec', $stats['failed']], ['Désabonnements', $stats['unsubscribed']]] as [$label, $value])
                                <div class="rounded-xl bg-night-900 p-2 ring-1 ring-night-600"><dt class="text-slate-400">{{ $label }}</dt><dd class="font-bold text-white tabular-nums">{{ $value }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                </a>
            </li>
        @empty
            <li class="card p-5 text-sm text-slate-300">Aucun e-mail pour le moment.</li>
        @endforelse
    </ul>

    {{ $newsletters->links() }}
</x-admin.page>
