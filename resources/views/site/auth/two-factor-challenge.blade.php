<x-layouts::site title="Code de sécurité" noindex>
    <div x-data="{ recovery: @js($errors->has('recovery_code')) }">
        <x-site.auth-card title="Vérification en deux étapes">
            <p class="-mt-2 mb-5 text-center text-sm text-slate-400" x-show="!recovery">Saisissez le code à 6 chiffres affiché dans votre application d'authentification.</p>
            <p class="-mt-2 mb-5 text-center text-sm text-slate-400" x-show="recovery" x-cloak>Saisissez l'un de vos codes de récupération.</p>

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
                @csrf
                <div x-show="!recovery" class="space-y-1.5">
                    <label for="code" class="field-label">Code à 6 chiffres</label>
                    <input id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus
                        :disabled="recovery" placeholder="123456"
                        @class(['field-input text-center font-mono text-2xl tracking-[0.4em]', 'field-input-error' => $errors->has('code')])>
                    @error('code')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <div x-show="recovery" x-cloak class="space-y-1.5">
                    <label for="recovery_code" class="field-label">Code de récupération</label>
                    <input id="recovery_code" name="recovery_code" autocomplete="one-time-code" :disabled="!recovery" placeholder="abcde-fghij"
                        @class(['field-input font-mono', 'field-input-error' => $errors->has('recovery_code')])>
                    @error('recovery_code')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn-gold w-full">Vérifier</button>
            </form>

            <button type="button" @click="recovery = !recovery" class="mt-5 w-full text-center text-sm font-semibold text-electric-300 hover:text-white">
                <span x-show="!recovery">Utiliser un code de récupération</span>
                <span x-show="recovery" x-cloak>Utiliser le code de l'application</span>
            </button>
        </x-site.auth-card>
    </div>
</x-layouts::site>
