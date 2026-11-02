@php use App\Support\Money; @endphp

<x-layouts::site title="Affiliés" noindex>
    <div class="mx-auto max-w-5xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Administration
        </a>

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">Affiliés</h1>
                <p class="mt-1 text-slate-400">{{ $affiliates->total() }} affilié{{ $affiliates->total() > 1 ? 's' : '' }}. Les alertes sont des indices, pas des preuves : rien n'est bloqué automatiquement.</p>
            </div>
            @include('site.partials.admin-withdrawals-link', ['class' => 'btn-ghost !px-4 !py-2 !text-sm'])
        </div>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif

        <div class="mt-6 space-y-4">
            @forelse ($affiliates as $affiliate)
                @php $summary = $summaries[$affiliate->id]; @endphp
                <article class="card p-5" data-test="affiliate">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-white">{{ $affiliate->user->name }} <span class="font-mono text-xs text-slate-400">· {{ $affiliate->code }}</span></p>
                            <p class="text-sm text-slate-400">{{ $affiliate->user->email }} · {{ $affiliate->user->formatted_phone }}</p>
                            <p class="text-xs text-slate-400">Affilié depuis le {{ $affiliate->created_at?->translatedFormat('j F Y') }} · Retraits : {{ $affiliate->payoutSummary() }}</p>
                        </div>
                        @if ($affiliate->isSuspended())
                            <span class="rounded-full bg-rose-500/15 px-3 py-1 text-xs font-semibold text-rose-200 ring-1 ring-rose-400/40">Suspendu</span>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        @foreach (['Clics' => $summary['clicks'], 'Inscriptions' => $summary['signups'], 'Ventes' => $summary['sales']] as $label => $value)
                            <div class="rounded-2xl bg-veil/[0.03] px-2 py-2.5 ring-1 ring-veil/10">
                                <p class="text-sm font-bold text-white tabular-nums">{{ $value }}</p>
                                <p class="text-[11px] text-slate-400">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                        @foreach (['En attente' => $summary['pending'], 'Solde' => $summary['balance'], 'Retrait en cours' => $summary['requested'], 'Payé' => $summary['paid']] as $label => $value)
                            <div class="rounded-2xl bg-veil/[0.03] px-2 py-2.5 ring-1 ring-veil/10">
                                <p @class(['text-sm font-bold tabular-nums', 'text-rose-300' => $value < 0, 'text-white' => $value >= 0])>{{ Money::fcfa($value) }}</p>
                                <p class="text-[11px] text-slate-400">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($alerts[$affiliate->id] !== [])
                        <div class="mt-4">@include('site.partials.alert-list', ['alerts' => $alerts[$affiliate->id]])</div>
                    @endif

                    <div class="mt-4 border-t border-veil/10 pt-4">
                        @if ($affiliate->isSuspended())
                            <form method="POST" action="{{ route('admin.affiliates.unsuspend', $affiliate) }}">
                                @csrf
                                <button type="submit" class="btn-ghost !px-4 !py-2 !text-sm">Réactiver l'affilié</button>
                            </form>
                        @else
                            <details class="group">
                                <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 text-sm font-semibold text-rose-200">Suspendre l'affilié <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" /></summary>
                                <form method="POST" action="{{ route('admin.affiliates.suspend', $affiliate) }}" class="mt-3 space-y-3">
                                    @csrf
                                    <label for="suspension-{{ $affiliate->id }}" class="field-label">Motif (affiché à l'affilié, facultatif)</label>
                                    <input id="suspension-{{ $affiliate->id }}" name="reason" type="text" maxlength="500" class="field-input">
                                    <p class="text-xs text-slate-400">Ses liens ne sont plus actifs, aucune nouvelle commission n'est créée et les retraits sont impossibles. Les commissions déjà acquises sont conservées.</p>
                                    <button type="submit" class="btn w-full bg-rose-500 text-white hover:bg-rose-400 sm:w-auto">Confirmer la suspension</button>
                                </form>
                            </details>
                        @endif
                    </div>
                </article>
            @empty
                <div class="card p-10 text-center text-sm text-slate-400">Aucun affilié pour l'instant.</div>
            @endforelse
        </div>

        <div class="mt-6">{{ $affiliates->links() }}</div>
    </div>
</x-layouts::site>
