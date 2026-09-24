{{-- Connexion par passkey (empreinte, Face ID…) : affichée seulement si le téléphone ou le navigateur la prend en charge. --}}
<div
    x-data="{
        supported: false,
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        init() {
            this.updateSupport();
            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async verify() {
            this.loading = true;
            this.error = null;
            try {
                const response = await window.Passkeys.verify({
                    routes: {
                        options: '{{ route('passkey.login-options') }}',
                        submit: '{{ route('passkey.login') }}',
                    },
                });
                window.location.assign(response.redirect || '{{ route('dashboard') }}');
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = 'La connexion par passkey n\'a pas abouti. Réessayez ou utilisez votre mot de passe.';
                }
            } finally {
                this.loading = false;
            }
        },
    }"
>
    <template x-if="supported">
        <div>
            <button type="button" @click="verify()" :disabled="loading" class="btn-ghost w-full disabled:opacity-60">
                <flux:icon.finger-print class="size-5" />
                <span x-show="!loading">Se connecter avec une passkey</span>
                <span x-show="loading" x-cloak>Vérification…</span>
            </button>
            <p x-show="error" x-text="error" x-cloak class="mt-2 text-center text-sm text-rose-300"></p>
            <div class="my-6 flex items-center gap-3 text-xs text-slate-500 uppercase">
                <span class="h-px flex-1 bg-veil/10"></span> ou avec votre e-mail <span class="h-px flex-1 bg-veil/10"></span>
            </div>
        </div>
    </template>
</div>
