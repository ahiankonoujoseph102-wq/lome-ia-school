<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * « Demandes de devis » (admin uniquement) : demandes du formulaire de la page « Entreprises et réalisations sur
 * mesure ». À traiter / traitée ; effacées après 12 mois.
 */
class QuoteRequestController extends Controller
{
    private const array FILTERS = ['a-traiter' => QuoteRequest::PENDING, 'traitees' => QuoteRequest::DONE, 'toutes' => null];

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('filtre'), self::FILTERS) ? (string) $request->query('filtre') : 'a-traiter';
        $status = self::FILTERS[$filter];

        return view('site.admin.quotes.index', [
            'filter' => $filter,
            'counts' => [
                'a-traiter' => QuoteRequest::query()->where('status', QuoteRequest::PENDING)->count(),
                'traitees' => QuoteRequest::query()->where('status', QuoteRequest::DONE)->count(),
                'toutes' => QuoteRequest::query()->count(),
            ],
            'quotes' => QuoteRequest::query()
                ->with('handler:id,name')
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(20)->withQueryString(),
        ]);
    }

    public function toggle(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $done = $quote->isPending();
        $quote->forceFill([
            'status' => $done ? QuoteRequest::DONE : QuoteRequest::PENDING,
            'handled_at' => $done ? now() : null,
            'handled_by' => $done ? $request->user()?->id : null,
        ])->save();

        ActivityLog::record(
            $done ? AdminAction::QuoteHandled : AdminAction::QuoteReopened,
            'Demande de devis '.($done ? 'marquée comme traitée' : 'remise à traiter').' ('.$quote->name.').',
            $quote,
        );

        return back()->with('status', $done ? 'Demande de '.$quote->name.' marquée comme traitée.' : 'Demande de '.$quote->name.' remise à traiter.');
    }
}
