@php
    use App\Enums\FormationCategory;

    $editing = $formation->exists;
    $isPack = $formation->isPack();
    $choices ??= collect();
    $includedIds = collect(old('included', $isPack ? $formation->includedFormations->modelKeys() : []))->map(fn ($id) => (int) $id)->all();
@endphp

<x-admin.page :title="$editing ? $formation->title : 'Nouvelle formation'" :subtitle="$isPack ? 'Offre d\'accompagnement' : null" :back="route('admin.formations.index')" back-label="Formations et prix">
    @if ($editing)
        <x-slot:actions>
            <a href="{{ $isPack ? route('packs').'#'.$formation->slug : route('formations.show', $formation) }}" target="_blank" rel="noopener" class="btn-ghost"><flux:icon.eye class="size-5" /> Voir la page</a>
        </x-slot:actions>
    @endif

    @if ($editing && $formation->isFree() && $formation->kind === \App\Enums\FormationKind::Course)
        <a href="{{ route('admin.free-registrations.show', $formation) }}" class="card flex items-center justify-between gap-3 p-4 text-sm text-slate-200 hover:text-white" data-test="free-ended-link">
            <span>Inscriptions, vidéo de présentation et « Formation terminée »@if ($formation->isFreeEnded()) <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-semibold text-gold-200 ring-1 ring-gold-500/60">Terminée</span>@endif</span>
            <flux:icon.arrow-right class="size-5 shrink-0 text-gold-300" />
        </a>
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
                <x-admin.field name="price" label="Prix" type="number" unit="FCFA" :value="$formation->price" :hint="$isPack ? 'Les achats déjà faits gardent leur montant.' : ($editing ? 'Les achats déjà faits gardent leur montant.' : 'En chiffres, sans espace (ex. 25000).').' 0 = formation gratuite (inscription avec options, sans paiement).'" />
                <x-admin.field name="category" label="Catégorie" type="select" :options="collect(FormationCategory::cases())->mapWithKeys(fn ($category) => [$category->value => $category->label()])->all()" :value="$formation->category?->value" />
            </div>
            <x-admin.field name="subtitle" label="Sous-titre" :value="$formation->subtitle" />
            <x-admin.field name="description" label="Description" type="textarea" :rows="7" :value="$formation->description" hint="Ce que l'élève apprend à faire, sans promettre de résultat (revenus, clients…)." />
            @if ($editing && $isPack)
                <p class="text-xs text-slate-500">Présentée sur la page {{ route('packs') }}, avec les autres offres. La description s'affiche telle quelle sur la carte de l'offre.</p>
            @elseif ($editing)
                <p class="text-xs text-slate-500">Adresse de la page : {{ route('formations.show', $formation) }} (elle ne change pas, pour ne casser aucun lien partagé).</p>
            @endif
        </section>

        @if ($isPack)
            <section class="card space-y-4 p-5 sm:p-6" data-test="pack-included-editor">
                <div>
                    <h2 class="font-semibold text-white">Formations incluses</h2>
                    <p class="mt-1 text-sm text-slate-400">Cochez les formations que l'offre ouvre à l'acheteur (accès provisoire ou validé), et le Groupe VIP. Une formation ajoutée s'ouvre aussitôt aux élèves qui ont déjà l'offre ; une formation décochée n'est pas retirée à ceux qui l'ont déjà.</p>
                </div>
                <input type="hidden" name="included_sent" value="1">
                <ul class="divide-y divide-veil/[0.06] overflow-hidden rounded-2xl ring-1 ring-veil/10">
                    @foreach ($choices as $choice)
                        <li>
                            <label class="flex items-start gap-3 px-4 py-3 text-sm text-slate-200 hover:bg-veil/[0.04]">
                                <input type="checkbox" name="included[]" value="{{ $choice->id }}" class="mt-0.5 size-5 shrink-0 accent-gold-400" data-test="pack-included-{{ $choice->slug }}" @checked(in_array($choice->id, $includedIds, true))>
                                <span class="min-w-0 flex-1">
                                    <span class="font-semibold text-white">{{ $choice->title }}</span>
                                    <span class="block text-xs text-slate-400">
                                        @if ($choice->isGroupOnly())
                                            Groupe de discussion seul
                                        @else
                                            {{ $choice->formatted_price }}{{ $choice->is_active ? '' : ' · masquée' }}
                                        @endif
                                    </span>
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>
                @if ($editing)
                    <p class="text-sm text-slate-300" data-test="pack-separate-total">Total des formations cochées si achetées séparément (prix actuels) : <span class="font-semibold text-gold-200">{{ \App\Support\Money::fcfa($formation->separatePrice()) }}</span>, affiché sur la page des offres quand il dépasse le prix de l'offre.</p>
                @endif
            </section>
        @endif

        <section @class(['card space-y-5 p-5 sm:p-6', 'hidden' => $isPack])>
            <h2 class="font-semibold text-white">Contenu de la page</h2>
            <x-admin.list-editor name="outcomes" label="Ce que vous allez apprendre" :items="$formation->outcomes ?? []" add-label="Ajouter un point" />
            <x-admin.list-editor name="audience" label="Pour qui est cette formation ?" :items="$formation->audience ?? []" add-label="Ajouter un profil" />
            <x-admin.list-editor name="bonuses" label="Bonus" :items="$formation->exists ? $formation->bonuses : []" add-label="Ajouter un bonus" hint="Vide : la section « Bonus » est masquée. N'ajoutez que des bonus réellement fournis aux élèves." />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">{{ $isPack ? 'Affiche de l\'offre' : 'Image' }}</h2>
            <x-site.formation-cover :formation="$formation" class="aspect-[16/10] w-full max-w-md rounded-2xl" />
            <x-admin.field name="image" label="Nouvelle image" type="file" accept="image/png,image/jpeg,image/webp" :hint="$isPack
                ? 'JPEG, PNG ou WebP, 8 Mo au plus, format conseillé 1600 × 1000 pixels (recadrée en 16:10 et compressée). Affichée en haut de la carte de l\'offre ; sans image, la carte s\'affiche sans visuel.'
                : 'Depuis votre téléphone : elle est recadrée (format 16:10) et compressée automatiquement. Sans image, un visuel aux couleurs de la catégorie est dessiné.'" />
            @if ($formation->image)
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remove_image" value="1" class="size-4 accent-gold-400"> Retirer l'image</label>
            @endif
        </section>

        <section class="card space-y-3 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Affichage</h2>
            <label class="flex items-start gap-3 text-sm text-slate-200">
                <input type="checkbox" name="is_active" value="1" class="mt-0.5 size-5 accent-gold-400" @checked(old('is_active', $formation->is_active))>
                <span><span class="font-semibold text-white">Visible sur le site</span><br><span class="text-slate-400">{{ $isPack ? 'Décochée : l\'offre est masquée (ceux qui l\'ont achetée gardent leurs accès). Tant qu\'aucune offre n\'est visible, la page des offres n\'est pas accessible au public.' : 'Décochée : la formation est masquée (les élèves qui l\'ont achetée y gardent accès).' }}</span></span>
            </label>
            <label @class(['flex items-start gap-3 text-sm text-slate-200', 'hidden' => $isPack])>
                <input type="checkbox" name="is_featured" value="1" class="mt-0.5 size-5 accent-gold-400" @checked(old('is_featured', $formation->is_featured))>
                <span><span class="font-semibold text-white">Mise en avant sur l'accueil</span></span>
            </label>
            <p class="text-xs text-slate-500">L'ordre d'affichage se règle avec les flèches de la liste des formations.</p>
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">{{ $editing ? ($isPack ? 'Enregistrer l\'offre' : 'Enregistrer la formation') : 'Créer la formation' }}</button>
    </form>
</x-admin.page>
