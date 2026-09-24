<?php

namespace App\Http\Controllers\Student;

use App\Affiliates\AffiliateAccount;
use App\Affiliates\AffiliateManager;
use App\Affiliates\WithdrawalManager;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Formation;
use App\Models\User;
use App\Payments\PaymentMethod;
use App\Payments\PaymentMethods;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Espace affilié de l'élève : activation, liens, statistiques, commissions, coordonnées et retraits.
 * Aucune information sur les acheteurs n'est jamais affichée.
 */
class AffiliateController extends Controller
{
    public function __construct(private AffiliateAccount $account) {}

    public function index(Request $request): View
    {
        $affiliate = $this->affiliate($request);

        if ($affiliate === null) {
            return view('site.student.affiliate.activate');
        }

        return view('site.student.affiliate.index', [
            'affiliate' => $affiliate,
            'summary' => $this->account->summary($affiliate),
            'formations' => Formation::query()->published()->orderBy('title')->get(['id', 'slug', 'title']),
            'commissions' => $affiliate->commissions()->with('purchase.formation')->latest()->limit(5)->get(),
        ]);
    }

    public function activate(Request $request, AffiliateManager $manager): RedirectResponse
    {
        $request->validate(['terms' => ['accepted']], ['terms.accepted' => 'Cochez la case pour accepter les conditions du programme d\'affiliation.']);

        /** @var User $user */
        $user = $request->user();
        $manager->activate($user);

        return to_route('student.affiliate.index')->with('status', 'Votre compte affilié est activé. Voici vos liens à partager.');
    }

    public function commissions(Request $request): View|RedirectResponse
    {
        $affiliate = $this->affiliate($request);

        if ($affiliate === null) {
            return to_route('student.affiliate.index');
        }

        return view('site.student.affiliate.commissions', [
            'affiliate' => $affiliate,
            'commissions' => $affiliate->commissions()->with('purchase.formation')->latest()->paginate(20),
        ]);
    }

    public function withdrawals(Request $request): View|RedirectResponse
    {
        $affiliate = $this->affiliate($request);

        if ($affiliate === null) {
            return to_route('student.affiliate.index');
        }

        return view('site.student.affiliate.withdrawals', [
            'affiliate' => $affiliate,
            'summary' => $this->account->summary($affiliate),
            'hasOpenWithdrawal' => $this->account->hasOpenWithdrawal($affiliate),
            'withdrawals' => $affiliate->withdrawals()->latest()->get(),
            'countries' => PaymentMethods::countries(),
            'methods' => PaymentMethods::enabled()->map(fn (PaymentMethod $method): array => ['key' => $method->key, 'country' => $method->country, 'name' => $method->name])->values(),
        ]);
    }

    public function updatePayout(Request $request, AffiliateManager $manager): RedirectResponse
    {
        $affiliate = $this->affiliate($request);
        abort_if($affiliate === null, 404);

        $countries = array_keys(PaymentMethods::countries());
        $validated = $request->validate([
            'payout_country' => ['required', Rule::in($countries)],
            'payout_method' => ['required', 'string'],
            'payout_phone' => ['required', 'string', 'max:30'],
            'payout_name' => ['required', 'string', 'min:3', 'max:100'],
        ], [
            'payout_country.required' => 'Choisissez le pays de votre compte Mobile Money.',
            'payout_method.required' => 'Choisissez votre opérateur Mobile Money.',
            'payout_phone.required' => 'Indiquez le numéro Mobile Money qui recevra vos retraits.',
            'payout_name.required' => 'Indiquez le nom du titulaire du compte Mobile Money.',
        ]);

        $method = PaymentMethods::findEnabled($validated['payout_method']);

        if ($method === null || $method->country !== $validated['payout_country']) {
            throw ValidationException::withMessages(['payout_method' => 'Choisissez un opérateur disponible dans ce pays.']);
        }

        $phone = PhoneNumber::normalize($validated['payout_phone'], (string) config("site.payments.countries.{$method->country}.dial_code", PhoneNumber::DEFAULT_DIAL_CODE));

        if ($phone === null) {
            throw ValidationException::withMessages(['payout_phone' => 'Ce numéro Mobile Money n\'est pas valide pour ce pays.']);
        }

        $changed = $manager->updatePayout($affiliate, [
            'payout_country' => $method->country,
            'payout_method' => $method->key,
            'payout_phone' => $phone,
            'payout_name' => trim((string) $validated['payout_name']),
        ]);

        return to_route('student.affiliate.withdrawals')->with('status', $changed ? 'Vos coordonnées de retrait sont enregistrées.' : 'Aucun changement dans vos coordonnées de retrait.');
    }

    public function requestWithdrawal(Request $request, WithdrawalManager $withdrawals): RedirectResponse
    {
        $affiliate = $this->affiliate($request);
        abort_if($affiliate === null, 404);

        $withdrawal = $withdrawals->request($affiliate);

        return to_route('student.affiliate.withdrawals')->with('status', 'Votre demande de retrait de '.Money::fcfa($withdrawal->amount).' est envoyée. Nous vous prévenons par e-mail dès qu\'elle est payée.');
    }

    private function affiliate(Request $request): ?Affiliate
    {
        /** @var User $user */
        $user = $request->user();

        return $user->affiliate;
    }
}
