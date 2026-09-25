<?php

namespace App\Http\Controllers\Admin;

use App\Admin\SalesQuery;
use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Formation;
use App\Models\PromoCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * « Toutes les ventes » (admin uniquement) : paiements Mobile Money et accès manuels, avec filtres et totaux.
 */
class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(SalesQuery::rules());

        $totals = SalesQuery::build($filters)
            ->selectRaw('status, count(*) as sales, sum(amount) as revenue')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->getRawOriginal('status') => ['sales' => (int) $row->getAttribute('sales'), 'revenue' => (int) $row->getAttribute('revenue')]]);

        return view('site.admin.sales', [
            'purchases' => SalesQuery::build($filters)->with(['user', 'formation', 'liveSession', 'promoCode'])->latest()->latest('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'totals' => $totals,
            'formations' => Formation::query()->orderBy('sort_order')->pluck('title', 'id')->all(),
            'statuses' => collect(PurchaseStatus::cases())->mapWithKeys(fn (PurchaseStatus $status): array => [$status->value => $status->label()])->all(),
            'gateways' => collect(PaymentGateway::cases())->mapWithKeys(fn (PaymentGateway $gateway): array => [$gateway->value => $gateway->label()])->all(),
            'codes' => PromoCode::query()->orderBy('code')->pluck('code', 'id')->all(),
        ]);
    }
}
