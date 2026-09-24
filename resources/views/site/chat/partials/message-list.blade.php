{{--
    Suite de messages avec séparateurs de jour et repère « messages non lus ».
    $previous : message juste avant la liste (pour savoir s'il faut un séparateur de jour).
--}}
@php $prev = $previous ?? null; @endphp

@foreach ($messages as $message)
    @php
        $newDay = $prev === null || ! $prev->created_at->isSameDay($message->created_at);
        $isFirstUnread = ($firstUnreadId ?? null) === $message->id;
        $grouped = ! $isFirstUnread && \App\Chat\MessageGrouping::continues($message, $prev);
    @endphp

    @if ($newDay)
        <div class="chat-day" data-day="{{ $message->created_at->toDateString() }}">
            <span>{{ \App\Chat\ChatDates::day($message->created_at) }}</span>
        </div>
    @endif

    @if ($isFirstUnread)
        <div id="premier-non-lu" class="chat-unread" data-unread-divider>
            <span>{{ $unreadCount }} {{ $unreadCount > 1 ? 'messages non lus' : 'message non lu' }}</span>
        </div>
    @endif

    @include('site.chat.partials.message', ['message' => $message, 'viewer' => $viewer, 'grouped' => $grouped])

    @php $prev = $message; @endphp
@endforeach
