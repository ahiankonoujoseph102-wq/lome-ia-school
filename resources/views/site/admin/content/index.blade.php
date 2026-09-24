<x-layouts::site title="Contenu des formations" noindex>
    <div class="mx-auto max-w-3xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Administration
        </a>
        <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Contenu des formations</h1>
        <p class="mt-1 text-slate-400">Choisissez une formation pour ajouter ses modules et ses leçons.</p>

        <ul class="mt-6 space-y-3">
            @foreach ($formations as $formation)
                <li>
                    <a href="{{ route('admin.content.show', $formation) }}" class="card card-hover flex items-center gap-4 p-4">
                        <x-site.formation-cover :formation="$formation" class="size-14 shrink-0 rounded-xl" compact />
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-white">{{ $formation->title }}</span>
                            <span class="mt-0.5 block text-sm text-slate-400">
                                @if ($formation->lessons_count === 0)
                                    Aucune leçon pour l'instant
                                @else
                                    {{ $formation->modules_count }} {{ $formation->modules_count > 1 ? 'modules' : 'module' }} ·
                                    {{ $formation->published_lessons_count }}/{{ $formation->lessons_count }} {{ $formation->lessons_count > 1 ? 'leçons publiées' : 'leçon publiée' }}
                                @endif
                            </span>
                        </span>
                        <flux:icon.chevron-right class="size-5 shrink-0 text-slate-500" />
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts::site>
