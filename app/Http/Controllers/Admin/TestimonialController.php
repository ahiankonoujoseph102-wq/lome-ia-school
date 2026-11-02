<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Services\ImageUploader;
use App\Support\PastedLink;
use App\Support\YouTube;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Témoignages (admin uniquement) : texte avec photo facultative, capture WhatsApp ou vidéo YouTube.
 * Tant qu'aucun témoignage n'est actif, la section est masquée sur le site.
 */
class TestimonialController extends Controller
{
    public function index(): View
    {
        return view('site.admin.testimonials.index', ['testimonials' => Testimonial::query()->ordered()->get()]);
    }

    public function create(): View
    {
        return view('site.admin.testimonials.form', ['testimonial' => new Testimonial(['type' => 'text'])]);
    }

    public function store(Request $request, ImageUploader $images): RedirectResponse
    {
        $testimonial = new Testimonial(['position' => (int) Testimonial::query()->max('position') + 1]);
        $this->fill($testimonial, $request, $images);

        ActivityLog::record(AdminAction::TestimonialsUpdated, 'Témoignage ajouté ('.$testimonial->typeLabel().($testimonial->name ? ', '.$testimonial->name : '').').', $testimonial);

        return to_route('admin.testimonials.index')->with('status', 'Témoignage ajouté.'.($testimonial->is_active ? ' Il est visible sur le site.' : ' Il est masqué pour le moment.'));
    }

    public function edit(Testimonial $testimonial): View
    {
        return view('site.admin.testimonials.form', ['testimonial' => $testimonial]);
    }

    public function update(Request $request, Testimonial $testimonial, ImageUploader $images): RedirectResponse
    {
        $this->fill($testimonial, $request, $images);

        ActivityLog::record(AdminAction::TestimonialsUpdated, 'Témoignage modifié ('.$testimonial->typeLabel().($testimonial->name ? ', '.$testimonial->name : '').').', $testimonial);

        return to_route('admin.testimonials.index')->with('status', 'Témoignage enregistré.');
    }

    public function toggle(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->forceFill(['is_active' => ! $testimonial->is_active])->save();

        ActivityLog::record(AdminAction::TestimonialsUpdated, 'Témoignage '.($testimonial->is_active ? 'affiché' : 'masqué').($testimonial->name ? ' ('.$testimonial->name.')' : '').'.', $testimonial);

        return back()->with('status', $testimonial->is_active ? 'Témoignage affiché sur le site.' : 'Témoignage masqué.');
    }

    public function move(Testimonial $testimonial, string $direction): RedirectResponse
    {
        $ordered = Testimonial::query()->ordered()->get()->values();
        $index = $ordered->search(fn (Testimonial $item): bool => $item->id === $testimonial->id);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && isset($ordered[$target])) {
            $items = $ordered->all();
            [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

            foreach ($items as $position => $item) {
                $item->forceFill(['position' => $position + 1])->save();
            }
        }

        return back();
    }

    public function destroy(Testimonial $testimonial, ImageUploader $images): RedirectResponse
    {
        $images->delete($testimonial->photo_path);
        $images->delete($testimonial->image_path);
        $testimonial->delete();

        ActivityLog::record(AdminAction::TestimonialsUpdated, 'Témoignage supprimé'.($testimonial->name ? ' ('.$testimonial->name.')' : '').'.');

        return to_route('admin.testimonials.index')->with('status', 'Témoignage supprimé.');
    }

    private function fill(Testimonial $testimonial, Request $request, ImageUploader $images): void
    {
        PastedLink::cleanRequest($request, ['youtube_url']);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Testimonial::TYPES))],
            'name' => [Rule::requiredIf($request->input('type') === 'text'), 'nullable', 'string', 'max:120'],
            'formation' => ['nullable', 'string', 'max:160'],
            'text' => [Rule::requiredIf($request->input('type') === 'text'), 'nullable', 'string', 'max:1500'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'youtube_url' => [Rule::requiredIf($request->input('type') === 'video'), 'nullable', 'string', 'max:255'],
            'video_format' => ['nullable', Rule::in(YouTube::FORMATS)],
            'is_active' => ['nullable', 'boolean'],
            'remove_photo' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Indiquez le nom de l\'élève.',
            'text.required' => 'Écrivez le témoignage.',
            'youtube_url.required' => 'Collez le lien de la vidéo YouTube.',
        ]);

        $type = (string) $validated['type'];

        if ($type === 'video' && YouTube::id($validated['youtube_url'] ?? null) === null) {
            throw ValidationException::withMessages(['youtube_url' => YouTube::invalidMessage()]);
        }

        if ($type === 'screenshot' && ! $request->hasFile('image') && ($testimonial->type !== 'screenshot' || blank($testimonial->image_path))) {
            throw ValidationException::withMessages(['image' => 'Envoyez la capture d\'écran WhatsApp.']);
        }

        $testimonial->fill([
            'type' => $type,
            'name' => filled($validated['name'] ?? null) ? trim((string) $validated['name']) : null,
            'formation' => filled($validated['formation'] ?? null) ? trim((string) $validated['formation']) : null,
            'text' => $type === 'text' ? trim((string) $validated['text']) : null,
            'youtube_url' => $type === 'video' ? trim((string) $validated['youtube_url']) : null,
            'video_format' => $type === 'video' && filled($validated['video_format'] ?? null) ? (string) $validated['video_format'] : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        if (($type !== 'text' || $request->boolean('remove_photo')) && $testimonial->photo_path) {
            $images->delete($testimonial->photo_path);
            $testimonial->photo_path = null;
        }

        if ($type === 'text' && $request->hasFile('photo')) {
            $images->delete($testimonial->photo_path);
            $testimonial->photo_path = $images->cover($request->file('photo'), 'testimonials', 320, 320, field: 'photo');
        }

        if ($type !== 'screenshot' && $testimonial->image_path) {
            $images->delete($testimonial->image_path);
            $testimonial->image_path = null;
        }

        if ($type === 'screenshot' && $request->hasFile('image')) {
            $images->delete($testimonial->image_path);
            $testimonial->image_path = $images->fit($request->file('image'), 'testimonials', 1080, 2400, field: 'image');
        }

        $testimonial->save();
    }
}
