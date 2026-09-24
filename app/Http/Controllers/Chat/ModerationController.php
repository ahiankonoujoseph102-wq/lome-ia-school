<?php

namespace App\Http\Controllers\Chat;

use App\Chat\ChatRealtime;
use App\Chat\ChatViewer;
use App\Chat\GroupMembership;
use App\Events\Chat\ChatGroupChanged;
use App\Http\Controllers\Chat\Concerns\ResolvesChatMember;
use App\Http\Controllers\Controller;
use App\Models\ChatGroupMember;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Outils de l'équipe (admin et modérateurs) : sourdine, exclusion, réintégration, mode annonces.
 */
class ModerationController extends Controller
{
    use ResolvesChatMember;

    public function __construct(
        private GroupMembership $membership,
        private ChatRealtime $realtime,
    ) {}

    public function mute(Request $request, Formation $formation, User $user): JsonResponse|RedirectResponse
    {
        [$viewer, $member] = $this->target($request, $formation, $user);

        $hours = (int) $request->validate([
            'hours' => ['required', 'integer', Rule::in(array_keys((array) config('chat.mute_durations')))],
        ], ['hours.required' => 'Choisissez une durée.', 'hours.in' => 'Choisissez une durée.'])['hours'];

        $member->forceFill(['muted_until' => now()->addHours($hours), 'muted_by' => $viewer->user->id])->save();
        $this->realtime->notify($viewer->group, ChatGroupChanged::GROUP_UPDATED);

        return $this->done($request, $formation, $user->name.' est en sourdine pour '.config('chat.mute_durations.'.$hours).'.');
    }

    public function unmute(Request $request, Formation $formation, User $user): JsonResponse|RedirectResponse
    {
        [$viewer, $member] = $this->target($request, $formation, $user);

        $member->forceFill(['muted_until' => null, 'muted_by' => null])->save();
        $this->realtime->notify($viewer->group, ChatGroupChanged::GROUP_UPDATED);

        return $this->done($request, $formation, $user->name.' peut de nouveau écrire.');
    }

    public function exclude(Request $request, Formation $formation, User $user): JsonResponse|RedirectResponse
    {
        [$viewer, $member] = $this->target($request, $formation, $user);

        $this->membership->exclude($member);
        $this->realtime->notify($viewer->group, ChatGroupChanged::GROUP_UPDATED);

        return $this->done($request, $formation, $user->name.' a été exclu(e) du groupe.');
    }

    public function reinstate(Request $request, Formation $formation, User $user): JsonResponse|RedirectResponse
    {
        [, $member] = $this->target($request, $formation, $user, activeOnly: false);

        $status = $this->membership->reinstate($member)
            ? $user->name.' a été réintégré(e) dans le groupe.'
            : $user->name.' n\'a plus accès à la formation : réintégration impossible.';

        return $this->done($request, $formation, $status);
    }

    /**
     * Mode « annonces seulement » : seuls l'admin et les modérateurs écrivent.
     */
    public function toggleAnnouncements(Request $request, Formation $formation): JsonResponse|RedirectResponse
    {
        $viewer = $this->viewer($request, $formation);
        abort_unless($viewer->canModerate(), 403);

        $enabled = ! $viewer->group->announcements_only;
        $viewer->group->forceFill(['announcements_only' => $enabled])->save();
        $this->realtime->notify($viewer->group, ChatGroupChanged::GROUP_UPDATED);

        return $this->done($request, $formation, $enabled
            ? 'Mode annonces activé : seule l\'équipe peut écrire.'
            : 'Mode annonces désactivé : tous les membres peuvent écrire.');
    }

    /**
     * Membre visé (jamais un membre de l'équipe), par un membre de l'équipe.
     *
     * @return array{0: ChatViewer, 1: ChatGroupMember}
     */
    private function target(Request $request, Formation $formation, User $user, bool $activeOnly = true): array
    {
        $viewer = $this->viewer($request, $formation);
        abort_unless($viewer->canModerate(), 403);
        abort_if($user->isStaff(), 403, 'Un membre de l\'équipe ne peut pas être mis en sourdine ni exclu.');

        $query = $viewer->group->members()->where('user_id', $user->id);
        $member = ($activeOnly ? $query->whereNull('removed_at') : $query)->firstOrFail();
        $member->setRelation('group', $viewer->group);
        $member->setRelation('user', $user);

        return [$viewer, $member];
    }

    private function done(Request $request, Formation $formation, string $status): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['status' => $status])
            : back(fallback: route('chat.show', $formation))->with('status', $status);
    }
}
