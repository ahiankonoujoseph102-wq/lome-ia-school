@php
    use App\Http\Requests\LessonRequest;

    $isNew = ! $lesson->exists;
    $videoUrl = old('video_url', $lesson->video_id ? 'https://youtu.be/'.$lesson->video_id : '');
    $durationValue = old('duration', $lesson->duration_seconds ? sprintf('%d:%02d', intdiv($lesson->duration_seconds, 60), $lesson->duration_seconds % 60) : '');
@endphp

<x-layouts::site :title="$isNew ? 'Nouvelle leçon' : 'Modifier la leçon'" noindex>
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.content.show', $formation) }}#module-{{ $module->id }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> {{ $formation->title }}
        </a>
        <h1 class="mt-4 text-2xl font-bold text-white">{{ $isNew ? 'Nouvelle leçon' : 'Modifier la leçon' }}</h1>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif

        <form
            method="POST"
            action="{{ $isNew ? route('admin.lessons.store', $module) : route('admin.lessons.update', $lesson) }}"
            enctype="multipart/form-data"
            class="card mt-6 space-y-5 p-5 sm:p-6"
        >
            @csrf
            @unless ($isNew)
                @method('PUT')
            @endunless

            <x-site.field name="title" label="Titre de la leçon" :value="old('title', $lesson->title)" required />

            <div class="space-y-1.5">
                <label for="course_module_id" class="field-label">Module</label>
                <select id="course_module_id" name="course_module_id" class="field-input">
                    @foreach ($formation->modules as $option)
                        <option value="{{ $option->id }}" @selected((int) old('course_module_id', $lesson->course_module_id) === $option->id)>{{ $loop->iteration }}. {{ $option->title }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Vidéo avec aperçu immédiat --}}
            <div class="space-y-2" x-data="lessonVideoPreview(@js($videoUrl))">
                <label for="video_url" class="field-label">Lien de la vidéo {{ $provider->name() }}</label>
                <input
                    id="video_url"
                    name="video_url"
                    type="url"
                    inputmode="url"
                    x-model="url"
                    value="{{ $videoUrl }}"
                    placeholder="https://youtu.be/…"
                    @class(['field-input', 'field-input-error' => $errors->has('video_url')])
                >
                @error('video_url')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @else
                    <p class="text-xs text-slate-500">Sur YouTube : « Partager », puis « Copier le lien ». Le lien normal ou court fonctionne.</p>
                @enderror

                <template x-if="videoId">
                    <div class="overflow-hidden rounded-2xl bg-black ring-1 ring-white/10">
                        <div class="aspect-video">
                            <iframe :src="embedUrl" title="Aperçu de la vidéo" class="size-full" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
                        </div>
                        <p class="flex items-center gap-1.5 px-3 py-2 text-xs text-emerald-300"><flux:icon.check-circle class="size-4" /> Lien reconnu. Vérifiez que la vidéo se lance.</p>
                    </div>
                </template>
                <p x-show="url.trim() !== '' && !videoId" x-cloak class="flex items-center gap-1.5 text-sm text-amber-200">
                    <flux:icon.exclamation-triangle class="size-4" /> Lien non reconnu pour l'instant.
                </p>
            </div>

            <x-site.field name="duration" label="Durée (facultatif)" :value="$durationValue" inputmode="numeric" placeholder="Ex. : 12:30" hint="Minutes:secondes, ou seulement les minutes (ex. 12)." />

            <div class="space-y-1.5">
                <label for="description" class="field-label">Description courte (facultatif)</label>
                <textarea id="description" name="description" rows="4" class="field-input" placeholder="Ce que l'élève va voir dans cette leçon.">{{ old('description', $lesson->description) }}</textarea>
                @error('description')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-white/[0.03] p-4 text-sm ring-1 ring-white/10">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $lesson->is_published)) class="mt-0.5 size-5 shrink-0 accent-emerald-500">
                    <span><span class="block font-semibold text-white">Publiée</span><span class="text-xs text-slate-400">Visible par les élèves de la formation.</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-white/[0.03] p-4 text-sm ring-1 ring-white/10">
                    <input type="checkbox" name="is_preview" value="1" @checked(old('is_preview', $lesson->is_preview)) class="mt-0.5 size-5 shrink-0 accent-gold-400">
                    <span><span class="block font-semibold text-white">Aperçu gratuit</span><span class="text-xs text-slate-400">Visible par tous les visiteurs depuis la page de la formation.</span></span>
                </label>
            </div>

            {{-- Ressources --}}
            <div id="ressources" class="scroll-mt-24 space-y-2">
                <p class="field-label">Fichiers à télécharger (facultatif)</p>
                @if ($lesson->exists && $lesson->resources->isNotEmpty())
                    <ul class="space-y-2">
                        @foreach ($lesson->resources as $resource)
                            <li class="flex items-center gap-3 rounded-2xl bg-white/[0.03] px-3 py-2.5 ring-1 ring-white/10">
                                <flux:icon.document class="size-5 shrink-0 text-electric-300" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-white">{{ $resource->name }}</span>
                                    <span class="text-xs text-slate-500">{{ $resource->extension() }} · {{ $resource->formattedSize() }}</span>
                                </span>
                                <button type="submit" form="supprimer-ressource-{{ $resource->id }}" class="grid size-9 shrink-0 place-items-center rounded-full text-slate-400 ring-1 ring-white/15 hover:text-rose-300" aria-label="Supprimer {{ $resource->name }}">
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div x-data="{ names: [] }">
                    <label for="resources" class="flex cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-white/20 px-4 py-4 transition hover:bg-white/5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-300"><flux:icon.paper-clip class="size-5" /></span>
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="block font-semibold text-white" x-text="names.length ? names.length + (names.length > 1 ? ' fichiers choisis' : ' fichier choisi') : 'Ajouter des fichiers'">Ajouter des fichiers</span>
                            <span class="block truncate text-xs text-slate-400" x-text="names.join(', ')"></span>
                        </span>
                    </label>
                    <input id="resources" type="file" name="resources[]" multiple accept=".{{ implode(',.', LessonRequest::RESOURCE_MIMES) }}" class="sr-only" @change="names = Array.from($event.target.files).map((file) => file.name)">
                </div>
                <p class="text-xs text-slate-500">PDF, images, Word, Excel, PowerPoint, ZIP… {{ intdiv(LessonRequest::RESOURCE_MAX_KB, 1024) }} Mo maximum par fichier. Ils restent privés : seuls les élèves de la formation peuvent les télécharger.</p>
                @foreach ($errors->get('resources*') as $messages)
                    @foreach ((array) $messages as $message)
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @endforeach
                @endforeach
            </div>

            <button type="submit" class="btn-gold w-full">{{ $isNew ? 'Ajouter la leçon' : 'Enregistrer' }}</button>
        </form>

        {{-- Formulaires de suppression (hors du formulaire principal) --}}
        @if ($lesson->exists)
            @foreach ($lesson->resources as $resource)
                <form id="supprimer-ressource-{{ $resource->id }}" method="POST" action="{{ route('admin.resources.destroy', $resource) }}" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach

            <div class="mt-6 text-center" x-data="{ confirmDelete: false }">
                <button type="button" x-show="!confirmDelete" @click="confirmDelete = true" class="text-sm text-slate-400 hover:text-rose-300">Supprimer cette leçon</button>
                <form x-show="confirmDelete" x-cloak method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}" class="card space-y-3 p-4 text-left ring-1 ring-rose-400/30">
                    @csrf
                    @method('DELETE')
                    <p class="text-sm text-rose-100">Supprimer définitivement « {{ $lesson->title }} » et ses fichiers ? La progression des élèves sur cette leçon sera aussi effacée.</p>
                    <div class="flex gap-2">
                        <button type="submit" class="rounded-full bg-rose-500 px-4 py-2 text-sm font-semibold text-white">Oui, supprimer</button>
                        <button type="button" @click="confirmDelete = false" class="rounded-full px-4 py-2 text-sm text-slate-300 ring-1 ring-white/15">Annuler</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-layouts::site>
