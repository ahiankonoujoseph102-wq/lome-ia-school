<x-layouts::site :title="$formation->title" variant="student">
    <div class="mx-auto max-w-4xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <a href="{{ route('student.formations.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Mes formations
        </a>

        <div class="card mt-4 overflow-hidden">
            <x-site.formation-cover :formation="$formation" class="aspect-[16/6]" eager />
            <div class="p-5 sm:p-7">
                <p class="text-xs font-semibold text-electric-300">{{ $formation->category->label() }}</p>
                <h1 class="mt-1 text-2xl font-bold text-white sm:text-3xl">{{ $formation->title }}</h1>
                @if ($enrollment)
                    <p class="mt-2 text-sm text-slate-400">Accès depuis le {{ $enrollment->granted_at->translatedFormat('j F Y') }}</p>
                    @if ($enrollment->purchase?->status === \App\Enums\PurchaseStatus::Provisional)
                        <x-site.alert type="info" class="mt-4">Accès provisoire : votre paiement sera vérifié sous peu. Vous serez prévenu par e-mail.</x-site.alert>
                    @endif
                @endif
            </div>
        </div>

        @if ($curriculum->isEmpty())
            <div class="card mt-6 p-6 text-center">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.play-circle class="size-7" /></span>
                <p class="mt-4 font-semibold text-white">Les leçons arrivent bientôt</p>
                <p class="mt-1 text-sm text-slate-400">Elles apparaîtront ici dès leur mise en ligne. Vous serez prévenu dans le groupe de la formation.</p>
            </div>
        @else
            {{-- Progression et reprise --}}
            <section class="card mt-6 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold text-white">Votre progression</p>
                    <p class="text-2xl font-bold text-gold-300 tabular-nums">{{ $summary->percent() }} %</p>
                </div>
                <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-veil/10" role="progressbar" aria-valuenow="{{ $summary->percent() }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progression">
                    <div class="h-full rounded-full bg-linear-to-r from-electric-500 to-gold-400" style="width: {{ $summary->percent() }}%"></div>
                </div>
                <p class="mt-2 text-sm text-slate-400">{{ $summary->completed }} {{ $summary->completed > 1 ? 'leçons terminées' : 'leçon terminée' }} sur {{ $summary->total }}</p>
                @if ($summary->resumeLesson)
                    <a href="{{ route('lessons.show', [$formation, $summary->resumeLesson]) }}" class="btn-gold mt-5 w-full sm:w-auto" data-test="resume-button">
                        <flux:icon.play class="size-5" variant="solid" />
                        {{ $summary->hasStarted() ? 'Reprendre où j\'en étais' : 'Commencer la formation' }}
                    </a>
                    <p class="mt-2 truncate text-xs text-slate-500">{{ $summary->resumeLesson->title }}</p>
                @endif
            </section>

            <section class="card mt-6 overflow-hidden">
                <h2 class="px-5 pt-5 text-lg font-bold text-white">Programme</h2>
                <div class="px-2 pb-3">
                    @include('site.lessons.partials.curriculum')
                </div>
            </section>
        @endif

        {{-- Prochaines sessions de la formation --}}
        @foreach ($sessions as $session)
            <a href="{{ route('student.sessions.show', $session) }}" class="card card-hover mt-6 block p-5" data-test="formation-session">
                <x-site.session-summary :session="$session" :show-seats="false" />
                <span class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-gold-300">{{ $session->isOnline() ? 'Voir la session et le lien' : 'Voir la session et s\'inscrire' }} <flux:icon.arrow-right class="size-4" /></span>
            </a>
        @endforeach

        {{-- Groupe de discussion de la formation --}}
        <a href="{{ route('chat.show', $formation) }}" class="card card-hover mt-6 flex items-center gap-4 p-5" data-test="group-link">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-emerald-400/15 text-emerald-300">
                <flux:icon.chat-bubble-left-right class="size-6" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-white">Groupe de discussion</span>
                <span class="block text-sm text-slate-400">Posez vos questions, échangez avec l'équipe et les autres élèves.</span>
            </span>
            @if ($groupUnread > 0)
                <span class="min-w-6 rounded-full bg-gold-400 px-2 py-0.5 text-center text-xs font-bold text-night-950">{{ $groupUnread }}</span>
            @endif
            <flux:icon.arrow-right class="size-5 text-slate-500" />
        </a>
    </div>
</x-layouts::site>
