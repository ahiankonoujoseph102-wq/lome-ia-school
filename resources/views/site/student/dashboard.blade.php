<x-layouts::site title="Mon espace" variant="student">
    <div class="mx-auto max-w-6xl space-y-10 px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        {{-- Bienvenue --}}
        <section class="relative overflow-hidden rounded-[2rem] border border-veil/10 bg-linear-to-br from-electric-700/60 via-night-800 to-night-900 p-6 sm:p-8">
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

        {{-- Sessions ponctuelles des formations de l'élève --}}
        <section data-test="dashboard-sessions">
            <div class="flex items-end justify-between gap-3">
                <h2 class="text-xl font-bold text-white sm:text-2xl">Sessions en direct ou à Lomé</h2>
                @if ($sessions->isNotEmpty())
                    <a href="{{ route('student.sessions.index') }}" class="shrink-0 text-sm font-semibold text-electric-300 hover:text-white">Tout voir</a>
                @endif
            </div>
            <div class="mt-5 space-y-4">
                @forelse ($sessions->take(3) as $session)
                    @include('site.student.sessions.card', ['registrations' => $sessionRegistrations])
                @empty
                    <div class="card flex items-start gap-4 p-5">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.calendar-days class="size-5" /></span>
                        <p class="text-sm text-slate-400">Aucune session prévue pour l'instant. Quand une session en direct ou à Lomé est annoncée pour l'une de vos formations, vous la retrouvez ici et dans le groupe de la formation.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Consultation gratuite réservée (rattachée au compte par le numéro WhatsApp ou l'e-mail) --}}
        @if ($consultation)
            <a href="{{ route('student.consultation', $consultation) }}" class="card card-hover flex items-center gap-4 p-5" data-test="consultation-card">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.video-camera class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-white">Ma consultation gratuite</span>
                    <span class="block text-sm text-slate-400">{{ ucfirst(\App\Consultations\ConsultationTime::short($consultation)) }} (heure de Lomé) · lien de l'appel vidéo</span>
                </span>
                <flux:icon.arrow-right class="size-5 text-slate-500" />
            </a>
        @endif

        {{-- Groupes et affiliation --}}
        <section>
            <div class="grid gap-4 sm:grid-cols-2">
                <a href="{{ route('chat.index') }}" class="card card-hover flex items-center gap-4 p-5">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-emerald-400/15 text-emerald-300"><flux:icon.chat-bubble-left-right class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-white">Mes groupes de discussion</span>
                        <span class="block text-sm text-slate-400">Échangez avec le formateur et les autres élèves.</span>
                    </span>
                    <flux:icon.arrow-right class="size-5 text-slate-500" />
                </a>
                <a href="{{ route('student.affiliate.index') }}" class="card card-hover flex items-center gap-4 p-5" data-test="affiliate-card">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-gold-400/15 text-gold-300"><flux:icon.banknotes class="size-5" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-white">Affiliation</span>
                        @if ($affiliateBalance !== null)
                            <span class="block text-sm text-slate-400">Solde : <span class="font-semibold text-gold-300">{{ \App\Support\Money::fcfa($affiliateBalance) }}</span> · vos liens et vos commissions</span>
                        @else
                            <span class="block text-sm text-slate-400">Recommandez nos formations et recevez {{ config('affiliate.rate') }} % de chaque achat validé passé par votre lien.</span>
                        @endif
                    </span>
                    <flux:icon.arrow-right class="size-5 text-slate-500" />
                </a>
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
