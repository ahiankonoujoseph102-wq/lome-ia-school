@php $formation = $enrollment->formation; @endphp

<article class="card card-hover group relative flex flex-col overflow-hidden sm:flex-row">
    <x-site.formation-cover :formation="$formation" class="aspect-[16/8] shrink-0 sm:aspect-auto sm:w-48" />
    <div class="flex flex-1 flex-col gap-3 p-5">
        <div>
            <p class="text-xs font-semibold text-electric-300">{{ $formation->category->label() }}</p>
            <h3 class="mt-1 text-lg leading-snug font-semibold text-white">
                <a href="{{ route('student.formations.show', $formation) }}" class="after:absolute after:inset-0 focus:outline-none">{{ $formation->title }}</a>
            </h3>
            <p class="mt-1 text-sm text-slate-400">Accès depuis le {{ $enrollment->granted_at->translatedFormat('j F Y') }}</p>
            @if ($enrollment->purchase?->status === \App\Enums\PurchaseStatus::Provisional)
                <p class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-sky-400/15 px-2.5 py-1 text-xs font-semibold text-sky-200 ring-1 ring-sky-400/40">
                    <flux:icon.clock class="size-3.5" /> Accès provisoire · paiement en cours de vérification
                </p>
            @endif
        </div>
        <div class="mt-auto">
            @php $cardProgress = ($progress ?? [])[$formation->id] ?? null; @endphp
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>Progression</span>
                @if ($cardProgress && $cardProgress->total > 0)
                    <span class="font-semibold text-white tabular-nums">{{ $cardProgress->percent() }} %</span>
                @else
                    <span class="text-slate-500">Leçons bientôt disponibles</span>
                @endif
            </div>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10" aria-hidden="true">
                <div class="h-full rounded-full bg-linear-to-r from-electric-500 to-gold-400" style="width: {{ $cardProgress?->percent() ?? 0 }}%"></div>
            </div>
        </div>
        <span class="inline-flex items-center gap-1 text-sm font-semibold text-gold-300 transition group-hover:gap-2">
            Ouvrir la formation <flux:icon.arrow-right class="size-4" />
        </span>
    </div>
</article>
