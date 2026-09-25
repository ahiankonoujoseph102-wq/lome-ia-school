<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Affiliates\AffiliateAlerts;
use App\Courses\CourseProgress;
use App\Enums\AdminAction;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PaymentAlerts;
use App\Services\PurchaseManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * « Paiements à vérifier » : validation ou refus des preuves de paiement Mobile Money.
 */
class PaymentController extends Controller
{
    public function __construct(private PurchaseManager $purchases) {}

    public function index(PaymentAlerts $alerts, CourseProgress $progress, AffiliateAlerts $affiliateAlerts): View
    {
        $pending = Purchase::query()
            ->awaitingVerification()
            ->with(['user', 'formation', 'affiliate.user'])
            ->latest()
            ->get();

        $recent = Purchase::query()
            ->whereIn('status', [PurchaseStatus::Validated, PurchaseStatus::Rejected])
            ->with(['user', 'formation'])
            ->latest('verified_at')
            ->limit(10)
            ->get();

        // Première leçon consultée (règle de remboursement : possible seulement si aucune leçon n'a été consultée).
        $startedAt = $pending->concat($recent)->mapWithKeys(fn (Purchase $purchase): array => [
            $purchase->id => $purchase->user ? $progress->startedAt($purchase->user, $purchase->formation) : null,
        ]);

        return view('site.admin.payments.index', [
            'purchases' => $pending,
            'alerts' => $pending->mapWithKeys(fn (Purchase $purchase): array => [$purchase->id => $alerts->for($purchase)]),
            'affiliateAlerts' => $pending->mapWithKeys(fn (Purchase $purchase): array => [$purchase->id => $affiliateAlerts->forPurchase($purchase)]),
            'recent' => $recent,
            'startedAt' => $startedAt,
        ]);
    }

    public function approve(Request $request, Purchase $purchase): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $this->purchases->validate($purchase, $admin);
        ActivityLog::record(AdminAction::PaymentValidated, 'Paiement validé : '.$purchase->item_label.' ('.$purchase->formatted_amount.') pour '.($purchase->user->name ?? 'un élève').'.', $purchase);

        return to_route('admin.payments.index')->with('status', 'Paiement validé pour '.($purchase->user->name ?? 'l\'élève').'. Le reçu lui a été envoyé.');
    }

    public function reject(Request $request, Purchase $purchase): RedirectResponse
    {
        $validated = $request->validateWithBag('reject'.$purchase->id, [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'block_user' => ['nullable', 'boolean'],
        ], [
            'reason.required' => 'Indiquez le motif du refus : il sera envoyé à l\'élève.',
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $block = $request->boolean('block_user');

        $this->purchases->reject($purchase, $admin, (string) $validated['reason'], $block);
        ActivityLog::record(AdminAction::PaymentRejected, 'Paiement refusé : '.$purchase->item_label.' ('.$purchase->formatted_amount.') pour '.($purchase->user->name ?? 'un élève').'. Motif : '.$validated['reason'].($block ? ' Compte bloqué.' : ''), $purchase);

        return to_route('admin.payments.index')->with('status', 'Paiement refusé : l\'accès a été retiré'.($block ? ' et le compte bloqué' : '').'. L\'élève a été prévenu par e-mail.');
    }

    public function block(User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 403);

        $this->purchases->block($user);
        ActivityLog::record(AdminAction::UserBlocked, 'Compte bloqué : '.$user->name.' ('.$user->email.').', $user);

        return back()->with('status', 'Le compte de '.$user->name.' est bloqué.');
    }

    public function unblock(User $user): RedirectResponse
    {
        $this->purchases->unblock($user);
        ActivityLog::record(AdminAction::UserUnblocked, 'Compte débloqué : '.$user->name.' ('.$user->email.').', $user);

        return back()->with('status', 'Le compte de '.$user->name.' est débloqué.');
    }
}
