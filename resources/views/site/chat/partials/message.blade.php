{{--
    Un message (bulle). Données lues par le menu d'actions : data-*.
    Jamais de numéro de téléphone ni d'e-mail : seulement le nom, la photo et le badge de l'auteur.
--}}
@use('App\Enums\ChatMessageType')

@php
    $author = $message->author;
    $mine = $viewer->owns($message);
    $deleted = $message->isDeleted();
    $badge = $author->chatBadge();
    $palette = ['text-sky-300', 'text-emerald-300', 'text-amber-300', 'text-pink-300', 'text-cyan-300', 'text-lime-300', 'text-orange-300', 'text-fuchsia-300'];
    $nameColor = $author->isStaff() ? 'text-gold-300' : $palette[$author->id % count($palette)];
    $reply = $message->replyTo;
    // Réactions (six emojis, une par personne) : compteurs sous la bulle, comme sur WhatsApp.
    $reactionCounts = $deleted ? [] : $message->reactionCounts();
    $myReaction = $deleted ? null : $message->reactionOf($viewer->user);
    $canReact = $viewer->canReact($message);
@endphp

<div
    id="m-{{ $message->id }}"
    @class(['chat-row group/message flex items-end gap-2', 'justify-end' => $mine, 'mt-0.5' => $grouped, 'mt-3' => ! $grouped, 'pb-4' => $reactionCounts !== []])
    data-message-id="{{ $message->id }}"
    data-author-id="{{ $author->id }}"
    data-author="{{ $mine ? 'Vous' : $author->name }}"
    data-preview="{{ $message->preview(80) }}"
    data-thumb="{{ ! $deleted && $message->type === ChatMessageType::Image && ! $message->isExpired() ? $message->attachmentUrl() : '' }}"
    data-text="{{ $message->type === ChatMessageType::Text && ! $deleted ? $message->body : '' }}"
    data-can-delete="{{ $viewer->canDelete($message) ? '1' : '0' }}"
    data-can-pin="{{ $viewer->canPin($message) ? '1' : '0' }}"
    data-pinned="{{ $message->isPinned() ? '1' : '0' }}"
    data-can-report="{{ $viewer->canReport($message) ? '1' : '0' }}"
    data-can-moderate="{{ $viewer->canModerateAuthorOf($message) ? '1' : '0' }}"
    data-deleted="{{ $deleted ? '1' : '0' }}"
    data-can-react="{{ $canReact ? '1' : '0' }}"
    data-my-reaction="{{ $myReaction?->value }}"
>
    @unless ($mine)
        <div class="w-8 shrink-0 self-end">
            @unless ($grouped)
                <x-site.avatar :user="$author" class="size-8 text-[11px]" />
            @endunless
        </div>
    @endunless

    <div @class([
        'chat-bubble relative max-w-[82%] min-w-0 sm:max-w-[70%]',
        'chat-bubble-mine' => $mine && ! $deleted,
        'chat-bubble-other' => ! $mine && ! $deleted,
        'chat-bubble-staff' => ! $mine && ! $deleted && $author->isStaff(),
        'chat-bubble-deleted' => $deleted,
        'chat-bubble-tail' => ! $grouped,
    ])>
        @if (! $mine && ! $grouped)
            <p class="mb-0.5 flex min-w-0 items-center gap-1.5 text-[13px] leading-5 font-semibold">
                <span class="truncate {{ $nameColor }}">{{ $author->name }}</span>
                @if ($badge)
                    <span class="{{ \App\Chat\ChatBadges::classes($author->chatBadgeKind()) }}" data-test="chat-badge">{{ $badge }}</span>
                @endif
            </p>
        @endif

        @if ($deleted)
            <p class="flex items-center gap-1.5 text-sm text-slate-400 italic">
                <flux:icon.no-symbol class="size-4" /> Ce message a été supprimé
            </p>
        @else
            @if ($reply)
                @include('site.chat.partials.quote', ['reply' => $reply, 'viewer' => $viewer])
            @endif

            @if ($message->isExpired())
                {{-- Note vocale ou vidéo supprimée après la durée de conservation : le message reste. --}}
                <p class="chat-expired" data-test="expired-file">
                    @if ($message->type === ChatMessageType::Voice)
                        <flux:icon.microphone class="size-5 shrink-0" />
                    @else
                        <flux:icon.video-camera class="size-5 shrink-0" />
                    @endif
                    <span>
                        <span class="block font-semibold">Fichier expiré</span>
                        <span class="block text-xs opacity-80">{{ $message->type === ChatMessageType::Voice ? 'Note vocale' : 'Vidéo' }} supprimée automatiquement {{ (int) config('chat.media_retention_months', 12) }} mois après son envoi</span>
                    </span>
                </p>
            @endif

            @switch ($message->isExpired() ? null : $message->type)
                @case (ChatMessageType::Image)
                    <a href="{{ $message->attachmentUrl() }}" class="chat-media block overflow-hidden rounded-xl" data-lightbox aria-label="Agrandir l'image">
                        <img
                            src="{{ $message->attachmentUrl() }}"
                            alt="Image envoyée par {{ $mine ? 'vous' : $author->name }}"
                            width="{{ $message->width }}"
                            height="{{ $message->height }}"
                            loading="lazy"
                            class="max-h-80 w-full bg-black/30 object-cover"
                            style="aspect-ratio: {{ $message->width ?: 4 }} / {{ $message->height ?: 3 }}"
                        >
                    </a>
                    @break

                @case (ChatMessageType::Voice)
                    <div class="chat-voice" x-data="voicePlayer({{ (int) $message->duration_seconds }})">
                        <button type="button" class="chat-voice-button" @click="toggle()" :aria-label="playing ? 'Mettre en pause' : 'Écouter la note vocale'" aria-label="Écouter la note vocale">
                            <flux:icon.play variant="solid" class="size-5" x-show="!playing" />
                            <flux:icon.pause variant="solid" class="size-5" x-show="playing" x-cloak />
                        </button>
                        <div class="min-w-0 flex-1">
                            <input type="range" min="0" max="{{ max(1, (int) $message->duration_seconds) }}" step="0.1" value="0" x-ref="range" @input="seek($event.target.value)" class="chat-range" aria-label="Position dans la note vocale" :style="`--progress: ${progress}%`">
                            <div class="mt-0.5 flex justify-between text-[11px] text-slate-300/80 tabular-nums">
                                <span x-text="format(current)">0:00</span>
                                <span>{{ $message->formattedDuration() }}</span>
                            </div>
                        </div>
                        <flux:icon.microphone class="size-4 shrink-0 text-slate-300/70" />
                        <audio x-ref="audio" preload="none" src="{{ $message->attachmentUrl() }}"></audio>
                    </div>
                    @break

                @case (ChatMessageType::Video)
                    <video
                        controls
                        playsinline
                        preload="metadata"
                        src="{{ $message->attachmentUrl() }}#t=0.1"
                        class="chat-media max-h-80 w-full rounded-xl bg-black"
                    ></video>
                    @break

                @case (ChatMessageType::Location)
                    @include('site.chat.partials.location', ['location' => $message->location])
                    @break

                @case (ChatMessageType::File)
                    <a href="{{ $message->downloadUrl() }}" class="chat-file">
                        <span class="chat-file-icon">{{ $message->extension() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-white">{{ $message->attachment_name }}</span>
                            <span class="block text-xs text-slate-300/80">{{ $message->formattedSize() }} · Télécharger</span>
                        </span>
                        <flux:icon.arrow-down-tray class="size-5 shrink-0 text-slate-300" />
                    </a>
                    @break
            @endswitch

            @if (filled($message->body) && $message->type !== ChatMessageType::Location)
                <div @class(['chat-text', 'mt-1.5' => $message->type !== ChatMessageType::Text])>{{ \App\Chat\MessageText::toHtml($message->body) }}</div>
            @endif
        @endif

        <p class="chat-meta">
            @if ($message->isPinned())
                <flux:icon.bookmark variant="solid" class="size-3" /><span class="sr-only">Épinglé</span>
            @endif
            <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('H:i') }}</time>
        </p>

        @if ($reactionCounts !== [])
            {{-- Toucher un emoji : on réagit avec lui (ou on retire sa réaction si c'était déjà la sienne). --}}
            <div @class(['chat-reactions', 'justify-end' => $mine]) data-test="reactions">
                @foreach ($reactionCounts as $key => $count)
                    @php $reaction = \App\Enums\ChatReaction::from($key); @endphp
                    <button
                        type="button"
                        @class(['chat-reaction', 'chat-reaction-mine' => $myReaction === $reaction])
                        @if ($canReact) data-react="{{ $key }}" @else disabled @endif
                        aria-pressed="{{ $myReaction === $reaction ? 'true' : 'false' }}"
                        aria-label="{{ $reaction->label() }} : {{ $count }} {{ $count > 1 ? 'réactions' : 'réaction' }}{{ $myReaction === $reaction ? ', dont la vôtre' : '' }}"
                        data-test="reaction-{{ $key }}"
                    ><span aria-hidden="true" class="chat-reaction-emoji">{{ $reaction->emoji() }}</span><span class="tabular-nums">{{ $count }}</span></button>
                @endforeach
            </div>
        @endif
    </div>

    @unless ($deleted)
        <button type="button" class="chat-more" data-menu aria-label="Actions sur ce message">
            <flux:icon.ellipsis-vertical class="size-5" />
        </button>
    @endunless
</div>
