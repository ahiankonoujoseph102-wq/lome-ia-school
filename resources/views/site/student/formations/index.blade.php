<x-layouts::site title="Mes formations" variant="student">
    <div class="mx-auto max-w-6xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">Mes formations</h1>
        <p class="mt-1 text-slate-400">
            {{ $enrollments->count() }} {{ $enrollments->count() > 1 ? 'formations' : 'formation' }} dans votre espace
        </p>

        <div class="mt-6">
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
    </div>
</x-layouts::site>
