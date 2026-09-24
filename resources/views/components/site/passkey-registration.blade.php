{{-- Ajout d'une passkey (empreinte, Face ID, code du téléphone) : la page se recharge une fois la passkey enregistrée. --}}
<div
    x-data="{
        supported: false,
        showForm: false,
        name: '',
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        defaultName() {
            const ua = navigator.userAgent;
            const device = [
                { pattern: /iPhone/, name: 'iPhone' },
                { pattern: /iPad/, name: 'iPad' },
                { pattern: /Android/, name: 'Téléphone Android' },
                { pattern: /Mac/, name: 'Mac' },
                { pattern: /Windows/, name: 'Ordinateur Windows' },
            ].find(({ pattern }) => pattern.test(ua))?.name;
            return device || 'Mon appareil';
        },
        init() {
            this.name = this.defaultName();
            this.updateSupport();
            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async register() {
            if (!this.name.trim()) return;
            this.loading = true;
            this.error = null;
            try {
                await window.Passkeys.register({ name: this.name.trim() });
                window.location.reload();
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = 'L\'enregistrement de la passkey n\'a pas abouti. Réessayez.';
                }
                this.loading = false;
            }
        },
    }"
>
    <p x-show="!supported" class="text-sm text-slate-400">Ce navigateur ne prend pas en charge les passkeys.</p>

    <div x-show="supported && !showForm" x-cloak>
        <button type="button" @click="showForm = true" class="btn-violet !py-3 !text-sm">
            <flux:icon.plus class="size-4" /> Ajouter une passkey
        </button>
    </div>

    <div x-show="supported && showForm" x-cloak class="space-y-4 rounded-2xl border border-veil/10 bg-veil/[0.03] p-4">
        <div class="space-y-1.5">
            <label for="nom-passkey" class="field-label">Nom de la passkey</label>
            <input id="nom-passkey" x-model="name" @keydown.enter.prevent="register()" class="field-input" placeholder="Ex. : iPhone, ordinateur du bureau">
            <p class="text-xs text-slate-500">Un nom pour la reconnaître plus tard.</p>
        </div>
        <p x-show="error" x-text="error" x-cloak class="text-sm text-rose-300"></p>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="register()" :disabled="loading || !name.trim()" class="btn-gold !py-3 !text-sm disabled:opacity-60">
                <span x-show="!loading">Enregistrer la passkey</span>
                <span x-show="loading" x-cloak>Enregistrement…</span>
            </button>
            <button type="button" @click="showForm = false; error = null" class="btn-ghost !py-3 !text-sm">Annuler</button>
        </div>
    </div>
</div>
