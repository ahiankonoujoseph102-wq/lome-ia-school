{{-- Messages épinglés (annonces, liens Google Meet…), affichés en haut du groupe. --}}
<div id="chat-pinned">
    @if ($pinned->isNotEmpty())
        <div class="chat-pinned" x-data="{ index: 0, count: {{ $pinned->count() }} }">
            @foreach ($pinned as $i => $message)
                <div
                    @if ($i > 0) x-cloak @endif
                    x-show="index === {{ $i }}"
                    class="flex w-full items-start gap-3 text-left"
                    role="button"
                    tabindex="0"
                    data-jump="{{ $message->id }}"
                    data-pinned-cycle
                    @click="if (! $event.target.closest('a')) { $dispatch('chat-jump', {{ $message->id }}); index = (index + 1) % count }"
                >
                    <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-gold-400/15 text-gold-300">
                        <flux:icon.bookmark variant="solid" class="size-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2 text-xs font-semibold text-gold-300">
                            Message épinglé
                            @if ($pinned->count() > 1)
                                <span class="rounded-full bg-veil/10 px-1.5 text-[10px] text-slate-300 tabular-nums">{{ $i + 1 }}/{{ $pinned->count() }}</span>
                            @endif
                        </span>
                        <span class="line-clamp-2 text-sm text-slate-200 [overflow-wrap:anywhere]">
                            @if ($message->type === \App\Enums\ChatMessageType::Text)
                                {{ \App\Chat\MessageText::toHtml($message->body) }}
                            @else
                                {{ $message->preview(120) }}
                            @endif
                        </span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
