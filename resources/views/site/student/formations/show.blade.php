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
                @endif
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <x-site.coming-soon-card icon="play-circle" title="Lecteur de cours" text="Les vidéos et leçons de cette formation apparaîtront ici, avec votre progression." />
            <x-site.coming-soon-card icon="chat-bubble-left-right" title="Groupe de discussion" text="Le groupe privé de cette formation sera accessible ici." />
        </div>

        @if ($formation->publicCurriculum())
            <section class="mt-10">
                <h2 class="text-xl font-bold text-white">Programme</h2>
                <ol class="mt-4 space-y-3">
                    @foreach ($formation->publicCurriculum() as $module)
                        <li class="card p-4 sm:p-5">
                            <p class="text-xs font-semibold text-gold-300">Module {{ $loop->iteration }}</p>
                            <p class="mt-1 font-semibold text-white">{{ $module['title'] }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-300">
                                @foreach ($module['lessons'] as $lesson)
                                    <li class="flex items-start gap-2"><flux:icon.play-circle class="mt-0.5 size-4 shrink-0 text-electric-400" /> {{ $lesson }}</li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</x-layouts::site>
