@php
    use App\Http\Requests\LessonRequest;

    $isNew = ! $lesson->exists;
    $videoLinks = [];
    foreach ($providers as $key => $videoProvider) {
        $savedId = $lesson->exists ? $lesson->videoIdFor($key) : null;
        $videoLinks[$key] = old('videos.'.$key, $savedId ? $videoProvider->shareUrl($savedId) : '');
    }
    $primaryVideo = old('primary_video', $lesson->video_provider ?? array_key_first($providers));
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

            {{-- Vidéo : un lien par source (YouTube, Google Drive…), avec aperçu immédiat --}}
            <fieldset class="space-y-4" x-data="{ primary: @js($primaryVideo) }">
                <legend class="field-label">Vidéo de la leçon</legend>
                <p class="-mt-2 text-xs text-slate-400">Renseignez une source, ou les deux : la seconde sert de « lecteur de secours » si la première ne fonctionne pas.</p>

                @foreach ($providers as $key => $videoProvider)
                    <div class="space-y-2 rounded-2xl bg-veil/[0.03] p-4 ring-1 ring-veil/10" x-data="lessonVideoPreview(@js($key), @js($videoLinks[$key]))">
                        <div class="flex items-center justify-between gap-3">
                            <label for="video-{{ $key }}" class="text-sm font-semibold text-white">Lien {{ $videoProvider->name() }}</label>
                            @if (count($providers) > 1)
                                <label class="inline-flex items-center gap-2 text-xs text-slate-300">
                                    <input type="radio" name="primary_video" value="{{ $key }}" x-model="primary" class="size-4 accent-gold-400">
                                    Lecteur principal
                                </label>
                            @endif
                        </div>
                        @if ($videoProvider->adminReminder())
                            <p class="flex items-start gap-2 rounded-xl bg-gold-400/10 px-3 py-2 text-xs font-medium text-gold-100 ring-1 ring-gold-400/30">
                                <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" /> {{ $videoProvider->adminReminder() }}
                            </p>
                        @endif
                        <input
                            id="video-{{ $key }}"
                            name="videos[{{ $key }}]"
                            type="text"
                            inputmode="url"
                            autocapitalize="none"
                            autocorrect="off"
                            spellcheck="false"
                            x-model="url"
                            value="{{ $videoLinks[$key] }}"
                            placeholder="{{ $key === 'drive' ? 'https://drive.google.com/file/d/…' : 'https://youtu.be/…' }}"
                            @class(['field-input', 'field-input-error' => $errors->has('videos.'.$key)])
                        >
                        @error('videos.'.$key)
                            <p class="text-sm text-rose-300">{{ $message }}</p>
                        @else
                            <p class="text-xs text-slate-400">{{ $videoProvider->linkHelp() }}</p>
                        @enderror

                        <template x-if="videoId">
                            <div class="overflow-hidden rounded-2xl bg-black ring-1 ring-veil/10">
                                <div class="aspect-video">
                                    <iframe :src="embedUrl" title="Aperçu de la vidéo {{ $videoProvider->name() }}" class="size-full" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
                                </div>
                                <p class="flex items-center gap-1.5 px-3 py-2 text-xs text-emerald-300"><flux:icon.check-circle class="size-4" /> Lien reconnu. Vérifiez que la vidéo se lance.</p>
                            </div>
                        </template>
                        <p x-show="url.trim() !== '' && !videoId" x-cloak class="flex items-center gap-1.5 text-sm text-amber-200">
                            <flux:icon.exclamation-triangle class="size-4 shrink-0" /> <span x-text="problem"></span>
                        </p>
                    </div>
                @endforeach
            </fieldset>

            <x-site.field name="duration" label="Durée (facultatif)" :value="$durationValue" inputmode="numeric" placeholder="Ex. : 12:30" hint="Minutes:secondes, ou seulement les minutes (ex. 12)." />

            <div class="space-y-1.5">
                <label for="description" class="field-label">Description courte (facultatif)</label>
                <textarea id="description" name="description" rows="4" class="field-input" placeholder="Ce que l'élève va voir dans cette leçon.">{{ old('description', $lesson->description) }}</textarea>
                @error('description')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-veil/[0.03] p-4 text-sm ring-1 ring-veil/10">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $lesson->is_published)) class="mt-0.5 size-5 shrink-0 accent-emerald-500">
                    <span><span class="block font-semibold text-white">Publiée</span><span class="text-xs text-slate-400">Visible par les élèves de la formation.</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-veil/[0.03] p-4 text-sm ring-1 ring-veil/10">
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
                            <li class="flex items-center gap-3 rounded-2xl bg-veil/[0.03] px-3 py-2.5 ring-1 ring-veil/10">
                                @if ($resource->isLink())
                                    <flux:icon.link class="size-5 shrink-0 text-gold-300" />
                                @else
                                    <flux:icon.document class="size-5 shrink-0 text-electric-300" />
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-white">{{ $resource->name }}</span>
                                    <span class="text-xs text-slate-400">{{ $resource->details() }}</span>
                                </span>
                                <button type="submit" form="supprimer-ressource-{{ $resource->id }}" class="grid size-9 shrink-0 place-items-center rounded-full text-slate-400 ring-1 ring-veil/15 hover:text-rose-300" aria-label="Supprimer {{ $resource->name }}">
                                    <flux:icon.trash class="size-4" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div x-data="{ names: [] }">
                    <label for="resources" class="flex cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-veil/20 px-4 py-4 transition hover:bg-veil/5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-electric-500/15 text-electric-300"><flux:icon.paper-clip class="size-5" /></span>
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="block font-semibold text-white" x-text="names.length ? names.length + (names.length > 1 ? ' fichiers choisis' : ' fichier choisi') : 'Ajouter des fichiers'">Ajouter des fichiers</span>
                            <span class="block truncate text-xs text-slate-400" x-text="names.join(', ')"></span>
                        </span>
                    </label>
                    <input id="resources" type="file" name="resources[]" multiple accept=".{{ implode(',.', LessonRequest::RESOURCE_MIMES) }}" class="sr-only" @change="names = Array.from($event.target.files).map((file) => file.name)">
                </div>
                <p class="text-xs text-slate-400">PDF, images, Word, Excel, PowerPoint, ZIP… {{ intdiv(LessonRequest::RESOURCE_MAX_KB, 1024) }} Mo maximum par fichier. Ils restent privés : seuls les élèves de la formation peuvent les télécharger.</p>
                <div class="space-y-2 rounded-2xl bg-veil/[0.03] p-4 ring-1 ring-veil/10">
                    <p class="text-sm font-semibold text-white">Ou ajouter un lien Google Drive</p>
                    <p class="flex items-start gap-2 rounded-xl bg-gold-400/10 px-3 py-2 text-xs font-medium text-gold-100 ring-1 ring-gold-400/30">
                        <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0" /> Le fichier Drive doit être partagé en « Tous les utilisateurs disposant du lien ».
                    </p>
                    <label for="link_name" class="sr-only">Nom du lien</label>
                    <input id="link_name" name="link_name" value="{{ old('link_name') }}" placeholder="Nom affiché (ex. Support de cours)" @class(['field-input', 'field-input-error' => $errors->has('link_name')])>
                    @error('link_name')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                    <label for="link_url" class="sr-only">Lien Google Drive</label>
                    <input id="link_url" name="link_url" type="text" inputmode="url" autocapitalize="none" autocorrect="off" spellcheck="false" value="{{ old('link_url') }}" placeholder="https://drive.google.com/…" @class(['field-input', 'field-input-error' => $errors->has('link_url')])>
                    @error('link_url')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @else
                        <p class="text-xs text-slate-400">Le lien n'est jamais affiché dans la page : seuls les élèves ayant accès peuvent l'ouvrir.</p>
                    @enderror
                </div>
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
                        <button type="button" @click="confirmDelete = false" class="rounded-full px-4 py-2 text-sm text-slate-300 ring-1 ring-veil/15">Annuler</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-layouts::site>
