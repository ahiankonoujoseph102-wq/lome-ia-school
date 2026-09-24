<?php

namespace App\Http\Controllers\Student;

use App\Contracts\PushSender;
use App\Http\Controllers\Controller;
use App\Models\ChatGroupMember;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Réglages des notifications des groupes : téléphone (Web Push), e-mail récapitulatif, groupes coupés.
 */
class NotificationController extends Controller
{
    public function edit(Request $request, PushSender $push): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('site.student.notifications', [
            'user' => $user,
            'pushAvailable' => $push->isConfigured(),
            'vapidPublicKey' => $push->isConfigured() ? (string) config('services.webpush.public_key') : null,
            'devicesCount' => $user->pushSubscriptions()->count(),
            'memberships' => ChatGroupMember::query()
                ->where('user_id', $user->id)
                ->whereNull('removed_at')
                ->with('group.formation')
                ->get()
                ->sortBy(fn (ChatGroupMember $member): string => $member->group->formation->title)
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'chat_push_enabled' => $request->boolean('chat_push_enabled'),
            'chat_digest_enabled' => $request->boolean('chat_digest_enabled'),
        ])->save();

        $muted = collect((array) $request->input('muted_groups', []))->map(fn (mixed $id): int => (int) $id);

        $user->chatMemberships()->whereNull('removed_at')->get()->each(
            fn (ChatGroupMember $member) => $member->forceFill(['notifications_muted' => $muted->contains($member->chat_group_id)])->save(),
        );

        return back()->with('status', 'Réglages des notifications enregistrés.');
    }

    /**
     * Enregistre l'appareil pour les notifications sur téléphone.
     */
    public function subscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $user->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ],
        );

        $user->forceFill(['chat_push_enabled' => true])->save();

        return response()->json(['status' => 'Notifications activées sur cet appareil.']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $endpoint = (string) $request->input('endpoint');

        $user->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))->delete();

        return response()->json(['status' => 'Notifications désactivées sur cet appareil.']);
    }
}
