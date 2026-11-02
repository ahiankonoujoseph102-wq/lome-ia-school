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
                    @if ($enrollment->pack)
                        <p class="mt-1 text-sm text-gold-200" data-test="enrollment-pack">Incluse dans « {{ $enrollment->pack->title }} »</p>
                    @endif
                    @if (($enrollment->purchase ?? $enrollment->packPurchase())?->status === \App\Enums\PurchaseStatus::Provisional)
                        <x-site.alert type="info" class="mt-4">Accès provisoire : votre paiement sera vérifié sous peu. Vous serez prévenu par e-mail.</x-site.alert>
                    @endif
                @endif
            </div>
        </div>

        @if ($formation->isFree())
            {{-- Formation gratuite : option choisie, groupe des inscrits, gestion de l'inscription --}}
            @php($freeRegistration = app(\App\FreeRegistrations\FreeRegistrar::class)->activeFor(auth()->user(), $formation))
            <section class="mt-6 rounded-3xl bg-night-900 p-5 ring-1 ring-gold-500/50 sm:p-6" data-test="student-free-registration">
                <p class="text-sm font-semibold text-gold-200">Formation gratuite</p>
                @if ($freeRegistration?->option)
                    <h2 class="mt-1 text-lg font-bold text-white">{{ $freeRegistration->option->label }}</h2>
                    <p class="mt-1 text-sm text-slate-300">{{ collect([$formation->eventDateLabel(), $freeRegistration->option->schedule])->filter()->implode(' · ') }}</p>
                @elseif ($formation->eventDateLabel())
                    <h2 class="mt-1 text-lg font-bold text-white">{{ $formation->eventDateLabel() }}</h2>
                @endif
                <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('chat.show', $formation) }}" class="btn-gold">Ouvrir le groupe des inscrits</a>
                    <a href="{{ route('formations.show', $formation) }}#inscription" class="btn-ghost">Voir ou annuler mon inscription</a>
                </div>
            </section>
        @endif

        @php($liveOffer = \App\Support\LiveOffer::configured($formation))
        @if ($liveOffer)
            {{-- Formation en direct : où trouver le lien Google Meet, les enregistrements et les documents --}}
            <section class="mt-6 rounded-3xl bg-night-900 p-5 ring-1 ring-gold-500/50 sm:p-6" data-test="student-live-offer">
                <p class="inline-flex items-center gap-2 rounded-full bg-rose-500/15 px-3 py-1 text-xs font-bold text-rose-100 uppercase ring-1 ring-rose-400/40">
                    <span class="size-1.5 rounded-full bg-rose-400"></span> En direct sur Google Meet
                </p>
                <h2 class="mt-3 text-lg font-bold text-white">{{ $liveOffer->period() }}</h2>
                <p class="mt-1 text-sm text-slate-300">{{ $liveOffer->schedule() }} · {{ $liveOffer->neighbours() }}</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-200">
                    <li class="flex gap-2"><flux:icon.video-camera class="mt-0.5 size-4 shrink-0 text-gold-400" /> Le lien Google Meet de chaque séance se trouve dans « Sessions » et dans l'annonce épinglée du groupe d'accompagnement.</li>
                    <li class="flex gap-2"><flux:icon.archive-box class="mt-0.5 size-4 shrink-0 text-gold-400" /> L'enregistrement et le document de chaque séance sont déposés dans le groupe d'accompagnement, disponibles à vie.</li>
                </ul>
                <ol class="mt-5 space-y-2">
                    @foreach ($liveOffer->sessions() as $session)
                        <li class="flex gap-3 rounded-2xl bg-night-800 px-3 py-2.5 ring-1 ring-night-600">
                            <span class="w-6 shrink-0 text-center text-sm font-bold text-electric-300">{{ $session['number'] }}</span>
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block text-slate-400">{{ $session['day'] }} · {{ $liveOffer->hours() }}{{ ['past' => ' · terminée', 'live' => ' · en cours'][$session['state']] ?? '' }}</span>
                                <span class="block text-white">{{ $session['title'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
                <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('student.sessions.index') }}" class="btn-gold" data-test="student-live-offer-sessions">Voir mes séances et le lien Google Meet</a>
                    <a href="{{ route('chat.show', $formation) }}" class="btn-ghost">Ouvrir le groupe d'accompagnement</a>
                </div>
            </section>
        @endif

        @if ($curriculum->isEmpty() && $liveOffer)
            {{-- Pas encore de leçon enregistrée : l'encadré ci-dessus suffit --}}
        @elseif ($curriculum->isEmpty())
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
                    <p class="mt-2 truncate text-xs text-slate-400">{{ $summary->resumeLesson->title }}</p>
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
