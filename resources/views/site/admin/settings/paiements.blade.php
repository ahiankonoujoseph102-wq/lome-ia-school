@php
    use App\Http\Controllers\Admin\SiteSettingsController;
    use App\Settings\SiteSettings;

    $countries = collect((array) SiteSettings::get('site.payments.countries'))
        ->map(fn ($country, $code) => ['code' => (string) $code, 'name' => $country['name'] ?? '', 'flag' => $country['flag'] ?? '', 'dial_code' => $country['dial_code'] ?? '', 'note' => $country['note'] ?? ''])
        ->values()->all();
    $methods = collect((array) SiteSettings::get('site.payments.methods'))
        ->map(fn ($method, $key) => ['key' => (string) $key, 'country' => $method['country'] ?? '', 'name' => $method['name'] ?? '', 'number' => $method['number'] ?? '', 'beneficiary' => $method['beneficiary'] ?? '', 'color' => $method['color'] ?? '#8b5cf6', 'enabled' => (bool) ($method['enabled'] ?? true)])
        ->values()->all();
    $countries = old('countries', $countries);
    $methods = collect(old('methods', $methods))->map(fn ($method) => [...$method, 'enabled' => (bool) ($method['enabled'] ?? false)])->all();
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6" x-data="{
        countries: @js(array_values($countries)),
        methods: @js(array_values($methods)),
        palette: @js(SiteSettingsController::palette()),
        addMethod() { this.methods.push({ key: '', country: this.countries[0]?.code ?? '', name: '', number: '', beneficiary: '', color: this.palette[this.methods.length % this.palette.length], enabled: true }); },
        addCountry() { this.countries.push({ code: '', name: '', flag: '', dial_code: '+', note: '' }); },
    }">
        @csrf
        @method('PUT')

        <section class="space-y-3">
            <h2 class="font-semibold text-white">Numéros Mobile Money</h2>
            <p class="text-sm text-slate-400">Vérifiez chaque numéro et le nom exact du bénéficiaire : l'élève les compare avant de payer. Un moyen désactivé n'est plus proposé mais reste visible sur les anciens achats.</p>

            <template x-for="(method, index) in methods" :key="index">
                <div class="card p-4 sm:p-5" x-data="{ open: ! method.key }">
                    <div class="flex items-center gap-3">
                        <span class="size-9 shrink-0 rounded-full ring-2 ring-veil/20" :style="`background:${method.color}`" aria-hidden="true"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-white" x-text="method.name || 'Nouveau moyen de paiement'"></p>
                            <p class="truncate font-mono text-sm text-slate-300" x-text="method.number"></p>
                            <p class="truncate text-xs text-slate-400" x-text="method.beneficiary"></p>
                        </div>
                        <label class="inline-flex shrink-0 items-center gap-2 text-sm text-slate-200">
                            <input type="checkbox" value="1" class="size-5 accent-gold-400" :name="`methods[${index}][enabled]`" x-model="method.enabled"> Actif
                        </label>
                    </div>
                    <button type="button" x-show="! open" @click="open = true" class="mt-3 rounded-full px-3 py-1.5 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10">Modifier</button>
                    <input type="hidden" :name="`methods[${index}][key]`" :value="method.key">
                    <div x-show="open" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="space-y-1.5"><span class="field-label">Pays</span>
                            {{-- Options construites en JavaScript : un <template> dans un <select> casserait la page. --}}
                            <select class="field-input" :name="`methods[${index}][country]`" @change="method.country = $event.target.value" x-effect="$el.replaceChildren(...countries.map(country => new Option(`${country.flag} ${country.name}`, country.code))); $el.value = method.country"></select>
                        </label>
                        <label class="space-y-1.5"><span class="field-label">Nom (Mixx by Yas, Flooz…)</span>
                            <input type="text" class="field-input" :name="`methods[${index}][name]`" x-model="method.name" required>
                        </label>
                        <label class="space-y-1.5"><span class="field-label">Numéro</span>
                            <input type="text" inputmode="tel" class="field-input font-mono" :name="`methods[${index}][number]`" x-model="method.number" required>
                        </label>
                        <label class="space-y-1.5"><span class="field-label">Nom exact du bénéficiaire</span>
                            <input type="text" class="field-input" :name="`methods[${index}][beneficiary]`" x-model="method.beneficiary" required>
                        </label>
                        <label class="flex items-center gap-3"><span class="field-label">Couleur de la pastille</span>
                            <input type="color" class="h-10 w-16 rounded-lg bg-transparent" :name="`methods[${index}][color]`" x-model="method.color">
                        </label>
                        <button type="button" x-show="! method.key" @click="methods.splice(index, 1)" class="justify-self-start text-sm font-semibold text-rose-200 hover:text-rose-100">Retirer cette ligne</button>
                    </div>
                </div>
            </template>
            <button type="button" @click="addMethod()" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10"><flux:icon.plus class="size-4" /> Ajouter un numéro</button>
        </section>

        <section class="space-y-3">
            <h2 class="font-semibold text-white">Pays</h2>
            <p class="text-sm text-slate-400">« Mention du représentant » : affichée à l'élève et rappelée sur « Paiements à vérifier » (à confirmer avec le représentant). Laissez-la vide si le paiement vous arrive directement.</p>
            <template x-for="(country, index) in countries" :key="index">
                <div class="card grid gap-3 p-4 sm:grid-cols-2 sm:p-5">
                    <label class="space-y-1.5"><span class="field-label">Nom du pays</span>
                        <input type="text" class="field-input" :name="`countries[${index}][name]`" x-model="country.name" required>
                    </label>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="space-y-1.5"><span class="field-label">Code</span>
                            <input type="text" maxlength="2" class="field-input lowercase" :name="`countries[${index}][code]`" x-model="country.code" required>
                        </label>
                        <label class="space-y-1.5"><span class="field-label">Drapeau</span>
                            <input type="text" class="field-input" :name="`countries[${index}][flag]`" x-model="country.flag">
                        </label>
                        <label class="space-y-1.5"><span class="field-label">Indicatif</span>
                            <input type="text" class="field-input" :name="`countries[${index}][dial_code]`" x-model="country.dial_code" required>
                        </label>
                    </div>
                    <label class="space-y-1.5 sm:col-span-2"><span class="field-label">Mention du représentant (facultatif)</span>
                        <input type="text" class="field-input" :name="`countries[${index}][note]`" x-model="country.note" placeholder="Paiement reçu par notre représentant au …">
                    </label>
                </div>
            </template>
            <button type="button" @click="addCountry()" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-electric-200 ring-1 ring-electric-400/40 hover:bg-electric-500/10"><flux:icon.plus class="size-4" /> Ajouter un pays</button>
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <x-admin.field name="admin_email" label="Adresse qui reçoit les alertes" type="email" :value="SiteSettings::get('site.payments.admin_email')" hint="Nouveaux paiements à vérifier, retraits, consultations et alertes de sécurité. Si vous la changez, l'ancienne adresse est aussi prévenue." />
        </section>

        <x-admin.password-confirm />

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer les moyens de paiement</button>
    </form>
</x-admin.page>
