<x-layouts::site title="Mes commissions" variant="student">
    <div class="mx-auto max-w-3xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">Commissions</h1>
        <p class="mt-1 text-slate-400">Chaque achat validé passé par vos liens. Les acheteurs ne sont jamais affichés.</p>

        @include('site.student.affiliate.partials.tabs')

        <section class="card mt-6 px-5 py-2 sm:px-6">
            @if ($commissions->isEmpty())
                <p class="py-4 text-sm text-slate-400">Aucune commission pour l'instant.</p>
            @else
                <ul class="divide-y divide-veil/10">
                    @foreach ($commissions as $commission)
                        @include('site.student.affiliate.partials.commission-row')
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="mt-4">{{ $commissions->links() }}</div>

        <dl class="mt-6 grid gap-2 text-xs text-slate-400">
            <div><dt class="inline font-semibold text-slate-300">En attente</dt> <dd class="inline">: disponible {{ config('affiliate.availability_days') }} jours après la validation du paiement.</dd></div>
            <div><dt class="inline font-semibold text-slate-300">Disponible</dt> <dd class="inline">: comprise dans votre solde, retirable par Mobile Money.</dd></div>
            <div><dt class="inline font-semibold text-slate-300">Payée</dt> <dd class="inline">: versée avec un retrait.</dd></div>
            <div><dt class="inline font-semibold text-slate-300">Annulée</dt> <dd class="inline">: achat refusé ou remboursé avant le versement.</dd></div>
            <div><dt class="inline font-semibold text-slate-300">Déduction</dt> <dd class="inline">: achat remboursé après le versement de sa commission, déduit de vos prochaines commissions.</dd></div>
        </dl>
    </div>
</x-layouts::site>
