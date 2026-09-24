<x-layouts::site title="Messages signalés" variant="student">
    <div class="mx-auto max-w-3xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <a href="{{ route('chat.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Mes groupes
        </a>

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">Messages signalés</h1>
                <p class="mt-1 text-slate-400">Signalés par les membres des groupes. Supprimez le message ou classez le signalement.</p>
            </div>
            <span @class(['rounded-full px-4 py-1.5 text-sm font-bold', 'bg-gold-400 text-night-950' => $open->isNotEmpty(), 'bg-white/10 text-slate-300' => $open->isEmpty()])>{{ $open->count() }} à traiter</span>
        </div>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif

        <div class="mt-6 space-y-4" data-test="open-reports">
            @forelse ($open as $reports)
                @php
                    $report = $reports->first();
                    $message = $report->message;
                    $formation = $message->group->formation;
                @endphp
                <article class="card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 px-5 py-3">
                        <p class="text-sm font-semibold text-white">{{ $formation->title }}</p>
                        <span class="rounded-full bg-rose-500/15 px-2.5 py-1 text-xs font-semibold text-rose-200 ring-1 ring-rose-400/30">{{ $reports->count() }} {{ $reports->count() > 1 ? 'signalements' : 'signalement' }}</span>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="flex items-start gap-3">
                            <x-site.avatar :user="$message->author" class="size-10 text-xs" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm"><span class="font-semibold text-white">{{ $message->author->name }}</span> <span class="text-slate-500">· {{ $message->created_at->translatedFormat('j F à H\hi') }}</span></p>
                                <div class="mt-1 rounded-2xl bg-night-800 px-3 py-2 text-[15px] text-slate-100 ring-1 ring-white/[0.06]">
                                    @if ($message->isDeleted())
                                        <span class="text-slate-400 italic">Ce message a déjà été supprimé</span>
                                    @else
                                        @if ($message->type !== \App\Enums\ChatMessageType::Text)
                                            <p class="text-sm text-slate-300">{{ $message->type->preview() }}@if ($message->hasAttachment()) · <a href="{{ $message->attachmentUrl() }}" target="_blank" class="text-electric-300 underline">ouvrir le fichier</a>@endif</p>
                                        @endif
                                        @if (filled($message->body))
                                            <div class="chat-text">{{ \App\Chat\MessageText::toHtml($message->body) }}</div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        <ul class="space-y-1.5 text-sm">
                            @foreach ($reports as $item)
                                <li class="flex items-start gap-2 text-slate-300">
                                    <flux:icon.flag class="mt-0.5 size-4 shrink-0 text-rose-300" />
                                    <span><span class="font-semibold text-white">{{ $item->reason->label() }}</span> · par {{ $item->reporter->name }}, {{ $item->created_at->diffForHumans() }}@if ($item->details) : « {{ $item->details }} »@endif</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="grid gap-2 sm:grid-cols-3">
                            <form method="POST" action="{{ route('moderation.reports.handle', $report) }}">
                                @csrf
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn w-full bg-rose-600 !py-2.5 !text-sm text-white hover:bg-rose-500"><flux:icon.trash class="size-4" /> Supprimer le message</button>
                            </form>
                            <form method="POST" action="{{ route('moderation.reports.handle', $report) }}">
                                @csrf
                                <input type="hidden" name="action" value="dismiss">
                                <button type="submit" class="btn-ghost w-full !py-2.5 !text-sm">Classer sans suite</button>
                            </form>
                            <a href="{{ route('chat.members', $formation) }}" class="btn-ghost w-full !py-2.5 !text-sm"><flux:icon.speaker-x-mark class="size-4" /> Sourdine / exclure</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="card p-6 text-center">
                    <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-emerald-500/15 text-emerald-300"><flux:icon.check-badge class="size-7" /></span>
                    <p class="mt-4 font-semibold text-white">Aucun message à traiter</p>
                    <p class="mt-1 text-sm text-slate-400">Les nouveaux signalements apparaîtront ici.</p>
                </div>
            @endforelse
        </div>

        @if ($handled->isNotEmpty())
            <h2 class="mt-10 text-lg font-bold text-white">Derniers signalements traités</h2>
            <ul class="card mt-3 divide-y divide-white/[0.06] text-sm">
                @foreach ($handled as $item)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3">
                        <span class="font-semibold text-white">{{ $item->message->group->formation->title }}</span>
                        <span class="text-slate-400">{{ $item->reason->label() }}</span>
                        <span class="ml-auto text-xs text-slate-500">{{ $item->status->label() }} par {{ $item->handler?->name ?? 'l\'équipe' }}, {{ $item->handled_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts::site>
