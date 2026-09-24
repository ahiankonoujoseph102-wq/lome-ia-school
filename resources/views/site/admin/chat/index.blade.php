@php
    $formatBytes = fn (int $bytes): string => $bytes >= 1024 ** 3
        ? number_format($bytes / 1024 ** 3, 2, ',', ' ').' Go'
        : ($bytes >= 1024 ** 2 ? number_format($bytes / 1024 ** 2, 1, ',', ' ').' Mo' : number_format($bytes / 1024, 0, ',', ' ').' Ko');
@endphp

<x-layouts::site title="Groupes de discussion" noindex>
    <div class="mx-auto max-w-5xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Administration
        </a>

        <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Groupes de discussion</h1>
        <p class="mt-1 text-slate-400">Un groupe par formation. Les élèves y entrent dès que l'accès leur est ouvert.</p>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="card p-5" data-test="disk-usage">
                <p class="text-sm text-slate-400">Espace disque des fichiers</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ $formatBytes($totalBytes) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $totalFiles }} {{ $totalFiles > 1 ? 'fichiers' : 'fichier' }} (photos, notes vocales, vidéos, documents)</p>
            </div>
            <a href="{{ route('moderation.reports.index') }}" class="card card-hover p-5">
                <p class="text-sm text-slate-400">Messages signalés</p>
                <p @class(['mt-1 text-2xl font-bold', 'text-gold-300' => $openReports > 0, 'text-white' => $openReports === 0])>{{ $openReports }}</p>
                <p class="mt-1 text-xs text-slate-500">à traiter</p>
            </a>
            <a href="{{ route('admin.moderators.index') }}" class="card card-hover p-5">
                <p class="text-sm text-slate-400">Équipe de modération</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ \App\Models\User::query()->where('role', 'moderator')->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">modérateur(s) · gérer l'équipe</p>
            </a>
        </div>

        <div class="mt-6 space-y-3" data-test="group-list">
            @foreach ($groups as $group)
                @php $disk = $diskByGroup->get($group->id); @endphp
                <article class="card p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <x-site.formation-cover :formation="$group->formation" compact class="size-12 shrink-0 rounded-2xl" />
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-white">{{ $group->formation->title }}</p>
                            <p class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-400">
                                <span><span class="font-semibold text-slate-200">{{ $group->active_members_count }}</span> membres</span>
                                <span><span class="font-semibold text-slate-200">{{ $group->messages_count }}</span> messages</span>
                                <span><span class="font-semibold text-slate-200">{{ $group->recent_messages_count }}</span> cette semaine</span>
                                <span>{{ $disk ? $formatBytes((int) $disk->bytes) : '0 Ko' }} de fichiers</span>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $group->last_message_at ? 'Dernier message '.$group->last_message_at->diffForHumans() : 'Aucun message' }}
                                @if ($group->announcements_only)
                                    · <span class="font-semibold text-gold-300">Mode annonces</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('chat.show', $group->formation) }}" class="btn-violet !px-4 !py-2 !text-sm"><flux:icon.chat-bubble-left-right class="size-4" /> Ouvrir</a>
                        <a href="{{ route('admin.chat.edit', $group) }}" class="btn-ghost !px-4 !py-2 !text-sm"><flux:icon.pencil-square class="size-4" /> Règles</a>
                        <form method="POST" action="{{ route('admin.chat.announcements', $group) }}">
                            @csrf
                            <button type="submit" class="btn-ghost !px-4 !py-2 !text-sm"><flux:icon.megaphone class="size-4" /> {{ $group->announcements_only ? 'Désactiver le mode annonces' : 'Mode annonces' }}</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-layouts::site>
