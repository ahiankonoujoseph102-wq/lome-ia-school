<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Affiliates\AffiliateAccount;
use App\Affiliates\AffiliateAlerts;
use App\Affiliates\AffiliateManager;
use App\Affiliates\WithdrawalManager;
use App\Enums\AdminAction;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateWithdrawal;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin « Affiliation » (admin uniquement) : retraits à payer et liste des affiliés.
 */
class AffiliateController extends Controller
{
    public function __construct(private AffiliateAccount $account, private AffiliateAlerts $alerts) {}

    public function withdrawals(): View
    {
        $open = AffiliateWithdrawal::query()
            ->where('status', WithdrawalStatus::Requested)
            ->with('affiliate.user')
            ->oldest()
            ->get();

        $recent = AffiliateWithdrawal::query()
            ->whereIn('status', [WithdrawalStatus::Paid, WithdrawalStatus::Rejected])
            ->with('affiliate.user')
            ->latest('processed_at')
            ->limit(15)
            ->get();

        return view('site.admin.affiliates.withdrawals', [
            'withdrawals' => $open,
            'summaries' => $open->mapWithKeys(fn (AffiliateWithdrawal $withdrawal): array => [$withdrawal->id => $this->account->summary($withdrawal->affiliate)]),
            'alerts' => $open->mapWithKeys(fn (AffiliateWithdrawal $withdrawal): array => [$withdrawal->id => $this->alerts->forAffiliate($withdrawal->affiliate)]),
            'recent' => $recent,
        ]);
    }

    public function pay(Request $request, AffiliateWithdrawal $withdrawal, WithdrawalManager $manager): RedirectResponse
    {
        $validated = $request->validateWithBag('pay'.$withdrawal->id, [
            'fee' => ['required', 'integer', 'min:0'],
            'transaction_id' => ['required', 'string', 'min:3', 'max:100'],
        ], [
            'fee.required' => 'Indiquez les frais d\'envoi réels (0 s\'il n\'y en a pas).',
            'transaction_id.required' => 'Indiquez l\'identifiant de la transaction Mobile Money.',
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $manager->markPaid($withdrawal, $admin, (int) $validated['fee'], (string) $validated['transaction_id']);
        ActivityLog::record(AdminAction::WithdrawalPaid, 'Retrait payé : '.Money::fcfa($withdrawal->amount).' à '.$withdrawal->affiliate->user->name.' (frais '.Money::fcfa((int) $validated['fee']).', transaction '.$validated['transaction_id'].').', $withdrawal);

        return to_route('admin.affiliates.withdrawals')->with('status', 'Retrait marqué comme payé. L\'affilié a été prévenu par e-mail.');
    }

    public function reject(Request $request, AffiliateWithdrawal $withdrawal, WithdrawalManager $manager): RedirectResponse
    {
        $validated = $request->validateWithBag('reject'.$withdrawal->id, [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Indiquez le motif du refus : il sera envoyé à l\'affilié.',
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $manager->reject($withdrawal, $admin, (string) $validated['reason']);
        ActivityLog::record(AdminAction::WithdrawalRejected, 'Retrait refusé : '.Money::fcfa($withdrawal->amount).' pour '.$withdrawal->affiliate->user->name.'. Motif : '.$validated['reason'], $withdrawal);

        return to_route('admin.affiliates.withdrawals')->with('status', 'Retrait refusé : le montant est revenu dans le solde de l\'affilié, qui a été prévenu par e-mail.');
    }

    public function index(): View
    {
        $affiliates = Affiliate::query()->with('user')->latest()->paginate(30);

        return view('site.admin.affiliates.index', [
            'affiliates' => $affiliates,
            'summaries' => $affiliates->getCollection()->mapWithKeys(fn (Affiliate $affiliate): array => [$affiliate->id => $this->account->summary($affiliate)]),
            'alerts' => $affiliates->getCollection()->mapWithKeys(fn (Affiliate $affiliate): array => [$affiliate->id => $this->alerts->forAffiliate($affiliate)]),
        ]);
    }

    public function suspend(Request $request, Affiliate $affiliate, AffiliateManager $manager): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $manager->suspend($affiliate, $validated['reason'] ?? null);
        ActivityLog::record(AdminAction::AffiliateSuspended, 'Affilié suspendu : '.$affiliate->user->name.(filled($validated['reason'] ?? null) ? '. Motif : '.$validated['reason'] : '.'), $affiliate);

        return back()->with('status', 'Affilié suspendu : ses liens ne sont plus actifs, aucune nouvelle commission et aucun retrait.');
    }

    public function unsuspend(Affiliate $affiliate, AffiliateManager $manager): RedirectResponse
    {
        $manager->unsuspend($affiliate);
        ActivityLog::record(AdminAction::AffiliateUnsuspended, 'Affilié réactivé : '.$affiliate->user->name.'.', $affiliate);

        return back()->with('status', 'Affilié réactivé.');
    }
}
