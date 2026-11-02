@php
    use App\Support\Money;

    $cards = [
        ['label' => 'En attente', 'value' => Money::fcfa($summary['pending']), 'hint' => 'Disponibles '.config('affiliate.availability_days').' jours après la validation'],
        ['label' => 'Disponibles', 'value' => Money::fcfa($summary['available']), 'hint' => 'Prêtes à être retirées'],
        ['label' => 'Payées', 'value' => Money::fcfa($summary['paid']), 'hint' => 'Total de vos retraits payés'],
    ];
    $counters = [
        ['label' => 'Clics', 'value' => $summary['clicks']],
        ['label' => 'Inscriptions', 'value' => $summary['signups']],
        ['label' => 'Ventes', 'value' => $summary['sales']],
    ];
@endphp

<x-layouts::site title="Affiliation" variant="student">
    <div class="mx-auto max-w-3xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">Affiliation</h1>
        <p class="mt-1 text-slate-400">Votre code : <span class="font-mono font-semibold text-gold-200">{{ $affiliate->code }}</span></p>

        @include('site.student.affiliate.partials.tabs')

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif

        {{-- Solde --}}
        <section class="card relative mt-6 overflow-hidden p-5 sm:p-6" data-test="affiliate-balance">
            <div class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-gold-400/15 blur-3xl"></div>
            <p class="text-sm text-slate-400">Solde</p>
            <p @class(['mt-1 text-3xl font-bold', 'text-gold-300' => $summary['balance'] >= 0, 'text-rose-300' => $summary['balance'] < 0])>{{ Money::fcfa($summary['balance']) }}</p>
            @if ($summary['deductions'] < 0)
                <p class="mt-1 text-xs text-slate-400">Dont {{ Money::fcfa(abs($summary['deductions'])) }} déduits (achat remboursé après le versement de sa commission).</p>
            @endif
            @if ($summary['requested'] > 0)
                <p class="mt-1 text-sm text-gold-100">Retrait en cours : {{ Money::fcfa($summary['requested']) }}</p>
            @endif
            <a href="{{ route('student.affiliate.withdrawals') }}" class="btn-gold mt-4 w-full sm:w-auto">Retirer par Mobile Money</a>
        </section>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach ($cards as $card)
                <div class="card p-4">
                    <p class="text-xs text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-1 text-lg font-bold text-white">{{ $card['value'] }}</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-3 grid grid-cols-3 gap-3">
            @foreach ($counters as $counter)
                <div class="card p-4 text-center">
                    <p class="text-xl font-bold text-white tabular-nums">{{ number_format($counter['value'], 0, ',', ' ') }}</p>
                    <p class="text-xs text-slate-400">{{ $counter['label'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Lien général --}}
        <section class="card mt-6 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Votre lien vers le site</h2>
            <p class="mt-1 text-sm text-slate-400">À partager sur WhatsApp, Facebook ou TikTok. Chaque achat validé dans les {{ config('affiliate.cookie_days') }} jours qui suivent un clic vous est attribué (dernier lien cliqué).</p>
            <div class="mt-4">
                <x-site.share-link :link="$affiliate->link()" message="Découvrez les formations en intelligence artificielle de Lomé IA School :" />
            </div>
        </section>

        {{-- Liens par formation --}}
        <section class="mt-6">
            <h2 class="text-lg font-semibold text-white">Un lien pour chaque formation</h2>
            <p class="mt-1 text-sm text-slate-400">La personne arrive directement sur la page de la formation.</p>
            <div class="mt-4 space-y-3">
                @foreach ($formations as $formation)
                    <details class="card group p-4" data-test="formation-link">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-medium text-white">
                            <span class="min-w-0">{{ $formation->title }}</span>
                            <flux:icon.chevron-down class="size-4 shrink-0 text-slate-500 transition group-open:rotate-180" />
                        </summary>
                        <div class="mt-3">
                            <x-site.share-link :link="$affiliate->link($formation)" :message="'Découvrez la formation « '.$formation->title.' » de Lomé IA School :'" />
                        </div>
                    </details>
                @endforeach
            </div>
        </section>

        {{-- Dernières commissions --}}
        <section class="card mt-6 p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-white">Dernières commissions</h2>
                <a href="{{ route('student.affiliate.commissions') }}" class="text-sm font-semibold text-electric-300 hover:text-white">Tout voir</a>
            </div>
            @if ($commissions->isEmpty())
                <p class="mt-3 text-sm text-slate-400">Aucune commission pour l'instant. Elles apparaissent ici dès que notre équipe valide un paiement passé par l'un de vos liens.</p>
            @else
                <ul class="mt-2 divide-y divide-veil/10">
                    @foreach ($commissions as $commission)
                        @include('site.student.affiliate.partials.commission-row')
                    @endforeach
                </ul>
            @endif
        </section>

        <p class="mt-6 text-center text-xs text-slate-400">Règles du programme : article « Programme d'affiliation » des <a href="{{ route('legal.terms') }}#affiliation" class="underline underline-offset-2 hover:text-white">conditions générales de vente</a>.</p>
    </div>
</x-layouts::site>
