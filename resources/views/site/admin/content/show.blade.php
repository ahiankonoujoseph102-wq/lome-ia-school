<x-layouts::site :title="'Contenu : '.$formation->title" noindex>
    <div class="mx-auto max-w-3xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.content.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Toutes les formations
        </a>
        <h1 class="mt-4 text-2xl font-bold text-balance text-white sm:text-3xl">{{ $formation->title }}</h1>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
            <a href="{{ route('formations.show', $formation) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-electric-300 hover:text-white">
                <flux:icon.eye class="size-4" /> Voir la page publique
            </a>
            <a href="{{ route('student.formations.show', $formation) }}" class="inline-flex items-center gap-1 text-electric-300 hover:text-white">
                <flux:icon.play-circle class="size-4" /> Voir comme un élève
            </a>
        </div>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif

        <div class="mt-6 space-y-5">
            @forelse ($formation->modules as $module)
                @php $moduleErrors = $errors->getBag('module'.$module->id); @endphp
                <section id="module-{{ $module->id }}" class="card scroll-mt-24 overflow-hidden">
                    <div class="flex items-start gap-3 border-b border-veil/10 p-4" x-data="{ renaming: @js($moduleErrors->any()) }">
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-sm font-bold text-electric-300">{{ $loop->iteration }}</span>
                        <div class="min-w-0 flex-1">
                            <h2 x-show="!renaming" class="font-semibold text-white">{{ $module->title }}</h2>
                            <form x-show="renaming" x-cloak method="POST" action="{{ route('admin.modules.update', $module) }}" class="flex gap-2">
                                @csrf
                                @method('PUT')
                                <label for="module-titre-{{ $module->id }}" class="sr-only">Titre du module</label>
                                <input id="module-titre-{{ $module->id }}" name="title" value="{{ $moduleErrors->any() ? old('title') : $module->title }}" required class="field-input min-w-0 flex-1 !py-2">
                                <button type="submit" class="btn-gold !px-4 !py-2 !text-sm">OK</button>
                            </form>
                            @if ($moduleErrors->has('title'))
                                <p class="mt-1 text-sm text-rose-300">{{ $moduleErrors->first('title') }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-slate-500">{{ $module->lessons->count() }} {{ $module->lessons->count() > 1 ? 'leçons' : 'leçon' }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @include('site.admin.content.partials.move', ['route' => 'admin.modules.move', 'model' => $module, 'first' => $loop->first, 'last' => $loop->last, 'label' => 'le module'])
                            <button type="button" @click="renaming = !renaming" class="grid size-10 place-items-center rounded-full text-slate-300 ring-1 ring-veil/15 hover:bg-veil/10" aria-label="Renommer le module">
                                <flux:icon.pencil class="size-4" />
                            </button>
                        </div>
                    </div>

                    <ol class="divide-y divide-veil/10">
                        @foreach ($module->lessons as $lesson)
                            <li id="lecon-{{ $lesson->id }}" class="scroll-mt-24 p-4">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 w-6 shrink-0 text-center text-sm font-semibold text-slate-500">{{ $loop->iteration }}</span>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('admin.lessons.edit', $lesson) }}" class="font-medium text-white hover:text-electric-200">{{ $lesson->title }}</a>
                                        <div class="mt-1.5 flex flex-wrap gap-1.5 text-xs">
                                            @if ($lesson->is_published)
                                                <span class="rounded-full bg-emerald-400/15 px-2 py-0.5 font-semibold text-emerald-200 ring-1 ring-emerald-400/30">Publiée</span>
                                            @else
                                                <span class="rounded-full bg-veil/10 px-2 py-0.5 font-semibold text-slate-300 ring-1 ring-veil/15">Brouillon</span>
                                            @endif
                                            @if ($lesson->is_preview)
                                                <span class="rounded-full bg-gold-400/15 px-2 py-0.5 font-semibold text-gold-200 ring-1 ring-gold-400/30">Aperçu gratuit</span>
                                            @endif
                                            @if ($lesson->video_id)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-veil/5 px-2 py-0.5 text-slate-300 ring-1 ring-veil/10"><flux:icon.play class="size-3" /> {{ implode(' + secours ', $lesson->videoSourceNames()) }}</span>
                                            @else
                                                <span class="rounded-full bg-rose-400/10 px-2 py-0.5 text-rose-200 ring-1 ring-rose-400/30">Sans vidéo</span>
                                            @endif
                                            @if ($lesson->formatted_duration)
                                                <span class="rounded-full bg-veil/5 px-2 py-0.5 text-slate-300 ring-1 ring-veil/10">{{ $lesson->formatted_duration }}</span>
                                            @endif
                                            @if ($lesson->resources->isNotEmpty())
                                                <span class="rounded-full bg-veil/5 px-2 py-0.5 text-slate-300 ring-1 ring-veil/10">{{ $lesson->resources->count() }} {{ $lesson->resources->count() > 1 ? 'fichiers' : 'fichier' }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2 pl-9">
                                    @include('site.admin.content.partials.move', ['route' => 'admin.lessons.move', 'model' => $lesson, 'first' => $loop->first, 'last' => $loop->last, 'label' => 'la leçon'])
                                    <a href="{{ route('admin.lessons.edit', $lesson) }}" class="inline-flex h-10 items-center gap-1.5 rounded-full px-4 text-sm font-semibold text-white ring-1 ring-veil/15 hover:bg-veil/10">
                                        <flux:icon.pencil-square class="size-4" /> Modifier
                                    </a>
                                    <form method="POST" action="{{ route('admin.lessons.publish', $lesson) }}">
                                        @csrf
                                        <button type="submit" @class([
                                            'inline-flex h-10 items-center gap-1.5 rounded-full px-4 text-sm font-semibold ring-1',
                                            'text-slate-300 ring-veil/15 hover:bg-veil/10' => $lesson->is_published,
                                            'bg-emerald-500 text-night-950 ring-emerald-400 hover:bg-emerald-400' => ! $lesson->is_published,
                                        ])>{{ $lesson->is_published ? 'Dépublier' : 'Publier' }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-veil/10 bg-veil/[0.02] p-4" x-data="{ confirmDelete: false }">
                        <a href="{{ route('admin.lessons.create', $module) }}" class="btn-gold !px-5 !py-2.5 !text-sm">
                            <flux:icon.plus class="size-4" /> Ajouter une leçon
                        </a>
                        <button type="button" x-show="!confirmDelete" @click="confirmDelete = true" class="text-sm text-slate-400 hover:text-rose-300">Supprimer le module</button>
                        <form x-show="confirmDelete" x-cloak method="POST" action="{{ route('admin.modules.destroy', $module) }}" class="flex w-full flex-wrap items-center gap-2 rounded-2xl bg-rose-500/10 p-3 ring-1 ring-rose-400/30">
                            @csrf
                            @method('DELETE')
                            <p class="w-full text-sm text-rose-100">Supprimer « {{ $module->title }} » et ses {{ $module->lessons->count() }} leçon(s), avec leurs fichiers ? Cette action est définitive.</p>
                            <button type="submit" class="rounded-full bg-rose-500 px-4 py-2 text-sm font-semibold text-white">Oui, supprimer</button>
                            <button type="button" @click="confirmDelete = false" class="rounded-full px-4 py-2 text-sm text-slate-300 ring-1 ring-veil/15">Annuler</button>
                        </form>
                    </div>
                </section>
            @empty
                <div class="card p-8 text-center">
                    <p class="font-semibold text-white">Aucun module pour l'instant.</p>
                    <p class="mt-1 text-sm text-slate-400">Commencez par créer le premier module ci-dessous (par exemple « Introduction »).</p>
                </div>
            @endforelse
        </div>

        {{-- Nouveau module --}}
        @php $newModuleErrors = $errors->getBag('newModule'); @endphp
        <form method="POST" action="{{ route('admin.modules.store', $formation) }}" class="card mt-6 space-y-3 p-4">
            @csrf
            <label for="nouveau-module" class="field-label">Nouveau module</label>
            <div class="flex gap-2">
                <input id="nouveau-module" name="title" value="{{ $newModuleErrors->any() ? old('title') : '' }}" required placeholder="Titre du module" class="field-input min-w-0 flex-1">
                <button type="submit" class="btn-gold shrink-0 !px-5"><flux:icon.plus class="size-5" /><span class="sr-only sm:not-sr-only">Ajouter</span></button>
            </div>
            @if ($newModuleErrors->has('title'))
                <p class="text-sm text-rose-300">{{ $newModuleErrors->first('title') }}</p>
            @endif
        </form>
    </div>
</x-layouts::site>
