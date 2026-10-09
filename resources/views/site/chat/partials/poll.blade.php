{{--
    Sondage : question, choix avec barre de progression, nombre de votes et pourcentage (des votants).
    Le choix du membre est entouré de doré. Toucher un choix vote, change ou retire le vote (chat.js).
    Style stable face au mode sombre forcé : fonds foncés translucides, textes clairs, contours en « ring ».
--}}
@use('App\Chat\ChatPolls')

@if ($poll)
    @php
        $results = $poll->results($viewer->user);
        $canVote = ChatPolls::canVote($poll, $message);
        $canClose = ChatPolls::canClose($poll, $message, $viewer->user);
    @endphp
    <div
        class="chat-poll"
        data-test="poll"
        data-poll="{{ $poll->id }}"
        data-poll-multiple="{{ $poll->allows_multiple ? '1' : '0' }}"
        data-poll-mine="{{ implode(',', $results['mine']) }}"
    >
        <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-slate-300 uppercase">
            <flux:icon.chart-bar class="size-3.5" /> Sondage
            @if ($poll->isClosed())
                <span class="chat-poll-closed" data-test="poll-closed">Clôturé</span>
            @endif
        </p>
        <p class="mt-1 text-[15px] leading-5 font-semibold break-words text-white">{{ $poll->question }}</p>
        <p class="mt-0.5 text-xs text-slate-300">{{ $poll->allows_multiple ? 'Plusieurs réponses possibles' : 'Une seule réponse' }}</p>

        <div class="mt-2.5 space-y-2" role="group" aria-label="Choix du sondage">
            @foreach ($results['options'] as $option)
                <button
                    type="button"
                    @class(['chat-poll-option', 'chat-poll-option-mine' => $option['mine']])
                    @if ($canVote) data-poll-option="{{ $option['id'] }}" @else disabled @endif
                    aria-pressed="{{ $option['mine'] ? 'true' : 'false' }}"
                    aria-label="{{ $option['label'] }} : {{ $option['votes'] }} {{ $option['votes'] > 1 ? 'votes' : 'vote' }}, {{ $option['percent'] }} %{{ $option['mine'] ? ', votre choix' : '' }}"
                    data-test="poll-option"
                >
                    <span class="flex items-start gap-2">
                        <span @class(['chat-poll-check', 'rounded-full' => ! $poll->allows_multiple, 'rounded-md' => $poll->allows_multiple, 'chat-poll-check-on' => $option['mine']]) aria-hidden="true">
                            @if ($option['mine'])
                                <flux:icon.check class="size-3.5" />
                            @endif
                        </span>
                        <span class="min-w-0 flex-1 text-left text-sm leading-5 break-words text-white">{{ $option['label'] }}</span>
                        <span class="shrink-0 text-xs leading-5 font-semibold text-slate-200 tabular-nums">{{ $option['votes'] }} {{ $option['votes'] > 1 ? 'votes' : 'vote' }} · {{ $option['percent'] }} %</span>
                    </span>
                    <span class="chat-poll-track" aria-hidden="true"><span class="chat-poll-bar" style="width: {{ $option['percent'] }}%"></span></span>
                </button>
            @endforeach
        </div>

        <p class="mt-2 text-xs text-slate-300" data-test="poll-voters">
            {{ $results['voters'] }} {{ $results['voters'] > 1 ? 'votants' : 'votant' }}
            @if ($canVote && $results['mine'] !== [])
                · Touchez votre choix pour le retirer
            @elseif (! $canVote && ! $poll->isClosed())
                · Votes indisponibles pour le moment
            @endif
        </p>

        @if ($canClose)
            <button type="button" class="chat-poll-close-button" data-poll-close="{{ $poll->id }}" data-test="poll-close">
                <flux:icon.lock-closed class="size-4" /> Clôturer le sondage
            </button>
        @endif
    </div>
@else
    <p class="chat-expired">Sondage indisponible</p>
@endif
