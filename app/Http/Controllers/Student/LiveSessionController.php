<?php

namespace App\Http\Controllers\Student;

use App\Actions\Payments\SubmitManualPayment;
use App\Enums\PurchaseStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentProofRequest;
use App\LiveSessions\CalendarFile;
use App\LiveSessions\SessionRegistrar;
use App\Models\LiveSession;
use App\Models\User;
use App\Payments\PaymentMethod;
use App\Payments\PaymentMethods;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Sessions dans l'espace élève : liste, détail (lien Google Meet réservé aux élèves ayant accès),
 * inscription en présentiel, annulation, paiement du supplément, agenda, « Rejoindre la session ».
 */
class LiveSessionController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('site.student.sessions.index', [
            'sessions' => self::upcomingFor($user),
            'registrations' => $user->liveSessionRegistrations()->get()->keyBy('live_session_id'),
        ]);
    }

    public function show(Request $request, LiveSession $session): View
    {
        Gate::authorize('view', $session);

        /** @var User $user */
        $user = $request->user();

        return view('site.student.sessions.show', [
            'session' => $session->load('formation'),
            'registration' => $session->registrations()->where('user_id', $user->id)->with('purchase')->first(),
        ]);
    }

    public function register(Request $request, LiveSession $session, SessionRegistrar $registrar): RedirectResponse
    {
        Gate::authorize('register', $session);

        /** @var User $user */
        $user = $request->user();
        $registration = $registrar->register($user, $session);

        if ($registration->status === RegistrationStatus::Reserved) {
            return to_route('student.sessions.payment', $session)->with('status', 'Votre place est réservée. Payez le supplément pour la confirmer.');
        }

        return to_route('student.sessions.show', $session)->with('status', match ($registration->status) {
            RegistrationStatus::Waitlisted => 'La session est complète : vous êtes sur la liste d\'attente. Si une place se libère, vous serez inscrit(e) automatiquement et prévenu(e).',
            default => 'C\'est noté : vous êtes inscrit(e) à la session.',
        });
    }

    public function cancel(Request $request, LiveSession $session, SessionRegistrar $registrar): RedirectResponse
    {
        Gate::authorize('view', $session);

        /** @var User $user */
        $user = $request->user();
        $registration = $session->registrations()->where('user_id', $user->id)->firstOrFail();
        $registrar->cancelByStudent($registration->setRelation('session', $session));

        return to_route('student.sessions.show', $session)->with('status', 'Votre inscription est annulée.');
    }

    public function payment(Request $request, LiveSession $session): View|RedirectResponse
    {
        Gate::authorize('register', $session);

        /** @var User $user */
        $user = $request->user();
        $registration = $session->registrations()->where('user_id', $user->id)->first();

        if ($registration === null || ! $registration->awaitsPayment()) {
            return to_route('student.sessions.show', $session);
        }

        return view('site.student.sessions.payment', [
            'session' => $session->load('formation'),
            'formation' => $session->formation,
            'registration' => $registration,
            'unverifiedPurchase' => $user->purchases()->awaitingVerification()->with(['formation', 'liveSession'])->latest()->first(),
            'countries' => PaymentMethods::countries(),
            'methods' => PaymentMethods::enabled()->map(fn (PaymentMethod $method): array => $method->toArray())->values()->all(),
            'needsVerification' => (int) $session->supplement_amount >= PaymentMethods::verificationThreshold(),
        ]);
    }

    public function pay(StorePaymentProofRequest $request, LiveSession $session, SubmitManualPayment $submit): RedirectResponse
    {
        Gate::authorize('register', $session);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $proof */
        $proof = $request->file('proof');

        $purchase = $submit->handle($user, $session->formation, [
            'payment_method' => $request->string('payment_method')->toString(),
            'payer_phone' => $request->string('payer_phone')->toString(),
            'payer_name' => $request->string('payer_name')->toString(),
            'transaction_id' => $request->string('transaction_id')->toString(),
        ], $proof, $session);

        return to_route('student.purchases.show', $purchase)->with('status', $purchase->status === PurchaseStatus::Provisional
            ? 'Merci ! Votre preuve de paiement est bien reçue et votre place est confirmée.'
            : 'Merci ! Votre preuve de paiement est bien reçue : votre place reste réservée pendant la vérification.');
    }

    /**
     * « Ajouter à mon agenda » : fichier .ics.
     */
    public function calendar(LiveSession $session): Response
    {
        Gate::authorize('view', $session);

        return response(CalendarFile::for($session->load('formation')), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="session-'.Str::slug($session->formation->title).'-'.$session->starts_at->format('Y-m-d').'.ics"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * « Rejoindre la session » : ouvre Google Meet, seulement à partir de 15 minutes avant le début.
     */
    public function join(LiveSession $session): RedirectResponse
    {
        Gate::authorize('view', $session);

        if (! $session->canJoinNow()) {
            return to_route('student.sessions.show', $session)->with('status', $session->isFinished() || $session->isCancelled()
                ? 'Cette session est terminée.'
                : 'Le bouton « Rejoindre la session » s\'active '.config('live_sessions.join_window_minutes').' minutes avant le début.');
        }

        return redirect()->away((string) $session->meet_url)->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Sessions à venir des formations de l'élève (toutes pour l'équipe).
     *
     * @return Collection<int, LiveSession>
     */
    public static function upcomingFor(User $user): Collection
    {
        return LiveSession::query()
            ->upcoming()
            ->when(! $user->isStaff(), fn ($query) => $query->whereIn('formation_id', $user->enrollments()->select('formation_id')))
            ->with('formation')
            ->get();
    }
}
