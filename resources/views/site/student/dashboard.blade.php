<x-layouts::site title="Mon espace" variant="student">
    <div class="mx-auto max-w-6xl space-y-10 px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        {{-- Bienvenue --}}
        <section class="relative overflow-hidden rounded-[2rem] border border-white/10 bg-linear-to-br from-electric-700/60 via-night-800 to-night-900 p-6 sm:p-8">
            <div class="glow pointer-events-none absolute inset-0 opacity-70"></div>
            <div class="relative flex items-center gap-4">
                <x-site.avatar :user="$user" class="size-14 text-lg sm:size-16" />
                <div class="min-w-0">
                    <p class="text-sm text-electric-200">Bienvenue dans votre espace</p>
                    <h1 class="truncate text-2xl font-bold text-white sm:text-3xl">Bonjour {{ $user->first_name }} !</h1>
                </div>
            </div>
            <p class="relative mt-4 max-w-xl text-slate-300">
                @if ($enrollments->isEmpty())
                    Tout est prêt pour commencer. Choisissez votre première formation et avancez à votre rythme.
                @else
                    Content de vous revoir. Reprenez votre formation là où vous en étiez.
                @endif
            </p>
        </section>

        {{-- Mes formations --}}
        <section>
            <div class="flex items-end justify-between gap-4">
                <h2 class="text-xl font-bold text-white sm:text-2xl">Mes formations</h2>
                @if ($enrollments->isNotEmpty())
                    <a href="{{ route('student.formations.index') }}" class="text-sm font-semibold text-electric-300 hover:text-white">Tout voir</a>
                @endif
            </div>
            <div class="mt-5">
                @if ($enrollments->isEmpty())
                    @include('site.student.partials.empty-formations')
                @else
                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($enrollments as $enrollment)
                            @include('site.student.partials.enrollment-card')
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        {{-- Fonctionnalités des prochaines étapes --}}
        <section>
            <h2 class="text-xl font-bold text-white sm:text-2xl">Bientôt dans votre espace</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <x-site.coming-soon-card icon="chart-bar" title="Ma progression" text="Suivez les leçons terminées et votre avancement dans chaque formation." />
                <x-site.coming-soon-card icon="calendar-days" title="Prochaines sessions en direct" text="Retrouvez vos sessions Google Meet et vos séances à Lomé, avec rappels." />
                <x-site.coming-soon-card icon="chat-bubble-left-right" title="Mes groupes de discussion" text="Échangez avec le formateur et les autres élèves de chaque formation." />
                <x-site.coming-soon-card icon="banknotes" title="Mes gains d'affiliation" text="Votre lien personnel, vos ventes et vos gains de 10 %, retirables par Mobile Money." />
            </div>
        </section>

        {{-- Suggestions --}}
        @if ($suggestions->isNotEmpty())
            <section>
                <h2 class="text-xl font-bold text-white sm:text-2xl">{{ $enrollments->isEmpty() ? 'Pour bien démarrer' : 'Continuez à progresser' }}</h2>
                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($suggestions as $formation)
                        <x-site.formation-card :formation="$formation" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts::site>
