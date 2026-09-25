<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ConsultationStatus;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationBlock;
use App\Models\ConsultationHour;
use App\Models\ConsultationSettings;
use App\Support\GoogleMeet;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin « Disponibilités » des consultations : lien Google Meet fixe, réglages, horaires par jour,
 * jours ou plages bloqués. Les sessions en direct et en présentiel bloquent déjà leurs créneaux.
 */
class ConsultationAvailabilityController extends Controller
{
    public function edit(): View
    {
        return view('site.admin.consultations.availability', [
            'settings' => ConsultationSettings::current(),
            'hours' => ConsultationHour::query()->orderBy('starts_at')->get()->groupBy('weekday'),
            'blocks' => ConsultationBlock::query()->where('ends_at', '>', now())->orderBy('starts_at')->get(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('settings', [
            'meet_url' => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:180'],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'min_notice_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'max_days_ahead' => ['required', 'integer', 'min:1', 'max:180'],
            'max_per_day' => ['nullable', 'integer', 'min:1', 'max:50'],
            'manage_deadline_hours' => ['required', 'integer', 'min:0', 'max:72'],
        ]);

        $meetUrl = GoogleMeet::normalize($validated['meet_url'] ?? null);

        if (filled($validated['meet_url'] ?? null) && $meetUrl === null) {
            throw ValidationException::withMessages(['meet_url' => 'Collez un lien de réunion Google Meet, par exemple https://meet.google.com/abc-defg-hij.'])->errorBag('settings');
        }

        ConsultationSettings::current()->update([...$validated, 'meet_url' => $meetUrl]);

        return back()->with('status', 'Réglages enregistrés.');
    }

    public function storeHour(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('hours', [
            'weekday' => ['required', 'integer', 'between:1,7'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ], ['ends_at.after' => 'L\'heure de fin doit être après l\'heure de début.']);

        $overlap = ConsultationHour::query()
            ->where('weekday', $validated['weekday'])
            ->where('starts_at', '<', $validated['ends_at'])
            ->where('ends_at', '>', $validated['starts_at'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Cette plage chevauche une plage existante de ce jour.'])->errorBag('hours');
        }

        ConsultationHour::query()->create($validated);

        return back()->with('status', 'Horaires de '.mb_strtolower(ConsultationHour::WEEKDAYS[(int) $validated['weekday']]).' enregistrés.');
    }

    public function destroyHour(ConsultationHour $hour): RedirectResponse
    {
        $hour->delete();

        return back()->with('status', 'Plage horaire supprimée.');
    }

    public function storeBlock(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('blocks', [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:120'],
        ], ['end_date.after_or_equal' => 'La date de fin doit être après la date de début.']);

        $start = CarbonImmutable::parse($validated['start_date'].' '.($validated['start_time'] ?? '00:00'));
        $end = CarbonImmutable::parse(($validated['end_date'] ?? $validated['start_date']).' '.($validated['end_time'] ?? '00:00'));

        if (($validated['end_time'] ?? null) === null) {
            $end = $end->addDay();
        }

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['end_time' => 'La fin doit être après le début.'])->errorBag('blocks');
        }

        ConsultationBlock::query()->create(['starts_at' => $start, 'ends_at' => $end, 'reason' => $validated['reason'] ?? null]);

        $booked = Consultation::query()
            ->where('status', ConsultationStatus::Booked)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->count();

        return back()->with('status', 'Plage bloquée : plus aucune réservation possible.'.($booked > 0 ? ' Attention : '.$booked.' rendez-vous déjà réservé'.($booked > 1 ? 's' : '').' dans cette plage (à annuler si nécessaire dans « Consultations »).' : ''));
    }

    public function destroyBlock(ConsultationBlock $block): RedirectResponse
    {
        $block->delete();

        return back()->with('status', 'Plage débloquée.');
    }
}
