<x-layouts::site title="Mes sessions" variant="student">
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">Sessions en direct ou à Lomé</h1>
        <p class="mt-1 text-slate-400">Sessions ponctuelles de vos formations. Heures de Lomé (GMT).</p>

        <div class="mt-6 space-y-4">
            @forelse ($sessions as $session)
                @include('site.student.sessions.card')
            @empty
                <div class="card p-6 text-center">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.calendar-days class="size-7" /></span>
                    <p class="mt-4 font-semibold text-white">Aucune session prévue pour l'instant</p>
                    <p class="mt-1 text-sm text-slate-400">Les sessions sont annoncées ici et dans le groupe de chaque formation.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts::site>
