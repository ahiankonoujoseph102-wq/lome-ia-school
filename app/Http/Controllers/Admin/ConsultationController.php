<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Consultations\ConsultationBooker;
use App\Enums\AdminAction;
use App\Enums\ConsultationAttendance;
use App\Enums\ConsultationStatus;
use App\Http\Controllers\Controller;
use App\Mail\ConsultationMail;
use App\Models\Consultation;
use App\Models\Formation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Admin « Consultations » (admin uniquement) : rendez-vous à venir jour par jour et passés,
 * annulation avec message, présence, notes privées, formation recommandée et envoi de la recommandation.
 */
class ConsultationController extends Controller
{
    public function index(): View
    {
        $upcoming = Consultation::query()
            ->upcoming()
            ->with(['formation', 'user', 'recommendedFormation'])
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Consultation $consultation): string => $consultation->starts_at->format('Y-m-d'));

        $past = Consultation::query()
            ->where(fn ($query) => $query->where('status', ConsultationStatus::Cancelled)->orWhere('ends_at', '<=', now()))
            ->with(['formation', 'user', 'recommendedFormation'])
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('site.admin.consultations.index', [
            'upcoming' => $upcoming,
            'past' => $past,
            'formations' => Formation::query()->published()->orderBy('title')->get(['id', 'title', 'slug']),
        ]);
    }

    public function cancel(Request $request, Consultation $consultation, ConsultationBooker $booker): RedirectResponse
    {
        $validated = $request->validateWithBag('cancel'.$consultation->id, [
            'message' => ['required', 'string', 'min:3', 'max:500'],
        ], ['message.required' => 'Écrivez un message au visiteur : il le reçoit par e-mail.']);

        $booker->cancel($consultation, 'admin', (string) $validated['message']);
        ActivityLog::record(AdminAction::ConsultationCancelled, 'Consultation annulée : '.$consultation->name.', '.$consultation->starts_at->translatedFormat('j F Y à H\hi').'.', $consultation);

        return back()->with('status', 'Consultation annulée. Le visiteur a été prévenu par e-mail et le créneau est de nouveau disponible.');
    }

    public function attendance(Request $request, Consultation $consultation): RedirectResponse
    {
        $validated = $request->validate(['attendance' => ['required', Rule::enum(ConsultationAttendance::class)]]);

        $consultation->forceFill(['attendance' => $validated['attendance']])->save();

        return back()->with('status', 'Présence enregistrée : '.mb_strtolower(ConsultationAttendance::from($validated['attendance'])->label()).'.');
    }

    public function followUp(Request $request, Consultation $consultation): RedirectResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:5000'],
            'recommended_formation_id' => ['nullable', 'integer', Rule::exists('formations', 'id')->where('is_active', true)],
        ]);

        $consultation->forceFill([
            'admin_notes' => filled($validated['admin_notes'] ?? null) ? trim((string) $validated['admin_notes']) : null,
            'recommended_formation_id' => $validated['recommended_formation_id'] ?? null,
        ])->save();

        return back()->with('status', 'Notes et recommandation enregistrées.');
    }

    public function recommend(Consultation $consultation): RedirectResponse
    {
        if ($consultation->recommended_formation_id === null) {
            return back()->withErrors(['recommendation'.$consultation->id => 'Choisissez d\'abord la formation recommandée, puis enregistrez.']);
        }

        Mail::to($consultation->email)->send(new ConsultationMail($consultation, 'recommendation'));
        $consultation->forceFill(['recommendation_sent_at' => now()])->save();

        return back()->with('status', 'Recommandation envoyée par e-mail à '.$consultation->name.'.');
    }
}
