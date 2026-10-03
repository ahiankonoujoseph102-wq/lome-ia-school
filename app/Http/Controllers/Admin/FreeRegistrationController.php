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
use App\Support\PhoneNumber;
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
