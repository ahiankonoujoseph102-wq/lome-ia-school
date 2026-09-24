<x-layouts::site title="Mes groupes" variant="student">
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">Mes groupes</h1>
                <p class="mt-1 text-slate-400">Un groupe privé par formation, avec l'équipe et les autres élèves.</p>
            </div>
            <a href="{{ route('student.notifications') }}" class="grid size-11 shrink-0 place-items-center rounded-full text-slate-300 ring-1 ring-white/15 hover:bg-white/10 hover:text-white" aria-label="Réglages des notifications">
                <flux:icon.bell class="size-5" />
            </a>
        </div>

        @can('moderate-chat')
            <a href="{{ route('moderation.reports.index') }}" class="card mt-5 flex items-center gap-3 px-4 py-3.5 text-sm font-semibold text-slate-200 hover:bg-white/5">
                <flux:icon.flag class="size-5 text-gold-300" /> Messages signalés
                @php $openReports = \App\Models\ChatMessageReport::query()->where('status', 'open')->distinct()->count('chat_message_id'); @endphp
                <span @class(['ml-auto min-w-6 rounded-full px-2 py-0.5 text-center text-xs font-bold', 'bg-gold-400 text-night-950' => $openReports > 0, 'bg-white/10 text-slate-400' => $openReports === 0])>{{ $openReports }}</span>
            </a>
        @endcan

        @if ($members->isEmpty())
            <div class="card mt-6 p-6 text-center">
                <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-electric-500/15 text-electric-300"><flux:icon.chat-bubble-left-right class="size-7" /></span>
                <p class="mt-4 font-semibold text-white">Aucun groupe pour l'instant</p>
                <p class="mt-1 text-sm text-slate-400">Dès que vous achetez une formation, vous rejoignez automatiquement son groupe privé.</p>
                <a href="{{ route('formations.index') }}" class="btn-gold mt-5">Voir les formations</a>
            </div>
        @else
            <ul class="card mt-6 divide-y divide-white/[0.06] overflow-hidden" data-test="group-list">
                @foreach ($members as $member)
                    @php
                        $group = $member->group;
                        $formation = $group->formation;
                        $last = $lastMessages->get($group->id);
                        $count = (int) ($unread[$group->id] ?? 0);
                    @endphp
                    <li>
                        <a href="{{ route('chat.show', $formation) }}" class="flex items-center gap-3 px-4 py-3.5 transition hover:bg-white/[0.04] active:bg-white/[0.07]">
                            <x-site.formation-cover :formation="$formation" compact class="size-12 shrink-0 rounded-2xl" />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline gap-2">
                                    <span class="min-w-0 flex-1 truncate font-semibold text-white">{{ $formation->title }}</span>
                                    @if ($last)
                                        <span @class(['shrink-0 text-xs tabular-nums', 'font-semibold text-gold-300' => $count > 0, 'text-slate-500' => $count === 0])>{{ \App\Chat\ChatDates::short($last->created_at) }}</span>
                                    @endif
                                </span>
                                <span class="mt-0.5 flex items-center gap-2">
                                    <span class="min-w-0 flex-1 truncate text-sm text-slate-400">
                                        @if ($group->announcements_only)
                                            <flux:icon.megaphone class="mr-0.5 inline size-3.5 align-[-2px] text-gold-300" />
                                        @endif
                                        @if ($last)
                                            {{ $last->user_id === auth()->id() ? 'Vous' : $last->author->first_name }} : {{ $last->preview(70) }}
                                        @else
                                            Aucun message pour l'instant
                                        @endif
                                    </span>
                                    @if ($member->notifications_muted)
                                        <flux:icon.bell-slash class="size-4 shrink-0 text-slate-500" aria-label="Notifications coupées" />
                                    @endif
                                    @if ($count > 0)
                                        <span class="min-w-6 shrink-0 rounded-full bg-gold-400 px-1.5 text-center text-xs leading-6 font-bold text-night-950" aria-label="{{ $count }} non lus">{{ $count > 99 ? '99+' : $count }}</span>
                                    @endif
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts::site>
