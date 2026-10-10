<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Admin\CsvExport;
use App\Enums\AdminAction;
use App\Enums\FreeOptionMode;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\FreeRegistration;
use App\Models\FreeRegistrationOption;
use App\Support\InternalPath;
use App\Support\PastedLink;
use App\Support\PhoneNumber;
use App\Support\YouTube;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inscriptions d'une formation gratuite : date, lieu, options (libellé, horaires, déroulement, places)
 * et liste des inscrits par option, avec export CSV.
 */
class FreeRegistrationController extends Controller
{
    /** Nombre maximal d'options par formation. */
    public const int MAX_OPTIONS = 8;

    public function show(Formation $formation): View
    {
        abort_unless($formation->isFree(), 404);

        $options = $formation->freeOptions()->withTakenCount()->get();
        $registrations = $this->activeRegistrations($formation);

        return view('site.admin.free-registrations.show', [
            'formation' => $formation,
            'options' => $options,
            'registrationsByOption' => $registrations->groupBy(fn (FreeRegistration $registration): string => (string) $registration->free_registration_option_id),
            'total' => $registrations->count(),
            'capacity' => (int) $options->sum('capacity'),
            'modes' => collect(FreeOptionMode::cases())->mapWithKeys(fn (FreeOptionMode $mode): array => [$mode->value => $mode->label()])->all(),
            'maxOptions' => self::MAX_OPTIONS,
        ]);
    }

    public function update(Request $request, Formation $formation): RedirectResponse
    {
        abort_unless($formation->isFree(), 404);

        // Lignes vides (le formulaire propose toujours une ligne pour ajouter une option) : ignorées.
        $rows = collect((array) $request->input('options', []))
            ->filter(fn ($row): bool => is_array($row) && (filled($row['id'] ?? null) || filled($row['label'] ?? null)))
            ->values();
        $request->merge(['options' => $rows->all()]);

        $validated = $request->validate([
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'event_location' => ['nullable', 'string', 'max:255'],
            'options' => ['array', 'max:'.self::MAX_OPTIONS],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.remove' => ['nullable', 'boolean'],
            'options.*.label' => ['required_unless:options.*.remove,1', 'nullable', 'string', 'max:120'],
            'options.*.schedule' => ['nullable', 'string', 'max:160'],
            'options.*.mode' => ['required_unless:options.*.remove,1', 'nullable', Rule::enum(FreeOptionMode::class)],
            'options.*.capacity' => ['required_unless:options.*.remove,1', 'nullable', 'integer', 'min:1', 'max:10000'],
        ], [
            'event_date.date_format' => 'Choisissez une date valide.',
            'options.max' => 'Au plus '.self::MAX_OPTIONS.' options.',
            'options.*.label.required_unless' => 'Indiquez le libellé de l\'option.',
            'options.*.mode.required_unless' => 'Choisissez présentiel ou en ligne.',
            'options.*.capacity.required_unless' => 'Indiquez le nombre de places.',
            'options.*.capacity.integer' => 'Le nombre de places s\'écrit en chiffres.',
            'options.*.capacity.min' => 'Au moins 1 place.',
        ]);

        $existing = $formation->freeOptions()->withTakenCount()->get()->keyBy('id');
        $errors = [];

        foreach ($validated['options'] ?? [] as $index => $row) {
            $option = filled($row['id'] ?? null) ? $existing->get((int) $row['id']) : null;

            if (filled($row['id'] ?? null) && $option === null) {
                $errors["options.$index.label"] = 'Cette option n\'existe plus : rechargez la page.';

                continue;
            }

            if ($option === null) {
                continue;
            }

            $taken = $option->seatsTaken();

            if (! empty($row['remove']) && $option->registrations()->exists()) {
                $errors["options.$index.label"] = 'Des personnes se sont inscrites à cette option : elle ne peut pas être supprimée.';
            } elseif (empty($row['remove']) && (int) $row['capacity'] < $taken) {
                $errors["options.$index.capacity"] = $taken.' personnes sont déjà inscrites : au moins '.$taken.' places.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($formation, $validated): void {
            $existing = $formation->freeOptions()->get()->keyBy('id');

            $formation->forceFill([
                'event_date' => $validated['event_date'] ?? null,
                'event_location' => filled($validated['event_location'] ?? null) ? trim((string) $validated['event_location']) : null,
            ])->save();

            $position = 0;

            foreach ($validated['options'] ?? [] as $row) {
                $option = filled($row['id'] ?? null) ? $existing->get((int) $row['id']) : new FreeRegistrationOption(['formation_id' => $formation->id]);

                if (! $option instanceof FreeRegistrationOption) {
                    continue;
                }

                if (! empty($row['remove'])) {
                    if ($option->exists) {
                        $option->delete();
                    }

                    continue;
                }

                $option->fill([
                    'label' => trim((string) $row['label']),
                    'schedule' => filled($row['schedule'] ?? null) ? trim((string) $row['schedule']) : null,
                    'mode' => $row['mode'],
                    'capacity' => (int) $row['capacity'],
                    'position' => ++$position,
                ])->save();
            }
        });

        ActivityLog::record(AdminAction::FreeRegistrationUpdated, 'Inscription gratuite modifiée : '.$formation->title.' (date, lieu et options).', $formation);

        return to_route('admin.free-registrations.show', $formation)->with('status', 'Inscription gratuite enregistrée.');
    }

    /**
     * Vidéo de présentation : lien YouTube (watch, youtu.be, shorts…), interrupteur et format.
     * Aucun lien n'est inventé : vide = la page s'affiche sans vidéo.
     */
    public function updateVideo(Request $request, Formation $formation): RedirectResponse
    {
        abort_unless($formation->isFree(), 404);

        PastedLink::cleanRequest($request, ['presentation_video_url']);

        $validated = $request->validate([
            'presentation_video_url' => ['nullable', 'string', 'max:500'],
            'presentation_video_enabled' => ['nullable', 'boolean'],
            'presentation_video_format' => ['nullable', Rule::in(YouTube::FORMATS)],
        ]);

        $url = trim((string) ($validated['presentation_video_url'] ?? ''));
        $enabled = (bool) ($validated['presentation_video_enabled'] ?? false);

        if ($url !== '' && YouTube::id($url) === null) {
            throw ValidationException::withMessages(['presentation_video_url' => YouTube::invalidMessage().' Un lien Shorts (https://www.youtube.com/shorts/…) fonctionne aussi.']);
        }

        if ($enabled && $url === '') {
            throw ValidationException::withMessages(['presentation_video_url' => 'Collez le lien YouTube de la vidéo, ou éteignez « Afficher la vidéo ».']);
        }

        $formation->forceFill([
            'presentation_video_url' => $url !== '' ? $url : null,
            'presentation_video_enabled' => $enabled,
            'presentation_video_format' => $url !== '' ? YouTube::format($url, $validated['presentation_video_format'] ?? null) : null,
        ])->save();

        $detail = match (true) {
            $url === '' => 'sans lien',
            ! $enabled => 'masquée',
            default => 'affichée, format '.($formation->presentationVideoIsVertical() ? 'vertical' : 'horizontal'),
        };

        ActivityLog::record(AdminAction::FreeRegistrationUpdated, 'Vidéo de présentation modifiée : '.$formation->title.' ('.$detail.').', $formation);

        return to_route('admin.free-registrations.show', $formation)->with('status', 'Vidéo de présentation enregistrée.');
    }

    /**
     * « Formation terminée » : interrupteur, texte et destination du bouton qui remplace l'inscription.
     * La destination doit être une adresse interne du site (toute adresse externe est refusée).
     * Les inscriptions existantes et le groupe des inscrits ne changent pas.
     */
    public function updateEnded(Request $request, Formation $formation): RedirectResponse
    {
        abort_unless($formation->isFree(), 404);

        $validated = $request->validate([
            'free_ended' => ['nullable', 'boolean'],
            'free_ended_button_label' => ['nullable', 'string', 'max:80'],
            'free_ended_button_url' => ['nullable', 'string', 'max:500'],
        ], [
            'free_ended_button_label.max' => 'Le texte du bouton est trop long (80 caractères au maximum).',
            'free_ended_button_url.max' => 'L\'adresse est trop longue.',
        ]);

        $label = trim((string) preg_replace('/\s+/u', ' ', (string) ($validated['free_ended_button_label'] ?? '')));
        $rawUrl = PastedLink::clean((string) ($validated['free_ended_button_url'] ?? ''));
        $path = $rawUrl !== '' ? InternalPath::clean($rawUrl) : null;

        if ($rawUrl !== '' && $path === null) {
            throw ValidationException::withMessages(['free_ended_button_url' => 'Indiquez une adresse de ce site, par exemple /accompagnement. Les adresses d\'autres sites ne sont pas acceptées.']);
        }

        $ended = (bool) ($validated['free_ended'] ?? false);

        $formation->forceFill([
            'free_ended' => $ended,
            // Valeurs par défaut non enregistrées : un champ vidé revient au texte et à la page d'origine.
            'free_ended_button_label' => $label !== '' && $label !== Formation::ENDED_BUTTON_LABEL ? $label : null,
            'free_ended_button_url' => $path !== null && $path !== Formation::ENDED_BUTTON_PATH ? $path : null,
        ])->save();

        ActivityLog::record(
            AdminAction::FreeRegistrationUpdated,
            'Formation terminée : '.$formation->title.' ('.($ended ? 'oui' : 'non').', bouton « '.$formation->endedButtonLabel().' » vers '.$formation->endedButtonPath().').',
            $formation,
        );

        return to_route('admin.free-registrations.show', $formation)->with('status', $ended ? 'La formation est marquée comme terminée.' : 'La formation n\'est plus marquée comme terminée.');
    }

    public function export(Formation $formation): StreamedResponse
    {
        abort_unless($formation->isFree(), 404);

        ActivityLog::record(AdminAction::Export, 'Export des inscrits : '.$formation->title.'.', $formation);

        $rows = $this->activeRegistrations($formation)->map(fn (FreeRegistration $registration): array => [
            $registration->option->label ?? 'Sans option',
            $registration->user->name,
            PhoneNumber::format($registration->user->phone),
            $registration->user->email,
            $registration->created_at?->format('d/m/Y H:i'),
        ]);

        return CsvExport::download('inscrits-'.$formation->slug.'-'.now()->format('Y-m-d').'.csv', ['Option', 'Nom', 'WhatsApp', 'E-mail', 'Inscrit(e) le'], $rows);
    }

    /**
     * Inscriptions en cours, par option (dans l'ordre des options) puis par date d'inscription.
     *
     * @return EloquentCollection<int, FreeRegistration>
     */
    private function activeRegistrations(Formation $formation): EloquentCollection
    {
        $order = $formation->freeOptions()->pluck('id')->flip();

        return $formation->freeRegistrations()
            ->active()
            ->with(['user:id,name,phone,email', 'option'])
            ->oldest()
            ->get()
            ->sortBy(fn (FreeRegistration $registration): int => (int) ($order[$registration->free_registration_option_id] ?? PHP_INT_MAX))
            ->values();
    }
}
