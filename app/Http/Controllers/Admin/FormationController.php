<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Enums\FormationCategory;
use App\Enums\FormationKind;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Packs\PackAccess;
use App\Services\ImageUploader;
use App\Settings\SiteSettings;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * « Formations et prix » (admin uniquement) : modifier ou créer une formation (titre, prix, textes,
 * catégorie, image, bonus, mise en avant, ordre, visibilité). Un changement de prix ne modifie jamais
 * les achats déjà faits : chaque achat garde le montant payé.
 */
class FormationController extends Controller
{
    public function index(): View
    {
        // Le Groupe VIP (groupe seul) se gère dans « Groupes de discussion » : il n'est pas listé ici.
        $all = Formation::query()
            ->where('kind', '!=', FormationKind::Group->value)
            ->withCount(['purchases as sales_count' => fn ($query) => $query->whereNull('live_session_id')->where('status', PurchaseStatus::Validated)])
            ->with('includedFormations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('site.admin.formations.index', [
            'formations' => $all->reject->isPack()->values(),
            'packs' => $all->filter->isPack()->values(),
        ]);
    }

    public function create(): View
    {
        return view('site.admin.formations.form', ['formation' => new Formation(['category' => FormationCategory::AiBasics, 'is_active' => false])]);
    }

    public function store(Request $request, ImageUploader $images): RedirectResponse
    {
        $formation = new Formation([
            'sort_order' => (int) Formation::query()->max('sort_order') + 1,
            'formats' => ['recorded'],
        ]);
        $this->fill($formation, $request, $images);
        $formation->slug = self::uniqueSlug($formation->title);
        // Création : le groupe de discussion est créé automatiquement (Formation::booted).
        $formation->save();

        ActivityLog::record(AdminAction::FormationCreated, 'Formation créée : '.$formation->title.' ('.Money::fcfa($formation->price).', '.($formation->is_active ? 'visible' : 'masquée').').', $formation);

        return to_route('admin.formations.edit', $formation)->with('status', 'Formation créée, avec son groupe de discussion. Ajoutez maintenant ses modules et ses leçons dans « Contenu ».');
    }

    public function edit(Formation $formation): View
    {
        abort_if($formation->isGroupOnly(), 404);

        return view('site.admin.formations.form', [
            'formation' => $formation,
            'choices' => $formation->isPack() ? $this->packChoices($formation) : collect(),
        ]);
    }

    public function update(Request $request, Formation $formation, ImageUploader $images, PackAccess $packs): RedirectResponse
    {
        abort_if($formation->isGroupOnly(), 404);

        $oldPrice = $formation->price;
        $this->fill($formation, $request, $images);
        $changes = array_keys($formation->getDirty());
        $included = $formation->isPack() ? $this->validatedIncluded($request, $formation) : null;

        DB::transaction(function () use ($formation, $included, $packs, &$changes): void {
            $formation->save();

            if ($included !== null && $this->syncIncluded($formation, $included, $packs)) {
                $changes[] = 'included_formations';
            }
        });

        $details = $oldPrice !== $formation->price ? ' Prix : '.Money::fcfa($oldPrice).' → '.Money::fcfa($formation->price).'.' : '';

        if (in_array('included_formations', $changes, true)) {
            $details .= ' Formations incluses : '.$formation->includedFormations()->pluck('title')->implode(' ; ').'.';
        }

        ActivityLog::record(AdminAction::FormationUpdated, ($formation->isPack() ? 'Offre d\'accompagnement modifiée : ' : 'Formation modifiée : ').$formation->title.'.'.$details, $formation, ['fields' => $changes]);

        return back()->with('status', ($formation->isPack() ? 'Offre enregistrée.' : 'Formation enregistrée.').($oldPrice !== $formation->price ? ' Le nouveau prix s\'applique aux prochains achats ; les achats déjà faits gardent leur montant.' : ''));
    }

    /**
     * Formations qu'une offre peut inclure (formations habituelles et groupes seuls), celles déjà incluses
     * en premier, dans leur ordre.
     *
     * @return Collection<int, Formation>
     */
    private function packChoices(Formation $pack): Collection
    {
        $included = $pack->includedFormations()->get();
        $others = Formation::query()
            ->whereIn('kind', [FormationKind::Course->value, FormationKind::Group->value])
            ->whereKeyNot($included->modelKeys())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $included->toBase()->concat($others)->values();
    }

    /**
     * Formations cochées (offre d'accompagnement), dans l'ordre de la liste.
     *
     * @return list<int>
     */
    private function validatedIncluded(Request $request, Formation $pack): array
    {
        // Formulaire envoyé sans la liste (ancienne page) : la liste reste telle quelle.
        if (! $request->boolean('included_sent')) {
            return array_values($pack->includedFormations()->pluck('formations.id')->map(fn ($id): int => (int) $id)->all());
        }

        $validated = $request->validate([
            'included' => ['array', 'max:60'],
            'included.*' => ['integer', Rule::exists('formations', 'id')->whereIn('kind', [FormationKind::Course->value, FormationKind::Group->value])],
        ], [
            'included.*.exists' => 'Une offre ne peut inclure que des formations ou le Groupe VIP.',
        ]);

        return array_values(array_unique(array_map('intval', $validated['included'] ?? [])));
    }

    /**
     * Enregistre la liste des formations incluses. Une formation ajoutée s'ouvre aussitôt aux élèves qui ont déjà
     * l'offre ; une formation retirée de la liste n'est pas retirée à ceux qui l'ont déjà.
     *
     * @param  list<int>  $included
     */
    private function syncIncluded(Formation $pack, array $included, PackAccess $packs): bool
    {
        $before = $pack->includedFormations()->pluck('formations.id')->map(fn ($id): int => (int) $id)->all();

        if ($before === $included) {
            return false;
        }

        $pack->includedFormations()->sync(collect($included)->mapWithKeys(fn (int $id, int $index): array => [$id => ['position' => $index + 1]])->all());

        if (array_diff($included, $before) !== []) {
            $pack->enrollments()->with(['user', 'formation'])->get()->each(fn (Enrollment $enrollment) => $packs->grant($enrollment));
        }

        return true;
    }

    /**
     * Affiche en haut de la page des offres (/accompagnement) : envoyée, remplacée ou retirée depuis la partie
     * des offres de « Formations et prix ». Mêmes contrôles et même recadrage (16:10) que l'image d'une formation.
     */
    public function updatePacksPoster(Request $request, ImageUploader $images): RedirectResponse
    {
        $request->validate([
            'poster' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_poster' => ['nullable', 'boolean'],
        ], [
            'poster.image' => 'L\'image doit être une photo (JPEG, PNG ou WebP).',
            'poster.mimes' => 'L\'image doit être une photo (JPEG, PNG ou WebP).',
            'poster.max' => 'L\'image ne doit pas dépasser 8 Mo.',
        ]);

        $current = SiteSettings::get('site.packs_poster');

        if ($request->hasFile('poster')) {
            $path = $images->cover($request->file('poster'), 'packs', 1600, 1000, 'poster');
            SiteSettings::save(['site.packs_poster' => $path]);
            $this->deleteUploaded(is_string($current) ? $current : null, $images);
            ActivityLog::record(AdminAction::SettingsUpdated, 'Affiche de la page des offres d\'accompagnement '.($current ? 'remplacée' : 'ajoutée').'.');

            return back()->with('status', 'Affiche enregistrée : elle apparaît en haut de la page des offres.');
        }

        if ($request->boolean('remove_poster') && $current) {
            // Texte vide plutôt que null : la colonne des réglages n'accepte pas null.
            SiteSettings::save(['site.packs_poster' => '']);
            $this->deleteUploaded(is_string($current) ? $current : null, $images);
            ActivityLog::record(AdminAction::SettingsUpdated, 'Affiche de la page des offres d\'accompagnement retirée.');

            return back()->with('status', 'Affiche retirée : la page des offres s\'affiche sans image.');
        }

        return back()->withErrors(['poster' => 'Choisissez une image, ou cochez « Retirer l\'affiche ».']);
    }

    public function move(Formation $formation, string $direction): RedirectResponse
    {
        // Ordre propre à chaque type : les formations entre elles, les offres d'accompagnement entre elles.
        $ordered = Formation::query()->where('kind', $formation->kind->value)->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $ordered->search(fn (Formation $item): bool => $item->id === $formation->id);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ordered[$target])) {
            $items = $ordered->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

            foreach ($items as $position => $item) {
                if ($item->sort_order !== $position + 1) {
                    $item->forceFill(['sort_order' => $position + 1])->save();
                }
            }
        }

        return back();
    }

    private function fill(Formation $formation, Request $request, ImageUploader $images): void
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            // Une offre d'accompagnement est toujours payante (0 FCFA = formation gratuite).
            'price' => ['required', 'integer', $formation->isPack() ? 'min:1' : 'min:0', 'max:100000000'],
            'category' => ['required', Rule::enum(FormationCategory::class)],
            'outcomes' => ['array', 'max:15'],
            'outcomes.*' => ['nullable', 'string', 'max:255'],
            'audience' => ['array', 'max:15'],
            'audience.*' => ['nullable', 'string', 'max:255'],
            'bonuses' => ['array', 'max:15'],
            'bonuses.*' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'remove_image' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Indiquez le titre de la formation.',
            'description.required' => 'Écrivez la description de la formation.',
            'price.required' => 'Indiquez le prix en FCFA.',
            'price.integer' => 'Le prix s\'écrit en chiffres, sans espace ni « FCFA » (ex. 25000).',
            'image.image' => 'L\'image doit être une photo (JPEG, PNG ou WebP).',
        ]);

        $formation->fill([
            'title' => trim((string) $validated['title']),
            'subtitle' => filled($validated['subtitle'] ?? null) ? trim((string) $validated['subtitle']) : null,
            'description' => trim((string) $validated['description']),
            'price' => (int) $validated['price'],
            'category' => $validated['category'],
            'outcomes' => SiteSettingsController::lines($validated['outcomes'] ?? []),
            'audience' => SiteSettingsController::lines($validated['audience'] ?? []),
            'bonuses' => SiteSettingsController::lines($validated['bonuses'] ?? []),
            'is_featured' => $request->boolean('is_featured'),
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->boolean('remove_image') && $formation->image) {
            $this->deleteUploaded($formation->image, $images);
            $formation->image = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteUploaded($formation->image, $images);
            // Format des cartes et de la page formation (16:10), compressé.
            $formation->image = $images->cover($request->file('image'), 'formations', 1600, 1000);
        }
    }

    /**
     * Seules les images envoyées depuis l'admin sont supprimées (jamais les fichiers de public/images).
     */
    private function deleteUploaded(?string $image, ImageUploader $images): void
    {
        if ($image && str_contains($image, '/')) {
            $images->delete($image);
        }
    }

    private static function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'formation', 80, '');
        $slug = $base;
        $suffix = 2;

        while (Formation::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
