@php
    $placeholder = '__ID__';
    $config = [
        'groupId' => $group->id,
        'userId' => $viewer->user->id,
        'isStaff' => $viewer->isStaff(),
        'lastId' => (int) ($messages->last()?->id ?? 0),
        'firstId' => (int) ($messages->first()?->id ?? 0),
        'hasOlder' => $hasOlder,
        'since' => $serverTime,
        'canPost' => $viewer->canPost(),
        'blockedReason' => $viewer->postBlockedReason(),
        'announcementsOnly' => $group->announcements_only,
        'realtime' => $realtime,
        'pollInterval' => (int) config('chat.poll_interval') * 1000,
        'safetyPollInterval' => (int) config('chat.safety_poll_interval') * 1000,
        'showRules' => $showRules,
        'voiceMaxSeconds' => (int) config('chat.attachments.voice.max_seconds'),
        'maxBytes' => [
            'image' => (int) config('chat.attachments.image.max_kb') * 1024,
            'video' => (int) config('chat.attachments.video.max_kb') * 1024,
            'file' => (int) config('chat.attachments.file.max_kb') * 1024,
            'voice' => (int) config('chat.attachments.voice.max_kb') * 1024,
        ],
        'fileExtensions' => (array) config('chat.attachments.file.mimes'),
        'urls' => [
            'messages' => route('chat.messages.index', $formation),
            'store' => route('chat.messages.store', $formation),
            'read' => route('chat.read', $formation),
            'rulesSeen' => route('chat.rules.seen', $formation),
            'destroy' => route('chat.messages.destroy', [$formation, $placeholder]),
            'pin' => route('chat.messages.pin', [$formation, $placeholder]),
            'report' => route('chat.messages.report', [$formation, $placeholder]),
            'react' => route('chat.messages.react', [$formation, $placeholder]),
            'mute' => route('chat.members.mute', [$formation, $placeholder]),
            'exclude' => route('chat.members.exclude', [$formation, $placeholder]),
            'broadcastAuth' => url('/broadcasting/auth'),
        ],
    ];
    $fileAccept = collect((array) config('chat.attachments.file.mimes'))->map(fn ($ext) => '.'.$ext)->implode(',');
@endphp

<x-layouts::site :title="'Groupe · '.$formation->title" variant="chat">
    <div
        data-chat
        x-data="chatRoom(@js($config))"
        class="relative mx-auto flex h-full max-w-4xl flex-col bg-night-950 lg:border-x lg:border-veil/10"
        @chat-jump.window="jumpTo($event.detail)"
    >
        {{-- En-tête du groupe --}}
        <header class="z-20 shrink-0 border-b border-veil/10 bg-night-900/95 pt-[env(safe-area-inset-top)] backdrop-blur-xl">
            <div class="flex h-16 items-center gap-1 px-1.5 sm:px-3">
                <a href="{{ route('chat.index') }}" class="grid size-11 shrink-0 place-items-center rounded-full text-slate-200 hover:bg-veil/10" aria-label="Retour à mes groupes">
                    <flux:icon.arrow-left class="size-6" />
                </a>
                <a href="{{ route('chat.members', $formation) }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-2xl py-1.5 pr-2">
                    <x-site.formation-cover :formation="$formation" compact class="size-10 shrink-0 rounded-xl" />
                    <span class="min-w-0">
                        <span class="block truncate text-[15px] leading-5 font-semibold text-white">{{ $formation->title }}</span>
                        <span class="block truncate text-xs text-slate-400">
                            {{ $membersCount }} {{ $membersCount > 1 ? 'membres' : 'membre' }}<span x-show="announcementsOnly" @unless ($group->announcements_only) style="display: none" @endunless> · Annonces seulement</span>
                        </span>
                    </span>
                </a>
                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full text-slate-200 hover:bg-veil/10" @click="groupMenu = true" aria-label="Options du groupe">
                    <flux:icon.ellipsis-vertical class="size-6" />
                </button>
            </div>

            @include('site.chat.partials.pinned', ['pinned' => $pinned, 'viewer' => $viewer])
        </header>

        @if (session('status'))
            <div class="px-3 pt-3"><x-site.alert>{{ session('status') }}</x-site.alert></div>
        @endif

        {{-- Conversation --}}
        <main id="chat-scroll" x-ref="scroll" @scroll.passive="onScroll()" class="chat-wallpaper relative min-h-0 flex-1 overflow-y-auto overscroll-contain">
            <div class="mx-auto max-w-3xl px-2.5 pb-4 sm:px-6">
                <div x-ref="top" class="py-4 text-center">
                    <button type="button" x-show="hasOlder" @unless ($hasOlder) style="display: none" @endunless @click="loadOlder()" class="rounded-full bg-veil/10 px-4 py-2 text-xs font-semibold text-slate-200 hover:bg-veil/15" :disabled="loadingOlder">
                        <span x-show="!loadingOlder">Charger les messages précédents</span>
                        <span x-show="loadingOlder" x-cloak>Chargement…</span>
                    </button>
                    <p x-show="!hasOlder" @if ($hasOlder) style="display: none" @endif class="mx-auto max-w-sm rounded-2xl bg-veil/[0.04] px-4 py-3 text-xs text-slate-400 ring-1 ring-veil/10">
                        <flux:icon.lock-closed class="mr-1 inline size-3.5 align-[-2px]" />
                        Groupe privé réservé aux élèves de la formation et à l'équipe. Les numéros de téléphone et e-mails des membres ne sont jamais affichés.
                    </p>
                </div>

                <div id="chat-messages" x-ref="list">
                    @include('site.chat.partials.message-list', ['messages' => $messages, 'previous' => null, 'viewer' => $viewer, 'firstUnreadId' => $firstUnreadId, 'unreadCount' => $unreadCount])
                </div>

                @if ($messages->isEmpty())
                    <div x-ref="empty" x-show="lastId === 0" class="mx-auto mt-10 max-w-xs text-center">
                        <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.chat-bubble-left-right class="size-7" /></span>
                        <p class="mt-4 font-semibold text-white">Aucun message pour l'instant</p>
                        <p class="mt-1 text-sm text-slate-400">Présentez-vous et posez vos questions : l'équipe et les autres élèves vous répondront ici.</p>
                    </div>
                @endif
            </div>
        </main>

        {{-- Retour en bas, avec le nombre de nouveaux messages --}}
        <button
            type="button"
            x-show="!atBottom"
            x-cloak
            x-transition.opacity
            @click="scrollToBottom(true)"
            class="absolute right-4 bottom-28 z-10 grid size-11 place-items-center rounded-full bg-night-800 text-white shadow-xl shadow-black/40 ring-1 ring-veil/15"
            aria-label="Aller aux derniers messages"
        >
            <flux:icon.chevron-down class="size-6" />
            <span x-show="newCount > 0" x-text="newCount" class="absolute -top-1.5 -right-1 min-w-5 rounded-full bg-gold-400 px-1.5 text-center text-[11px] leading-5 font-bold text-night-950"></span>
        </button>

        {{-- Zone d'écriture --}}
        <footer class="z-20 shrink-0 border-t border-veil/10 bg-night-900/95 pb-[max(env(safe-area-inset-bottom),0.5rem)] backdrop-blur-xl">
            <div class="mx-auto max-w-3xl px-2 pt-2 sm:px-4">
                <p x-show="!canPost" @if ($viewer->canPost()) style="display: none" @endif class="flex items-start gap-2 rounded-2xl bg-veil/[0.05] px-4 py-3 text-sm text-slate-300 ring-1 ring-veil/10" data-test="post-blocked">
                    <flux:icon.information-circle class="mt-0.5 size-5 shrink-0 text-electric-300" />
                    <span x-text="blockedReason">{{ $viewer->postBlockedReason() }}</span>
                </p>

                @include('site.chat.partials.composer')
            </div>
        </footer>

        @include('site.chat.partials.overlays')
    </div>
</x-layouts::site>
