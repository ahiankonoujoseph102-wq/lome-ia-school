<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ActivityLog;
use App\Enums\AdminAction;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\WhatsApp\AgentSettings;
use App\WhatsApp\WhatsAppAgent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * « Conversations WhatsApp » (admin uniquement) : clients qui écrivent au numéro de l'API, réponses de l'agent,
 * clients qui attendent l'équipe. L'admin peut répondre (dans les 24 h qui suivent le dernier message du client,
 * règle de WhatsApp), marquer une demande comme traitée, arrêter ou relancer l'agent pour un client.
 */
class WhatsAppConversationController extends Controller
{
    /**
     * Filtres de la liste : « toutes » (par défaut), « a-traiter » (client qui attend l'équipe), « sans-reponse ».
     */
    private const array FILTERS = ['toutes', 'a-traiter', 'sans-reponse'];

    public function index(Request $request): View
    {
        $filter = in_array($request->query('filtre'), self::FILTERS, true) ? (string) $request->query('filtre') : 'toutes';

        $today = WhatsAppMessage::query()->where('created_at', '>=', now()->startOfDay());

        return view('site.admin.whatsapp.index', [
            'filter' => $filter,
            'counts' => [
                'toutes' => WhatsAppContact::query()->count(),
                'a-traiter' => WhatsAppContact::query()->whereNotNull('needs_human_at')->count(),
                'sans-reponse' => WhatsAppContact::query()->whereHas('messages', fn (Builder $query) => $query->where('unanswered', true))->count(),
            ],
            'stats' => [
                'received' => (clone $today)->where('sender', WhatsAppMessage::CLIENT)->count(),
                'agent' => (clone $today)->where('sender', WhatsAppMessage::AGENT)->count(),
                'team' => (clone $today)->where('sender', WhatsAppMessage::TEAM)->count(),
                'failed' => (clone $today)->where('status', 'failed')->count(),
            ],
            'contacts' => WhatsAppContact::query()
                ->when($filter === 'a-traiter', fn (Builder $query) => $query->whereNotNull('needs_human_at'))
                ->when($filter === 'sans-reponse', fn (Builder $query) => $query->whereHas('messages', fn (Builder $query) => $query->where('unanswered', true)))
                ->addSelect(['last_text' => WhatsAppMessage::query()->select('content')->whereColumn('whatsapp_contact_id', 'whatsapp_contacts.id')->latest('id')->limit(1)])
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->paginate(20)->withQueryString(),
            'enabled' => AgentSettings::enabled(),
            'retentionDays' => WhatsAppContact::RETENTION_DAYS,
        ]);
    }

    public function show(WhatsAppContact $contact): View
    {
        $contact->load(['messages.user:id,name']);

        return view('site.admin.whatsapp.show', [
            'contact' => $contact,
            'enabled' => AgentSettings::enabled(),
        ]);
    }

    /**
     * Réponse écrite par l'équipe : envoyée au client, l'agent se tait pour ce client (pause), la demande est traitée.
     */
    public function reply(Request $request, WhatsAppContact $contact, WhatsAppAgent $agent): RedirectResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:4000']], [
            'message.required' => 'Écrivez votre réponse.',
            'message.max' => 'La réponse est trop longue (4 000 caractères au maximum).',
        ]);

        if (! $contact->windowOpen()) {
            return back()->withInput()->withErrors(['message' => 'Plus de 24 heures depuis le dernier message du client : WhatsApp n\'autorise plus de réponse libre. Le client doit vous réécrire d\'abord.']);
        }

        $message = $agent->send($contact, trim((string) $validated['message']), WhatsAppMessage::TEAM, user: $request->user());

        if ($message->status === 'failed') {
            return back()->withInput()->withErrors(['message' => 'WhatsApp a refusé l\'envoi : '.$message->error]);
        }

        $contact->pauseAgent();
        $contact->forceFill(['needs_human_at' => null, 'needs_human_reason' => null])->save();

        ActivityLog::record(AdminAction::WhatsAppReplied, 'Réponse WhatsApp envoyée à '.$contact->displayName().'.', $contact);

        return back()->with('status', 'Réponse envoyée. L\'agent se tait pour ce client pendant '.AgentSettings::pauseHours().' h.');
    }

    /**
     * Demande de l'équipe traitée (ou remise à traiter).
     */
    public function handled(WhatsAppContact $contact): RedirectResponse
    {
        $done = $contact->needs_human_at !== null;
        $contact->forceFill([
            'needs_human_at' => $done ? null : now(),
            'needs_human_reason' => $done ? null : 'Remise à traiter par l\'équipe.',
        ])->save();

        ActivityLog::record(AdminAction::WhatsAppHandled, 'Conversation WhatsApp de '.$contact->displayName().' '.($done ? 'marquée comme traitée' : 'remise à traiter').'.', $contact);

        return back()->with('status', $done ? 'Conversation marquée comme traitée.' : 'Conversation remise à traiter.');
    }

    /**
     * Arrêter l'agent pour ce client (l'équipe répond seule), ou le relancer tout de suite.
     */
    public function agent(Request $request, WhatsAppContact $contact): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['stop', 'resume'])]])['action'];
        $stop = $action === 'stop';

        $contact->forceFill(['agent_stopped' => $stop, 'agent_paused_until' => null])->save();

        ActivityLog::record($stop ? AdminAction::WhatsAppAgentStopped : AdminAction::WhatsAppAgentResumed, 'Agent WhatsApp '.($stop ? 'arrêté' : 'relancé').' pour '.$contact->displayName().'.', $contact);

        return back()->with('status', $stop ? 'L\'agent ne répondra plus à ce client tant que vous ne le relancez pas.' : 'L\'agent répondra de nouveau au prochain message de ce client.');
    }
}
