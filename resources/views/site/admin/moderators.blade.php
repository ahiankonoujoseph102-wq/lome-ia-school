<x-layouts::site title="Équipe de modération" noindex>
    <div class="mx-auto max-w-2xl px-4 pt-6 pb-16 sm:px-6 sm:pt-10">
        <a href="{{ route('admin.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white">
            <flux:icon.arrow-left class="size-4" /> Administration
        </a>

        <h1 class="mt-4 text-2xl font-bold text-white sm:text-3xl">Équipe de modération</h1>
        <p class="mt-1 text-slate-400">Les modérateurs sont membres de tous les groupes, avec le badge « Équipe ». Ils peuvent supprimer et épingler des messages, mettre en sourdine, exclure et traiter les signalements.</p>

        @if (session('status'))
            <x-site.alert class="mt-5">{{ session('status') }}</x-site.alert>
        @endif

        <form method="POST" action="{{ route('admin.moderators.store') }}" class="card mt-6 space-y-4 p-5 sm:p-6">
            @csrf
            <x-site.field name="email" label="Adresse e-mail du compte à nommer modérateur" type="email" :value="old('email')" required inputmode="email" autocomplete="off" />
            <p class="text-xs text-slate-500">La personne doit d'abord avoir créé son compte sur le site.</p>
            <button type="submit" class="btn-gold w-full sm:w-auto"><flux:icon.user-plus class="size-5" /> Nommer modérateur</button>
        </form>

        <h2 class="mt-8 text-lg font-bold text-white">Modérateurs</h2>
        <ul class="card mt-3 divide-y divide-veil/[0.06]" data-test="moderators">
            @forelse ($moderators as $moderator)
                <li class="flex items-center gap-3 px-4 py-3">
                    <x-site.avatar :user="$moderator" class="size-10 text-xs" />
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5"><span class="truncate font-semibold text-white">{{ $moderator->name }}</span> <span class="chat-badge chat-badge-team">Équipe</span></p>
                        <p class="truncate text-xs text-slate-500">{{ $moderator->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.moderators.destroy', $moderator) }}" onsubmit="return confirm('Retirer le rôle de modérateur ?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-full px-3 py-2 text-sm font-semibold text-rose-300 ring-1 ring-rose-400/40 hover:bg-rose-500/10">Retirer</button>
                    </form>
                </li>
            @empty
                <li class="px-4 py-5 text-center text-sm text-slate-400">Aucun modérateur pour l'instant.</li>
            @endforelse
        </ul>

        <h2 class="mt-8 text-lg font-bold text-white">Administrateurs</h2>
        <ul class="card mt-3 divide-y divide-veil/[0.06]">
            @foreach ($admins as $admin)
                <li class="flex items-center gap-3 px-4 py-3">
                    <x-site.avatar :user="$admin" class="size-10 text-xs" />
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5"><span class="truncate font-semibold text-white">{{ $admin->name }}</span> <span class="chat-badge chat-badge-trainer">Formateur</span></p>
                        <p class="truncate text-xs text-slate-500">{{ $admin->email }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts::site>
