<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Enums\FormationCategory;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Services\ImageUploader;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        return view('site.admin.formations.index', [
            'formations' => Formation::query()
                ->withCount(['purchases as sales_count' => fn ($query) => $query->whereNull('live_session_id')->where('status', PurchaseStatus::Validated)])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
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
        return view('site.admin.formations.form', ['formation' => $formation]);
    }

    public function update(Request $request, Formation $formation, ImageUploader $images): RedirectResponse
    {
        $oldPrice = $formation->price;
        $this->fill($formation, $request, $images);
        $changes = array_keys($formation->getDirty());
        $formation->save();

        $details = $oldPrice !== $formation->price ? ' Prix : '.Money::fcfa($oldPrice).' → '.Money::fcfa($formation->price).'.' : '';
        ActivityLog::record(AdminAction::FormationUpdated, 'Formation modifiée : '.$formation->title.'.'.$details, $formation, ['fields' => $changes]);

        return back()->with('status', 'Formation enregistrée.'.($details !== '' ? ' Le nouveau prix s\'applique aux prochains achats ; les achats déjà faits gardent leur montant.' : ''));
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
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
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
