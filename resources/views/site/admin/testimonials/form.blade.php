@php
    use App\Models\Testimonial;

    $editing = $testimonial->exists;
@endphp

<x-admin.page :title="$editing ? 'Modifier le témoignage' : 'Ajouter un témoignage'" :back="route('admin.testimonials.index')" back-label="Témoignages">
    <form method="POST" action="{{ $editing ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}" enctype="multipart/form-data" class="card space-y-5 p-5 sm:p-6" x-data="{ type: @js(old('type', $testimonial->type)) }">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <fieldset class="space-y-2">
            <legend class="field-label">Type de témoignage</legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach (Testimonial::TYPES as $value => $label)
                    <label class="flex cursor-pointer items-center gap-2 rounded-2xl px-4 py-3 text-sm ring-1 ring-veil/15" :class="type === '{{ $value }}' ? 'bg-gold-400/15 text-gold-100 ring-2 ring-gold-400' : 'text-slate-200'">
                        <input type="radio" name="type" value="{{ $value }}" x-model="type" class="sr-only"> {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-admin.field name="name" label="Nom de l'élève" :value="$testimonial->name" hint="Facultatif pour une capture ou une vidéo." />
        <x-admin.field name="formation" label="Formation suivie (facultatif)" :value="$testimonial->formation" />

        <div x-show="type === 'text'" class="space-y-5">
            <x-admin.field name="text" label="Témoignage" type="textarea" :rows="5" :value="$testimonial->text" hint="Les mots de l'élève, sans les modifier." />
            @if ($testimonial->photoUrl())
                <div class="flex items-center gap-3">
                    <img src="{{ $testimonial->photoUrl() }}" alt="" class="size-14 rounded-full object-cover">
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remove_photo" value="1" class="size-4 accent-gold-400"> Retirer la photo</label>
                </div>
            @endif
            <x-admin.field name="photo" label="Photo de l'élève (facultative)" type="file" accept="image/png,image/jpeg,image/webp" hint="Recadrée en carré automatiquement." />
        </div>

        <div x-show="type === 'screenshot'" x-cloak class="space-y-3">
            @if ($testimonial->imageUrl())
                <img src="{{ $testimonial->imageUrl() }}" alt="Capture actuelle" class="max-h-80 rounded-2xl ring-1 ring-veil/10">
            @endif
            <x-admin.field name="image" label="Capture d'écran WhatsApp" type="file" accept="image/png,image/jpeg,image/webp" hint="Masquez le numéro de téléphone de l'élève avant l'envoi." />
        </div>

        <div x-show="type === 'video'" x-cloak>
            <x-admin.field name="youtube_url" label="Lien de la vidéo YouTube" type="url" :value="$testimonial->youtube_url" placeholder="https://www.youtube.com/watch?v=…" hint="Une vidéo non répertoriée fonctionne aussi." />
            <div class="mt-4">
                <x-admin.field
                    name="video_format"
                    label="Format de la vidéo"
                    type="select"
                    :options="['' => 'Automatique', 'vertical' => 'Vertical (filmée au téléphone, 9:16)', 'horizontal' => 'Horizontal (16:9)']"
                    :value="$testimonial->video_format ?? ''"
                    hint="Automatique reconnaît la plupart des vidéos verticales. Si une vidéo filmée au téléphone s'affiche en largeur sur le site, choisissez « Vertical »."
                />
            </div>
        </div>

        <label class="flex items-center gap-3 text-sm text-slate-200">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="size-5 accent-gold-400" @checked(old('is_active', $testimonial->is_active ?? true))> Afficher sur le site
        </label>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-gold">Enregistrer</button>
        </div>
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('admin.testimonials.destroy', $testimonial) }}" onsubmit="return confirm('Supprimer définitivement ce témoignage ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-semibold text-rose-200 hover:text-rose-100">Supprimer ce témoignage</button>
        </form>
    @endif
</x-admin.page>
