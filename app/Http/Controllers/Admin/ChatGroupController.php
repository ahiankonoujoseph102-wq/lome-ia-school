<?php

namespace App\Http\Controllers\Admin;

use App\Chat\ChatRealtime;
use App\Enums\ReportStatus;
use App\Events\Chat\ChatGroupChanged;
use App\Http\Controllers\Controller;
use App\Models\ChatEmailAnnouncement;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\ChatMessageReport;
use App\Models\Formation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin « Groupes de discussion » : activité, espace disque des fichiers, règles des groupes.
 */
class ChatGroupController extends Controller
{
    public function index(): View
    {
        // Un groupe pour chaque formation, même ajoutée avant l'étape 6.
        Formation::query()->doesntHave('chatGroup')->get()->each(fn (Formation $formation) => ChatGroup::forFormation($formation));

        $groups = ChatGroup::query()
            ->with('formation')
            ->withCount([
                'members as active_members_count' => fn ($query) => $query->whereNull('removed_at'),
                'messages as messages_count' => fn ($query) => $query->whereNull('deleted_at'),
                'messages as recent_messages_count' => fn ($query) => $query->whereNull('deleted_at')->where('created_at', '>=', now()->subDays(7)),
            ])
            ->get()
            ->sortByDesc(fn (ChatGroup $group): int => $group->last_message_at?->getTimestamp() ?? 0)
            ->values();

        $diskByGroup = ChatMessage::query()
            ->whereNull('deleted_at')
            ->whereNotNull('attachment_path')
            ->groupBy('chat_group_id')
            ->selectRaw('chat_group_id, SUM(attachment_size) as bytes, COUNT(*) as files')
            ->get()
            ->keyBy('chat_group_id');

        return view('site.admin.chat.index', [
            'groups' => $groups,
            'diskByGroup' => $diskByGroup,
            'totalBytes' => (int) $diskByGroup->sum('bytes'),
            'totalFiles' => (int) $diskByGroup->sum('files'),
            // Même compteur que « Messages signalés » : un message signalé plusieurs fois compte une fois.
            // Annonces envoyées par e-mail : combien sont parties, en échec ou encore en file d'attente.
            'emailAnnouncements' => ChatEmailAnnouncement::query()->with(['group.formation', 'author', 'message'])->latest('id')->limit(10)->get(),
            'openReports' => ChatMessageReport::query()->where('status', ReportStatus::Open->value)->distinct()->count('chat_message_id'),
        ]);
    }

    public function edit(ChatGroup $group): View
    {
        return view('site.admin.chat.edit', ['group' => $group->load('formation')]);
    }

    /**
     * Règles du groupe (affichées à l'entrée), éventuellement appliquées à tous les groupes.
     */
    public function updateRules(Request $request, ChatGroup $group, ChatRealtime $realtime): RedirectResponse
    {
        $data = $request->validate([
            'rules' => ['nullable', 'string', 'max:5000'],
            'apply_to_all' => ['nullable', 'boolean'],
        ], ['rules.max' => 'Règles trop longues : 5 000 caractères au maximum.']);

        $rules = filled($data['rules'] ?? null) ? trim((string) $data['rules']) : null;

        if ($request->boolean('apply_to_all')) {
            ChatGroup::query()->get()->each(fn (ChatGroup $target) => $this->saveRules($target, $rules, $realtime));

            return redirect()->route('admin.chat.index')->with('status', 'Règles enregistrées pour tous les groupes. Chaque membre les verra à sa prochaine visite.');
        }

        $this->saveRules($group, $rules, $realtime);

        return redirect()->route('admin.chat.index')->with('status', 'Règles enregistrées. Chaque membre les verra à sa prochaine visite.');
    }

    private function saveRules(ChatGroup $group, ?string $rules, ChatRealtime $realtime): void
    {
        $group->forceFill(['rules' => $rules, 'rules_updated_at' => now()])->save();
        $realtime->notify($group, ChatGroupChanged::GROUP_UPDATED);
    }

    /**
     * Mode annonces depuis l'écran admin.
     */
    public function toggleAnnouncements(ChatGroup $group, ChatRealtime $realtime): RedirectResponse
    {
        $enabled = ! $group->announcements_only;
        $group->forceFill(['announcements_only' => $enabled])->save();
        $realtime->notify($group, ChatGroupChanged::GROUP_UPDATED);

        return back()->with('status', $enabled ? 'Mode annonces activé.' : 'Mode annonces désactivé.');
    }
}
