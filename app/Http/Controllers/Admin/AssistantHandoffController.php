<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Models\AssistantConversation;
use App\Models\AssistantHandoff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * « Demandes transmises » (admin uniquement) : questions transmises à Coach Joseph par Ama, à la demande des
 * visiteurs (« Transmettre au fondateur »). À traiter / traitée ; effacées après 90 jours.
 */
class AssistantHandoffController extends Controller
{
    /**
     * Filtres de la liste : « a-traiter » (par défaut), « traitees », « toutes ».
     */
    private const array FILTERS = ['a-traiter' => AssistantHandoff::PENDING, 'traitees' => AssistantHandoff::DONE, 'toutes' => null];

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('filtre'), self::FILTERS) ? (string) $request->query('filtre') : 'a-traiter';
        $status = self::FILTERS[$filter];

        return view('site.admin.assistant.handoffs', [
            'filter' => $filter,
            'counts' => [
                'a-traiter' => AssistantHandoff::query()->where('status', AssistantHandoff::PENDING)->count(),
                'traitees' => AssistantHandoff::query()->where('status', AssistantHandoff::DONE)->count(),
                'toutes' => AssistantHandoff::query()->count(),
            ],
            'handoffs' => AssistantHandoff::query()
                ->with(['conversation:id,public_id', 'handler:id,name'])
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(20)->withQueryString(),
            'retentionDays' => AssistantConversation::RETENTION_DAYS,
        ]);
    }

    public function toggle(Request $request, AssistantHandoff $handoff): RedirectResponse
    {
        $done = $handoff->isPending();
        $handoff->forceFill([
            'status' => $done ? AssistantHandoff::DONE : AssistantHandoff::PENDING,
            'handled_at' => $done ? now() : null,
            'handled_by' => $done ? $request->user()?->id : null,
        ])->save();

        ActivityLog::record(
            $done ? AdminAction::HandoffHandled : AdminAction::HandoffReopened,
            'Demande transmise par Ama '.($done ? 'marquée comme traitée' : 'remise à traiter').' ('.$handoff->first_name.').',
            $handoff,
        );

        return back()->with('status', $done ? 'Demande de '.$handoff->first_name.' marquée comme traitée.' : 'Demande de '.$handoff->first_name.' remise à traiter.');
    }
}
