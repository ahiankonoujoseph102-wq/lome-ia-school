<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ManualAccess;
use App\Affiliates\AffiliateAccount;
use App\Courses\CourseProgress;
use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Models\Consultation;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * « Élèves » (admin uniquement) : liste avec recherche et filtres, fiche complète, accès manuel.
 * Le blocage et le déblocage passent par Admin\PaymentController (routes admin.users.block / unblock).
 */
class StudentController extends Controller
{
    /**
     * @var array<string, string>
     */
    public const array FILTERS = [
        '' => 'Tous les comptes',
        'acheteurs' => 'Avec au moins un achat',
        'sans-achat' => 'Sans achat',
        'bloques' => 'Comptes bloqués',
        'affilies' => 'Affiliés',
        'annonces' => 'Acceptent les annonces',
        'equipe' => 'Équipe (admin, modérateurs)',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'filtre' => ['nullable', Rule::in(array_keys(self::FILTERS))],
            'formation' => ['nullable', 'integer'],
        ]);

        $students = self::search($filters)
            ->withCount(['enrollments', 'purchases as validated_purchases_count' => fn ($query) => $query->where('status', PurchaseStatus::Validated)])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('site.admin.students.index', [
            'students' => $students,
            'filters' => $filters,
            'formations' => Formation::query()->orderBy('sort_order')->pluck('title', 'id')->all(),
            'total' => User::query()->count(),
        ]);
    }

    /**
     * Recherche partagée avec l'export CSV des élèves.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    public static function search(array $filters): Builder
    {
        $query = User::query();
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $digits = preg_replace('/\D+/', '', $search) ?? '';
            $query->where(function (Builder $query) use ($search, $digits): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%');

                if (strlen($digits) >= 4) {
                    $query->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        }

        match ($filters['filtre'] ?? '') {
            'acheteurs' => $query->whereHas('purchases', fn ($purchases) => $purchases->whereIn('status', PurchaseStatus::accessGranting())),
            'sans-achat' => $query->whereDoesntHave('purchases', fn ($purchases) => $purchases->whereIn('status', PurchaseStatus::accessGranting())),
            'bloques' => $query->whereNotNull('blocked_at'),
            'affilies' => $query->whereHas('affiliate'),
            'annonces' => $query->where('wants_newsletter', true),
            'equipe' => $query->whereIn('role', [UserRole::Admin, UserRole::Moderator]),
            default => null,
        };

        if (! empty($filters['formation'])) {
            $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('formation_id', (int) $filters['formation']));
        }

        return $query;
    }

    public function show(User $user, CourseProgress $progress, AffiliateAccount $affiliates): View
    {
        $user->load([
            'purchases' => fn ($query) => $query->with(['formation', 'liveSession', 'verifier', 'promoCode'])->latest(),
            'enrollments.formation',
            'chatMemberships.group.formation',
            'liveSessionRegistrations' => fn ($query) => $query->with('session.formation')->latest(),
            'affiliate',
            'referrer.user',
        ]);

        $consultations = Consultation::query()->where('user_id', $user->id)->with('formation')->latest('starts_at')->get();

        return view('site.admin.students.show', [
            'student' => $user,
            'progress' => $progress->forEnrollments($user, $user->enrollments),
            'consultations' => $consultations,
            'affiliateSummary' => $user->affiliate ? $affiliates->summary($user->affiliate) : null,
            'history' => $this->history($user, $consultations),
            'availableFormations' => Formation::query()
                ->whereNotIn('id', $user->enrollments->pluck('formation_id'))
                ->orderBy('sort_order')
                ->get(['id', 'title', 'price']),
            'manualMethods' => PaymentGateway::manualMethods(),
        ]);
    }

    public function grantAccess(Request $request, User $user, ManualAccess $access): RedirectResponse
    {
        $validated = $request->validateWithBag('manualAccess', [
            'formation_id' => ['required', 'integer', 'exists:formations,id'],
            'amount' => ['required', 'integer', 'min:0', 'max:100000000'],
            'method' => ['required', Rule::in(array_keys(PaymentGateway::manualMethods()))],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'formation_id.required' => 'Choisissez la formation.',
            'amount.required' => 'Indiquez le montant reçu (0 si l\'accès est offert).',
            'reason.required' => 'Indiquez le motif : il est enregistré dans le journal d\'activité.',
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $formation = Formation::query()->findOrFail((int) $validated['formation_id']);

        try {
            $access->grant($user, $formation, (int) $validated['amount'], (string) $validated['method'], (string) $validated['reason'], $admin);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('manualAccess');
        }

        return back()->with('status', 'Accès ouvert à « '.$formation->title.' » pour '.$user->name.'. L\'élève a reçu son reçu par e-mail et a rejoint le groupe de la formation.');
    }

    /**
     * Historique du compte : inscription, achats, accès, consultations, actions de l'équipe.
     *
     * @param  Collection<int, Consultation>  $consultations
     * @return Collection<int, array{at: Carbon, icon: string, text: string}>
     */
    private function history(User $user, Collection $consultations): Collection
    {
        $events = collect([['at' => $user->created_at, 'icon' => 'user-plus', 'text' => 'Création du compte'.($user->referrer ? ' (venu par le lien d\'affiliation de '.$user->referrer->user->name.')' : '')]]);

        foreach ($user->purchases as $purchase) {
            $events->push(['at' => $purchase->created_at, 'icon' => 'banknotes', 'text' => ($purchase->isManual() ? 'Accès manuel : ' : 'Paiement envoyé : ').$purchase->item_label.' ('.$purchase->formatted_amount.')']);

            if ($purchase->verified_at && ! $purchase->isManual()) {
                $events->push(['at' => $purchase->verified_at, 'icon' => 'check-circle', 'text' => $purchase->status->label().' : '.$purchase->item_label.($purchase->verifier ? ' (par '.$purchase->verifier->name.')' : '')]);
            }
        }

        foreach ($user->enrollments as $enrollment) {
            $events->push(['at' => $enrollment->granted_at ?? $enrollment->created_at, 'icon' => 'academic-cap', 'text' => 'Accès ouvert : '.$enrollment->formation->title]);
        }

        foreach ($consultations as $consultation) {
            $events->push(['at' => $consultation->created_at, 'icon' => 'video-camera', 'text' => 'Consultation gratuite réservée pour le '.$consultation->starts_at->translatedFormat('j F Y à H\hi')]);
        }

        AdminActivity::query()
            ->with('user')
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)
            ->get()
            ->each(fn (AdminActivity $activity) => $events->push(['at' => $activity->created_at, 'icon' => 'shield-check', 'text' => $activity->action->label().' par '.($activity->user->name ?? 'le système').($activity->description ? ' : '.$activity->description : '')]));

        return $events->filter(fn (array $event): bool => $event['at'] !== null)->sortByDesc('at')->values();
    }
}
