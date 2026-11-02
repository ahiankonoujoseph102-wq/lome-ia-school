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
            <p class="text-xs text-slate-400">La personne doit d'abord avoir créé son compte sur le site. Elle devra activer la double authentification pour accéder à la modération.</p>
            <x-admin.password-confirm />
            <button type="submit" class="btn-gold w-full sm:w-auto"><flux:icon.user-plus class="size-5" /> Nommer modérateur</button>
        </form>

        <h2 class="mt-8 text-lg font-bold text-white">Modérateurs</h2>
        <ul class="card mt-3 divide-y divide-veil/[0.06]" data-test="moderators">
            @forelse ($moderators as $moderator)
                @php $demoteErrors = $errors->getBag('demote'.$moderator->id); @endphp
                <li class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <x-site.avatar :user="$moderator" class="size-10 text-xs" />
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5"><span class="truncate font-semibold text-white">{{ $moderator->name }}</span> <span class="chat-badge chat-badge-team">Équipe</span></p>
                            <p class="truncate text-xs text-slate-400">{{ $moderator->email }}</p>
                        </div>
                    </div>
                    <details class="group mt-2" @if ($demoteErrors->any()) open @endif>
                        <summary class="inline-flex cursor-pointer list-none rounded-full px-3 py-1.5 text-sm font-semibold text-rose-300 ring-1 ring-rose-400/40 hover:bg-rose-500/10">Retirer le rôle</summary>
                        <form method="POST" action="{{ route('admin.moderators.destroy', $moderator) }}" class="mt-3 space-y-3 rounded-2xl bg-rose-500/5 p-3 ring-1 ring-rose-400/30">
                            @csrf
                            @method('DELETE')
                            <label for="demote-password-{{ $moderator->id }}" class="field-label">Votre mot de passe pour confirmer</label>
                            <input id="demote-password-{{ $moderator->id }}" name="current_password" type="password" required autocomplete="current-password" @class(['field-input', 'field-input-error' => $demoteErrors->has('current_password')])>
                            @if ($demoteErrors->has('current_password'))
                                <p class="text-sm text-rose-300">{{ $demoteErrors->first('current_password') }}</p>
                            @endif
                            <button type="submit" class="rounded-full px-4 py-2 text-sm font-semibold text-rose-200 ring-1 ring-rose-400/50 hover:bg-rose-500/10">Confirmer le retrait</button>
                        </form>
                    </details>
                </li>
            @empty
                <li class="px-4 py-5 text-center text-sm text-slate-400">Aucun modérateur pour l'instant.</li>
            @endforelse
        </ul>

        {{-- Étiquettes à côté du nom dans les groupes : compte principal et libellés --}}
        @php $badgeErrors = $errors->getBag('badges'); @endphp
        <h2 class="mt-8 text-lg font-bold text-white">Étiquettes dans les groupes</h2>
        <form method="POST" action="{{ route('admin.moderators.badges') }}" class="card mt-3 space-y-4 p-5 sm:p-6" data-test="chat-badges-form">
            @csrf
            @method('PUT')
            <p class="text-sm text-slate-400">L'étiquette dépend uniquement du rôle du compte : un élève ne peut jamais l'obtenir, ni l'imiter avec son nom. Les modérateurs gardent l'étiquette « Équipe ».</p>

            <fieldset class="space-y-2">
                <legend class="field-label">Compte du formateur principal (étiquette dorée)</legend>
                @foreach ($admins as $admin)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-200 ring-1 ring-night-600 has-[:checked]:bg-gold-500/10 has-[:checked]:ring-gold-500">
                        <input type="radio" name="principal_user_id" value="{{ $admin->id }}" @checked((int) old('principal_user_id', $principalId) === $admin->id) class="size-4 accent-amber-500">
                        <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-white">{{ $admin->name }}</span><span class="block truncate text-xs text-slate-400">{{ $admin->email }}</span></span>
                    </label>
                @endforeach
                @if ($badgeErrors->has('principal_user_id'))
                    <p class="text-sm text-rose-300">{{ $badgeErrors->first('principal_user_id') }}</p>
                @endif
                <p class="text-xs text-slate-400">Les autres administrateurs reçoivent l'étiquette violette.</p>
            </fieldset>

            <x-admin.field name="principal_label" label="Étiquette du formateur principal" :value="\App\Chat\ChatBadges::principalLabel()" bag="badges" maxlength="{{ \App\Chat\ChatBadges::LABEL_MAX_LENGTH }}" />
            <x-admin.field name="admin_label" label="Étiquette des autres administrateurs" :value="\App\Chat\ChatBadges::adminLabel()" bag="badges" maxlength="{{ \App\Chat\ChatBadges::LABEL_MAX_LENGTH }}" />

            <div class="space-y-1.5">
                <label for="badges-password" class="field-label">Votre mot de passe pour confirmer</label>
                <input id="badges-password" name="current_password" type="password" required autocomplete="current-password" @class(['field-input', 'field-input-error' => $badgeErrors->has('current_password')])>
                @if ($badgeErrors->has('current_password'))
                    <p class="text-sm text-rose-300">{{ $badgeErrors->first('current_password') }}</p>
                @endif
            </div>

            <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer les étiquettes</button>
        </form>

        <h2 class="mt-8 text-lg font-bold text-white">Administrateurs</h2>
        <ul class="card mt-3 divide-y divide-veil/[0.06]">
            @foreach ($admins as $admin)
                <li class="flex items-center gap-3 px-4 py-3">
                    <x-site.avatar :user="$admin" class="size-10 text-xs" />
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-1.5"><span class="truncate font-semibold text-white">{{ $admin->name }}</span> <span class="{{ \App\Chat\ChatBadges::classes($admin->chatBadgeKind()) }}">{{ $admin->chatBadge() }}</span></p>
                        <p class="truncate text-xs text-slate-400">{{ $admin->email }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts::site>
