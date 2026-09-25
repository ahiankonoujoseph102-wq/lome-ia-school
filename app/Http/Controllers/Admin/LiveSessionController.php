<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Enums\RegistrationStatus;
use App\Enums\SessionFormat;
use App\Enums\SessionPriceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LiveSessionRequest;
use App\LiveSessions\SessionManager;
use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\LiveSessionRegistration;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PurchaseManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin « Sessions » (admin et modérateurs) : sessions à venir et historique, création, modification,
 * annulation, feuille de présence, enregistrement ajouté comme leçon, remboursement des suppléments.
 */
class LiveSessionController extends Controller
{
    public function __construct(private SessionManager $manager) {}

    public function index(): View
    {
        return view('site.admin.sessions.index', [
            'upcoming' => LiveSession::query()->upcoming()->with('formation')->withCount($this->counts())->get(),
            'past' => LiveSession::query()->past()->with('formation')->withCount($this->counts())->limit(50)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $session = new LiveSession([
            'format' => SessionFormat::tryFrom($request->string('type')->toString()) ?? SessionFormat::Online,
            'duration_minutes' => (int) config('live_sessions.default_duration', 120),
            'location' => config('live_sessions.default_location'),
            'price_type' => SessionPriceType::Included,
            'formation_id' => $request->integer('formation') ?: null,
        ]);

        return view('site.admin.sessions.form', ['session' => $session, 'formations' => $this->formations()]);
    }

    public function store(LiveSessionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $session = $this->manager->create($request->sessionData(), $user);
        ActivityLog::record(AdminAction::SessionCreated, 'Session créée : '.$session->displayTitle().' ('.$session->formation->title.'), le '.$session->starts_at->translatedFormat('j F Y à H\hi').'.', $session);

        return to_route('admin.sessions.show', $session)->with('status', 'Session créée. L\'annonce est publiée et épinglée dans le groupe de la formation.');
    }

    public function show(LiveSession $session): View
    {
        $session->load(['formation', 'creator', 'registrations.user', 'registrations.purchase']);

        return view('site.admin.sessions.show', [
            'session' => $session,
            'registrations' => $session->registrations->groupBy(fn (LiveSessionRegistration $registration): string => $registration->status->value),
            'refunds' => Purchase::query()->where('live_session_id', $session->id)->refundDue()->with(['user', 'liveSession'])->oldest()->get(),
        ]);
    }

    public function edit(LiveSession $session): View
    {
        abort_if($session->isCancelled(), 404);

        return view('site.admin.sessions.form', ['session' => $session, 'formations' => $this->formations()]);
    }

    public function update(LiveSessionRequest $request, LiveSession $session): RedirectResponse
    {
        abort_if($session->isCancelled(), 404);

        /** @var User $user */
        $user = $request->user();
        $data = $request->sessionData();

        if ($data['capacity'] !== null && $data['capacity'] < $session->seatsTaken()) {
            throw ValidationException::withMessages(['capacity' => 'Déjà '.$session->seatsTaken().' place(s) occupée(s) : le nombre de places ne peut pas être plus petit.']);
        }

        if ($data['format'] !== $session->format && $session->registrations()->where('status', '!=', RegistrationStatus::Cancelled->value)->exists()) {
            throw ValidationException::withMessages(['format' => 'Des élèves sont déjà inscrits : le type de session ne peut plus être changé. Annulez la session et créez-en une autre.']);
        }

        $changes = $this->manager->update($session, $data, $user);
        ActivityLog::record(AdminAction::SessionUpdated, 'Session modifiée : '.$session->displayTitle().($changes === [] ? '.' : ' ('.implode(', ', $changes).').'), $session);

        return to_route('admin.sessions.show', $session)->with('status', $changes === []
            ? 'Session enregistrée.'
            : 'Session enregistrée. Les élèves concernés sont prévenus du changement ('.implode(', ', $changes).').');
    }

    public function cancel(Request $request, LiveSession $session): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        /** @var User $user */
        $user = $request->user();
        $this->manager->cancel($session, is_string($reason) ? $reason : null, $user);
        ActivityLog::record(AdminAction::SessionCancelled, 'Session annulée : '.$session->displayTitle().(is_string($reason) && $reason !== '' ? '. Motif : '.$reason : '.'), $session);

        return to_route('admin.sessions.show', $session)->with('status', 'Session annulée. Les élèves concernés sont prévenus par e-mail et notification.');
    }

    /**
     * Feuille de présence (imprimable) d'une session en présentiel.
     */
    public function attendance(LiveSession $session): View
    {
        abort_unless($session->isOnsite(), 404);

        return view('site.admin.sessions.attendance', [
            'session' => $session->load('formation'),
            'registrations' => $session->registrations()
                ->whereIn('status', RegistrationStatus::seatHolding())
                ->with('user')
                ->get()
                ->sortBy(fn (LiveSessionRegistration $registration): string => mb_strtolower($registration->user->name))
                ->values(),
        ]);
    }

    public function toggleAttendance(LiveSession $session, LiveSessionRegistration $registration): RedirectResponse
    {
        abort_unless($registration->live_session_id === $session->id && $registration->status->holdsSeat(), 404);

        $registration->forceFill(['attended_at' => $registration->attended_at ? null : now()])->save();

        return back()->with('status', $registration->user->name.($registration->attended_at ? ' : présent(e).' : ' : absent(e).'));
    }

    /**
     * « Ajouter l'enregistrement comme leçon » : formulaire de leçon pré-rempli (admin uniquement).
     */
    public function recording(Request $request, LiveSession $session): RedirectResponse
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        $module = $session->formation->modules()->reorder()->orderByDesc('position')->orderByDesc('id')->first();

        if ($module === null) {
            return to_route('admin.content.show', $session->formation)->with('status', 'Créez d\'abord un module pour cette formation, puis ajoutez l\'enregistrement comme leçon.');
        }

        return redirect()->route('admin.lessons.create', [
            'module' => $module,
            'titre' => 'Replay : '.$session->displayTitle().' du '.$session->starts_at->translatedFormat('j F Y'),
            'description' => 'Enregistrement de la session '.mb_strtolower($session->format->label()).' du '.$session->starts_at->translatedFormat('j F Y').'.',
        ]);
    }

    /**
     * Supplément remboursé (session annulée par l'école, ou inscription annulée par l'élève dans le délai) :
     * l'achat passe en « Remboursé ».
     */
    public function refund(Request $request, LiveSession $session, Purchase $purchase, PurchaseManager $purchases): RedirectResponse
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);
        abort_unless($purchase->live_session_id === $session->id, 404);

        /** @var User $admin */
        $admin = $request->user();
        $purchases->refund($purchase, $admin);
        ActivityLog::record(AdminAction::PaymentRefunded, 'Supplément remboursé : '.$purchase->item_label.' ('.$purchase->formatted_amount.') pour '.($purchase->user->name ?? 'un élève').'.', $purchase);

        return back()->with('status', 'Supplément marqué comme remboursé.');
    }

    /**
     * @return array<string, \Closure>
     */
    private function counts(): array
    {
        return [
            'registrations as seats_count' => fn ($query) => $query->whereIn('status', RegistrationStatus::seatHolding()),
            'registrations as waitlist_count' => fn ($query) => $query->where('status', RegistrationStatus::Waitlisted->value),
            'registrations as attended_count' => fn ($query) => $query->whereNotNull('attended_at'),
            'supplements as refunds_count' => fn ($query) => $query->refundDue(),
        ];
    }

    /**
     * @return Collection<int, Formation>
     */
    private function formations(): Collection
    {
        return Formation::query()->published()->get(['id', 'title', 'slug']);
    }
}
