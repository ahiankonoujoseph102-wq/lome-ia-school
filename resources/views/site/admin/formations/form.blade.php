@php
    use App\Enums\FormationCategory;

    $editing = $formation->exists;
@endphp

<x-admin.page :title="$editing ? $formation->title : 'Nouvelle formation'" :back="route('admin.formations.index')" back-label="Formations et prix">
    @if ($editing)
        <x-slot:actions>
            <a href="{{ route('formations.show', $formation) }}" target="_blank" rel="noopener" class="btn-ghost"><flux:icon.eye class="size-5" /> Voir la page</a>
        </x-slot:actions>
    @endif

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.formations.update', $formation) : route('admin.formations.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">L'essentiel</h2>
            <x-admin.field name="title" label="Titre" :value="$formation->title" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="price" label="Prix" type="number" unit="FCFA" :value="$formation->price" :hint="$editing ? 'Les achats déjà faits gardent leur montant.' : 'En chiffres, sans espace (ex. 25000).'" />
                <x-admin.field name="category" label="Catégorie" type="select" :options="collect(FormationCategory::cases())->mapWithKeys(fn ($category) => [$category->value => $category->label()])->all()" :value="$formation->category?->value" />
            </div>
            <x-admin.field name="subtitle" label="Sous-titre" :value="$formation->subtitle" />
            <x-admin.field name="description" label="Description" type="textarea" :rows="7" :value="$formation->description" hint="Ce que l'élève apprend à faire, sans promettre de résultat (revenus, clients…)." />
            @if ($editing)
                <p class="text-xs text-slate-500">Adresse de la page : {{ route('formations.show', $formation) }} (elle ne change pas, pour ne casser aucun lien partagé).</p>
            @endif
        </section>

        <section class="card space-y-5 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Contenu de la page</h2>
            <x-admin.list-editor name="outcomes" label="Ce que vous allez apprendre" :items="$formation->outcomes ?? []" add-label="Ajouter un point" />
            <x-admin.list-editor name="audience" label="Pour qui est cette formation ?" :items="$formation->audience ?? []" add-label="Ajouter un profil" />
            <x-admin.list-editor name="bonuses" label="Bonus" :items="$formation->exists ? $formation->bonuses : []" add-label="Ajouter un bonus" hint="Vide : la section « Bonus » est masquée. N'ajoutez que des bonus réellement fournis aux élèves." />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Image</h2>
            <x-site.formation-cover :formation="$formation" class="aspect-[16/10] w-full max-w-md rounded-2xl" />
            <x-admin.field name="image" label="Nouvelle image" type="file" accept="image/png,image/jpeg,image/webp" hint="Depuis votre téléphone : elle est recadrée (format 16:10) et compressée automatiquement. Sans image, un visuel aux couleurs de la catégorie est dessiné." />
            @if ($formation->image)
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remove_image" value="1" class="size-4 accent-gold-400"> Retirer l'image</label>
            @endif
        </section>

        <section class="card space-y-3 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Affichage</h2>
            <label class="flex items-start gap-3 text-sm text-slate-200">
                <input type="checkbox" name="is_active" value="1" class="mt-0.5 size-5 accent-gold-400" @checked(old('is_active', $formation->is_active))>
                <span><span class="font-semibold text-white">Visible sur le site</span><br><span class="text-slate-400">Décochée : la formation est masquée (les élèves qui l'ont achetée y gardent accès).</span></span>
            </label>
            <label class="flex items-start gap-3 text-sm text-slate-200">
                <input type="checkbox" name="is_featured" value="1" class="mt-0.5 size-5 accent-gold-400" @checked(old('is_featured', $formation->is_featured))>
                <span><span class="font-semibold text-white">Mise en avant sur l'accueil</span></span>
            </label>
            <p class="text-xs text-slate-500">L'ordre d'affichage se règle avec les flèches de la liste des formations.</p>
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">{{ $editing ? 'Enregistrer la formation' : 'Créer la formation' }}</button>
    </form>
</x-admin.page>
