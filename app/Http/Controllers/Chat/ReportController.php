<?php

namespace App\Http\Controllers\Chat;

use App\Chat\ChatRealtime;
use App\Enums\ReportStatus;
use App\Events\Chat\ChatGroupChanged;
use App\Http\Controllers\Controller;
use App\Models\ChatMessageReport;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * « Messages signalés » : réservé à l'admin et aux modérateurs.
 */
class ReportController extends Controller
{
    public function index(): View
    {
        $open = ChatMessageReport::query()
            ->where('status', ReportStatus::Open->value)
            ->with(['message.author', 'message.group.formation', 'reporter'])
            ->latest()
            ->get()
            ->groupBy('chat_message_id');

        $handled = ChatMessageReport::query()
            ->where('status', '!=', ReportStatus::Open->value)
            ->with(['message.author', 'message.group.formation', 'reporter', 'handler'])
            ->latest('handled_at')
            ->limit(20)
            ->get();

        return view('site.chat.reports', ['open' => $open, 'handled' => $handled]);
    }

    /**
     * Traiter tous les signalements d'un message : supprimer le message, ou classer sans suite.
     */
    public function handle(Request $request, ChatMessageReport $report, ChatRealtime $realtime): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $action = $request->validate(['action' => ['required', Rule::in(['delete', 'dismiss'])]])['action'];
        $message = $report->message;

        if ($action === 'delete' && ! $message->isDeleted()) {
            $message->wipe($user);
            $realtime->notify($message->group, ChatGroupChanged::MESSAGE_UPDATED, $message->id);
        }

        $message->reports()->where('status', ReportStatus::Open->value)->update([
            'status' => $action === 'delete' ? ReportStatus::Resolved->value : ReportStatus::Dismissed->value,
            'handled_by' => $user->id,
            'handled_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', $action === 'delete' ? 'Message supprimé et signalement traité.' : 'Signalement classé sans suite.');
    }
}
