<x-layouts::site :title="'Membres · '.$formation->title" variant="student">
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <a href="{{ route('chat.show', $formation) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Retour au groupe
        </a>

        <h1 class="mt-4 text-2xl font-bold text-white">Membres du groupe</h1>
        <p class="mt-1 text-slate-400">{{ $formation->title }} · {{ $active->count() }} {{ $active->count() > 1 ? 'membres' : 'membre' }}</p>
        <p class="mt-3 flex items-start gap-2 text-sm text-slate-400">
            <flux:icon.lock-closed class="mt-0.5 size-4 shrink-0" /> Seuls le nom et la photo sont visibles. Les numéros de téléphone et e-mails ne sont jamais affichés.
        </p>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif
        @if ($errors->any())
            <x-site.alert type="error" class="mt-5">{{ $errors->first() }}</x-site.alert>
        @endif

        <ul class="card mt-6 divide-y divide-veil/[0.06]" data-test="member-list">
            @foreach ($active as $member)
                @php $user = $member->user; @endphp
                <li class="px-4 py-3" x-data="{ open: false }">
                    <div class="flex items-center gap-3">
                        <x-site.avatar :user="$user" class="size-11 text-sm" />
                        <div class="min-w-0 flex-1">
                            <p class="flex min-w-0 items-center gap-1.5">
                                <span class="truncate font-semibold text-white">{{ $user->id === $viewer->user->id ? $user->name.' (vous)' : $user->name }}</span>
                                @if ($user->chatBadge())
                                    <span @class(['chat-badge', 'chat-badge-trainer' => $user->isAdmin(), 'chat-badge-team' => ! $user->isAdmin()])>{{ $user->chatBadge() }}</span>
                                @endif
                            </p>
                            @if ($member->isMuted())
                                <p class="text-xs text-gold-300">En sourdine jusqu'au {{ $member->muted_until?->translatedFormat('j F à H\hi') }}</p>
                            @else
                                <p class="text-xs text-slate-500">Membre depuis le {{ $member->joined_at->translatedFormat('j F Y') }}</p>
                            @endif
                        </div>
                        @if ($viewer->canModerate() && ! $user->isStaff())
                            <button type="button" @click="open = !open" class="grid size-10 place-items-center rounded-full text-slate-400 hover:bg-veil/10 hover:text-white" :aria-expanded="open.toString()" aria-label="Modérer {{ $user->name }}">
                                <flux:icon.ellipsis-vertical class="size-5" />
                            </button>
                        @endif
                    </div>

                    @if ($viewer->canModerate() && ! $user->isStaff())
                        <div x-show="open" x-cloak class="mt-3 space-y-2 rounded-2xl bg-veil/[0.04] p-3 ring-1 ring-veil/10">
                            @if ($member->isMuted())
                                <form method="POST" action="{{ route('chat.members.unmute', [$formation, $user]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-ghost w-full !py-2.5 !text-sm"><flux:icon.speaker-wave class="size-4" /> Retirer la sourdine</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('chat.members.mute', [$formation, $user]) }}" class="flex gap-2">
                                    @csrf
                                    <label for="duree-{{ $user->id }}" class="sr-only">Durée de la sourdine</label>
                                    <select id="duree-{{ $user->id }}" name="hours" class="field-input !py-2.5 !text-sm">
                                        @foreach ($muteDurations as $hours => $label)
                                            <option value="{{ $hours }}" @selected($hours === 24)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-ghost shrink-0 !px-4 !py-2.5 !text-sm"><flux:icon.speaker-x-mark class="size-4" /> Sourdine</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('chat.members.exclude', [$formation, $user]) }}" onsubmit="return confirm('Exclure ce membre du groupe ?')">
                                @csrf
                                <button type="submit" class="btn w-full border border-rose-400/40 bg-rose-500/10 !py-2.5 !text-sm text-rose-200 hover:bg-rose-500/20"><flux:icon.user-minus class="size-4" /> Exclure du groupe</button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($excluded->isNotEmpty())
            <h2 class="mt-8 text-lg font-bold text-white">Membres exclus</h2>
            <ul class="card mt-3 divide-y divide-veil/[0.06]">
                @foreach ($excluded as $member)
                    <li class="flex items-center gap-3 px-4 py-3">
                        <x-site.avatar :user="$member->user" class="size-10 text-xs opacity-60" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-300">{{ $member->user->name }}</p>
                            <p class="text-xs text-slate-500">Exclu le {{ $member->removed_at?->translatedFormat('j F Y') }}</p>
                        </div>
                        <form method="POST" action="{{ route('chat.members.reinstate', [$formation, $member->user]) }}">
                            @csrf
                            <button type="submit" class="rounded-full px-3 py-2 text-sm font-semibold text-electric-300 ring-1 ring-electric-400/40 hover:bg-electric-500/10">Réintégrer</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts::site>
