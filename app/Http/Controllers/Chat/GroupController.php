<?php

namespace App\Http\Controllers\Chat;

use App\Chat\ChatRealtime;
use App\Chat\ChatViewer;
use App\Chat\ConversationLoader;
use App\Chat\GroupMembership;
use App\Chat\MessageGrouping;
use App\Chat\UnreadCounter;
use App\Enums\ChatRemovalReason;
use App\Http\Controllers\Chat\Concerns\ResolvesChatMember;
use App\Http\Controllers\Controller;
use App\Models\ChatGroup;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\Formation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Groupes de discussion : liste, conversation, mises à jour (temps réel ou régulières), lecture.
 */
class GroupController extends Controller
{
    use ResolvesChatMember;

    public function __construct(private ConversationLoader $loader) {}

    /**
     * « Groupes » dans l'espace élève : ses groupes (tous pour l'équipe), avec les non-lus.
     */
    public function index(Request $request, GroupMembership $membership, UnreadCounter $unread): View
    {
        /** @var User $user */
        $user = $request->user();
        $membership->syncStaff($user);

        $members = ChatGroupMember::query()
            ->where('user_id', $user->id)
            ->whereNull('removed_at')
            ->with('group.formation')
            ->get()
            ->sortByDesc(fn (ChatGroupMember $member): int => ($member->group->last_message_at ?? $member->joined_at)->getTimestamp())
            ->values();

        $lastMessages = ChatMessage::query()
            ->whereIn('id', ChatMessage::query()
                ->selectRaw('MAX(id)')
                ->whereIn('chat_group_id', $members->pluck('chat_group_id'))
                ->groupBy('chat_group_id'))
            ->with('author')
            ->get()
            ->keyBy('chat_group_id');

        return view('site.chat.index', [
            'members' => $members,
            'lastMessages' => $lastMessages,
            'unread' => $unread->forUser($user),
        ]);
    }

    /**
     * Conversation d'un groupe, ouverte au premier message non lu.
     */
    public function show(Request $request, Formation $formation): View|Response
    {
        $viewer = $this->viewerOrRemovedPage($request, $formation);

        if ($viewer instanceof Response) {
            return $viewer;
        }

        $group = $viewer->group;
        $member = $viewer->member;

        $unreadQuery = $group->unreadMessagesFor($member);
        $unreadCount = (clone $unreadQuery)->count();
        $firstUnreadId = $unreadCount > 0 ? (int) (clone $unreadQuery)->min('id') : null;

        $count = min(max((int) config('chat.page_size'), $unreadCount + 5), (int) config('chat.max_initial_messages'));
        $messages = $this->loader->latest($group, $count);

        $member->forceFill([
            'last_read_message_id' => max((int) $member->last_read_message_id, (int) $messages->last()?->id),
            'last_seen_at' => now(),
        ])->save();

        return response()
            ->view('site.chat.show', [
                'formation' => $formation,
                'viewer' => $viewer,
                'group' => $group,
                'messages' => $messages,
                'hasOlder' => $this->loader->hasOlder($group, $messages->first()?->id),
                'firstUnreadId' => $firstUnreadId,
                'unreadCount' => $unreadCount,
                'pinned' => $this->pinned($group),
                'membersCount' => $group->activeMembers()->count(),
                'realtime' => ChatRealtime::clientConfig(),
                'showRules' => $member->mustSeeRules(),
                'serverTime' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Mises à jour (nouveaux messages, messages modifiés, épinglés, droits d'écriture)
     * ou historique en remontant (?before=).
     */
    public function messages(Request $request, Formation $formation): JsonResponse
    {
        $viewer = $this->viewer($request, $formation);
        $group = $viewer->group;

        if ($request->filled('before')) {
            $messages = $this->loader->before($group, $request->integer('before'));
            $firstId = $messages->first()?->id;

            return $this->json([
                'html' => $this->renderList($viewer, $messages, $firstId === null ? null : $this->loader->previousOf($group, $firstId)),
                'first_id' => $firstId,
                'has_more' => $this->loader->hasOlder($group, $firstId),
            ]);
        }

        $afterId = $request->integer('after');
        $since = $this->since($request);
        $messages = $this->loader->after($group, $afterId);
        $firstId = $messages->first()?->id;
        $lastId = $messages->last()?->id;

        $updated = $this->loader->query($group)
            ->where('id', '<=', $afterId)
            ->where('updated_at', '>=', $since)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (ChatMessage $message): array => [
                'id' => $message->id,
                'html' => view('site.chat.partials.message', [
                    'message' => $message,
                    'viewer' => $viewer,
                    'grouped' => MessageGrouping::continues($message, $this->loader->previousOf($group, $message->id)),
                ])->render(),
            ]);

        // Groupe affiché à l'écran : les nouveaux messages sont lus.
        $viewer->member->forceFill(array_filter([
            'last_seen_at' => now(),
            'last_read_message_id' => $request->boolean('visible') && $lastId !== null
                ? max((int) $viewer->member->last_read_message_id, $lastId)
                : null,
        ], fn (mixed $value): bool => $value !== null))->save();

        return $this->json([
            'html' => $firstId === null ? '' : $this->renderList($viewer, $messages, $this->loader->previousOf($group, $firstId)),
            'last_id' => $lastId ?? $afterId,
            'count' => $messages->count(),
            'updates' => $updated->values(),
            'pinned_html' => view('site.chat.partials.pinned', ['pinned' => $this->pinned($group), 'viewer' => $viewer])->render(),
            'can_post' => $viewer->canPost(),
            'blocked_reason' => $viewer->postBlockedReason(),
            'announcements_only' => $group->announcements_only,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Messages lus jusqu'à ce message (groupe affiché à l'écran).
     */
    public function read(Request $request, Formation $formation): JsonResponse
    {
        $viewer = $this->viewer($request, $formation);
        $lastId = min($request->integer('last_id'), $viewer->group->latestMessageId());

        $viewer->member->forceFill([
            'last_read_message_id' => max((int) $viewer->member->last_read_message_id, $lastId),
            'last_seen_at' => now(),
        ])->save();

        return $this->json(['ok' => true]);
    }

    /**
     * Règles du groupe lues (« J'ai compris »).
     */
    public function rulesSeen(Request $request, Formation $formation): JsonResponse|RedirectResponse
    {
        $viewer = $this->viewer($request, $formation);
        $viewer->member->forceFill(['rules_seen_at' => now()])->save();

        return $request->expectsJson()
            ? $this->json(['ok' => true])
            : redirect()->route('chat.show', $formation);
    }

    /**
     * Couper ou réactiver les notifications de ce groupe.
     */
    public function toggleNotifications(Request $request, Formation $formation): RedirectResponse|JsonResponse
    {
        $viewer = $this->viewer($request, $formation);
        $muted = ! $viewer->member->notifications_muted;
        $viewer->member->forceFill(['notifications_muted' => $muted])->save();

        $status = $muted ? 'Notifications coupées pour ce groupe.' : 'Notifications réactivées pour ce groupe.';

        return $request->expectsJson()
            ? $this->json(['muted' => $muted, 'status' => $status])
            : back()->with('status', $status);
    }

    /**
     * Membres du groupe : nom, photo et badge uniquement (jamais le numéro ni l'e-mail).
     */
    public function members(Request $request, Formation $formation): View
    {
        $viewer = $this->viewer($request, $formation);

        $members = $viewer->group->members()
            ->with('user')
            ->get()
            ->sortBy(fn (ChatGroupMember $member): string => ($member->user->isAdmin() ? '0' : ($member->user->isStaff() ? '1' : '2')).mb_strtolower($member->user->name));

        return view('site.chat.members', [
            'formation' => $formation,
            'viewer' => $viewer,
            'active' => $members->filter(fn (ChatGroupMember $member): bool => $member->isActive())->values(),
            'excluded' => $viewer->canModerate()
                ? $members->filter(fn (ChatGroupMember $member): bool => $member->isExcluded())->values()
                : collect(),
            'muteDurations' => (array) config('chat.mute_durations'),
        ]);
    }

    /**
     * @param  Collection<int, ChatMessage>  $messages
     */
    private function renderList(ChatViewer $viewer, Collection $messages, ?ChatMessage $previous): string
    {
        return view('site.chat.partials.message-list', [
            'messages' => $messages,
            'previous' => $previous,
            'viewer' => $viewer,
            'firstUnreadId' => null,
            'unreadCount' => 0,
        ])->render();
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    private function pinned(ChatGroup $group): Collection
    {
        return $group->pinnedMessages()->with('author')->limit((int) config('chat.max_pinned'))->get();
    }

    private function since(Request $request): CarbonInterface
    {
        try {
            $since = Carbon::parse((string) $request->query('since', ''));
        } catch (\Throwable) {
            return now();
        }

        // Petite marge pour ne rater aucune modification faite pendant la requête précédente.
        return $since->subSeconds(2)->max(now()->subDay());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    /**
     * Élève retiré ou exclu : page d'explication plutôt qu'une erreur brute.
     */
    private function viewerOrRemovedPage(Request $request, Formation $formation): ChatViewer|Response
    {
        /** @var User $user */
        $user = $request->user();
        $group = ChatGroup::forFormation($formation);
        $member = app(GroupMembership::class)->membershipFor($user, $group);

        if ($member !== null) {
            return $this->viewer($request, $formation);
        }

        $removed = $group->members()->where('user_id', $user->id)->whereNotNull('removed_at')->first();

        return response()->view('site.chat.no-access', [
            'formation' => $formation,
            'excluded' => $removed?->removal_reason === ChatRemovalReason::Excluded,
        ], 403);
    }
}
