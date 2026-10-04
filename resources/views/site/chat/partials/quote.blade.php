{{--
    Citation du message auquel répond ce message (auteur et extrait court). La toucher fait défiler jusqu'au
    message d'origine et le met en évidence. Message d'origine supprimé (par son auteur ou par la modération) :
    « Message supprimé », jamais son contenu.
--}}
@php
    $quoteDeleted = $reply->isDeleted();
    $quoteAuthor = $reply->user_id === $viewer->user->id ? 'Vous' : $reply->author->name;
    $quoteThumb = ! $quoteDeleted && $reply->type === \App\Enums\ChatMessageType::Image && ! $reply->isExpired() ? $reply->attachmentUrl() : null;
@endphp

<button type="button" class="chat-quote" data-jump="{{ $reply->id }}" aria-label="Voir le message cité de {{ $quoteAuthor }}" data-test="reply-quote">
    <span class="min-w-0 flex-1">
        <span class="block truncate text-xs font-semibold {{ $reply->author->isStaff() ? 'text-gold-300' : 'text-electric-200' }}">{{ $quoteAuthor }}</span>
        @if ($quoteDeleted)
            <span class="flex items-center gap-1 text-xs text-slate-400 italic" data-test="reply-quote-deleted">
                <flux:icon.no-symbol class="size-3.5 shrink-0" /> Message supprimé
            </span>
        @else
            <span class="line-clamp-2 text-xs text-slate-300">{{ $reply->preview(120) }}</span>
        @endif
    </span>
    @if ($quoteThumb)
        <img src="{{ $quoteThumb }}" alt="" loading="lazy" class="size-10 shrink-0 rounded-md bg-black/30 object-cover">
    @endif
</button>
