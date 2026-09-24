<x-layouts::site title="Mon profil" variant="student">
    <div class="mx-auto max-w-2xl space-y-6 px-4 pt-6 pb-12 sm:px-6 sm:pt-10">
        <div>
            <h1 class="text-2xl font-bold text-white sm:text-3xl">Mon profil</h1>
            <p class="mt-1 text-slate-400">Vos informations restent privées et servent à vous accompagner.</p>
        </div>

        @if (session('status'))
            <x-site.alert>{{ session('status') }}</x-site.alert>
        @endif

        <form
            method="POST"
            action="{{ route('student.profile.update') }}"
            enctype="multipart/form-data"
            class="card space-y-6 p-5 sm:p-7"
            x-data="{ preview: null }"
        >
            @csrf
            @method('PUT')

            {{-- Photo de profil --}}
            <div class="flex items-center gap-5">
                <div class="relative">
                    <template x-if="preview">
                        <img :src="preview" alt="Aperçu de la nouvelle photo" class="size-24 rounded-full object-cover ring-2 ring-gold-400/60">
                    </template>
                    <div x-show="!preview">
                        <x-site.avatar :user="$user" class="size-24 text-2xl" />
                    </div>
                </div>
                <div class="min-w-0 space-y-2">
                    <label for="avatar" class="btn-ghost cursor-pointer !px-4 !py-2.5 !text-sm">
                        <flux:icon.camera class="size-4" /> {{ $user->avatar_path ? 'Changer la photo' : 'Ajouter une photo' }}
                    </label>
                    <input
                        id="avatar"
                        name="avatar"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="const file = $event.target.files[0]; preview = file ? URL.createObjectURL(file) : null"
                    >
                    <p class="text-xs text-slate-500">JPG, PNG ou WebP, 4 Mo maximum.</p>
                    @error('avatar')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <x-site.field name="name" label="Nom complet" :value="old('name', $user->name)" required autocomplete="name" autocapitalize="words" />
            <x-site.phone-field :dial-code="$dialCode" :number="$localNumber" />
            <x-site.field name="email" label="Adresse e-mail" type="email" :value="old('email', $user->email)" required autocomplete="email" inputmode="email" />
            <x-site.newsletter-checkbox id="profil-newsletter" :checked="old('_token') ? old('wants_newsletter') : $user->wants_newsletter" />

            <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer les modifications</button>
        </form>

        @if ($user->avatar_path)
            <form method="POST" action="{{ route('student.profile.avatar.destroy') }}" class="-mt-2 text-right">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-rose-300">
                    <flux:icon.trash class="size-4" /> Supprimer ma photo
                </button>
            </form>
        @endif

        {{-- Sécurité (pages de paramètres existantes) --}}
        <a href="{{ route('security.edit') }}" class="card card-hover flex items-center gap-4 p-5">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-electric-500/15 text-electric-300">
                <flux:icon.lock-closed class="size-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-white">Sécurité du compte</span>
                <span class="block text-sm text-slate-400">Mot de passe, double authentification et passkeys (facultatif)</span>
            </span>
            <flux:icon.arrow-right class="size-5 text-slate-500" />
        </a>

        {{-- Suppression du compte --}}
        <details class="card group p-5" @if ($errors->deleteAccount->isNotEmpty()) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold text-slate-300">
                Supprimer mon compte
                <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" />
            </summary>
            <form method="POST" action="{{ route('student.profile.destroy') }}" class="mt-4 space-y-4">
                @csrf
                @method('DELETE')
                <p class="text-sm text-slate-400">Cette action est définitive : votre compte, vos accès aux formations et vos données seront supprimés. Saisissez votre mot de passe pour confirmer.</p>
                <div class="space-y-1.5" x-data>
                    <label for="suppression-password" class="field-label">Mot de passe</label>
                    <input id="suppression-password" name="password" type="password" required autocomplete="current-password" @class(['field-input', 'field-input-error' => $errors->deleteAccount->has('password')])>
                    @if ($errors->deleteAccount->has('password'))
                        <p class="text-sm text-rose-300">{{ $errors->deleteAccount->first('password') }}</p>
                    @endif
                </div>
                <button type="submit" class="btn w-full border border-rose-400/40 bg-rose-500/10 text-rose-200 hover:bg-rose-500/20 sm:w-auto" data-test="delete-account-button">Supprimer définitivement mon compte</button>
            </form>
        </details>
    </div>
</x-layouts::site>
