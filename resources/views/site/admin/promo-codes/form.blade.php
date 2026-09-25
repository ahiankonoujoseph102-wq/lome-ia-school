@php
    use App\Models\PromoCode;

    $editing = $promo->exists;
    $selected = collect(old('formations', $promo->formations?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
@endphp

<x-admin.page :title="$editing ? 'Code '.$promo->code : 'Nouveau code promo'" :back="route('admin.promo-codes.index')" back-label="Codes promo">
    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.promo-codes.update', $promo) : route('admin.promo-codes.store') }}" class="space-y-6" x-data="{ type: @js(old('type', $promo->type)), all: @js((bool) old('all_formations', $promo->all_formations)) }">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <section class="card space-y-4 p-5 sm:p-6">
            <x-admin.field name="code" label="Code" :value="$promo->code" class="font-mono uppercase" placeholder="RENTREE2026" hint="Lettres, chiffres, - et _. L'élève le tape sur le récapitulatif de commande (majuscules ou minuscules)." />
            <x-admin.field name="description" label="Description interne (facultative)" :value="$promo->description" placeholder="Exemple : offre de rentrée partagée sur WhatsApp" />
            <fieldset class="space-y-2">
                <legend class="field-label">Type de réduction</legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (PromoCode::TYPES as $value => $label)
                        <label class="flex cursor-pointer items-center justify-center rounded-2xl px-3 py-3 text-center text-sm ring-1 ring-veil/15" :class="type === '{{ $value }}' ? 'bg-gold-400/15 text-gold-100 ring-2 ring-gold-400' : 'text-slate-200'">
                            <input type="radio" name="type" value="{{ $value }}" x-model="type" class="sr-only"> {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="space-y-1.5">
                <label for="champ-value" class="field-label">Réduction</label>
                <div class="flex items-center gap-2">
                    <input id="champ-value" name="value" type="number" inputmode="numeric" min="1" value="{{ old('value', $promo->value) }}" @class(['field-input', 'field-input-error' => $errors->has('value')])>
                    <span class="shrink-0 text-sm text-slate-400" x-text="type === 'percent' ? '%' : 'FCFA'"></span>
                </div>
                @error('value')<p class="text-sm text-rose-300">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Validité et limites</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="starts_at" label="Début (facultatif)" type="datetime-local" :value="$promo->starts_at?->format('Y-m-d\TH:i')" hint="Heure de Lomé." />
                <x-admin.field name="ends_at" label="Fin (facultative)" type="datetime-local" :value="$promo->ends_at?->format('Y-m-d\TH:i')" />
                <x-admin.field name="max_uses" label="Utilisations au total (vide : sans limite)" type="number" :value="$promo->max_uses" />
                <x-admin.field name="max_uses_per_user" label="Utilisations par élève (vide : sans limite)" type="number" :value="$promo->max_uses_per_user" />
            </div>
        </section>

        <section class="card space-y-3 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Formations concernées</h2>
            <input type="hidden" name="all_formations" :value="all ? 1 : 0">
            <div class="grid grid-cols-2 gap-2">
                <button type="button" @click="all = true" class="rounded-2xl px-3 py-3 text-sm ring-1 ring-veil/15" :class="all ? 'bg-gold-400/15 text-gold-100 ring-2 ring-gold-400' : 'text-slate-200'">Toutes les formations</button>
                <button type="button" @click="all = false" class="rounded-2xl px-3 py-3 text-sm ring-1 ring-veil/15" :class="! all ? 'bg-gold-400/15 text-gold-100 ring-2 ring-gold-400' : 'text-slate-200'">Certaines formations</button>
            </div>
            <div x-show="! all" x-cloak class="space-y-2">
                @foreach ($formations as $formation)
                    <label class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-slate-200 ring-1 ring-veil/10">
                        <input type="checkbox" name="formations[]" value="{{ $formation->id }}" class="size-5 accent-gold-400" @checked(in_array($formation->id, $selected, true))>
                        <span class="min-w-0 flex-1">{{ $formation->title }}</span>
                        <span class="shrink-0 text-slate-400">{{ $formation->formatted_price }}</span>
                    </label>
                @endforeach
                @error('formations')<p class="text-sm text-rose-300">{{ $message }}</p>@enderror
            </div>
        </section>

        <label class="card flex items-center gap-3 p-4 text-sm text-slate-200">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="size-5 accent-gold-400" @checked(old('is_active', $promo->is_active ?? true))> Code actif
        </label>

        <button type="submit" class="btn-gold w-full sm:w-auto">{{ $editing ? 'Enregistrer' : 'Créer le code' }}</button>
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('admin.promo-codes.destroy', $promo) }}" onsubmit="return confirm('Supprimer ce code promo ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-sm font-semibold text-rose-200 hover:text-rose-100">Supprimer ce code</button>
        </form>
    @endif
</x-admin.page>
