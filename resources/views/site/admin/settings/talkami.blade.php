@php
    use App\Settings\SiteSettings;
    use App\Support\Talkami;

    $talkami = (array) SiteSettings::get('site.talkami');
    $enabled = (bool) old('enabled', $talkami['enabled'] ?? true);
    $plans = array_values((array) old('plans', $talkami['plans'] ?? []));
    // Toujours au moins 4 lignes : les lignes vides sont ignorées à l'enregistrement.
    while (count($plans) < 4) {
        $plans[] = ['name' => '', 'price' => '', 'period' => '', 'detail' => ''];
    }
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm text-slate-300">
        <p>Talkami, votre plateforme pour apprendre à parler anglais : section de l'accueil, page Talkami, lien du menu et mention dans À propos. Les textes de présentation reprennent les faits que vous avez fournis ; dites-moi s'ils changent.</p>
        <a href="{{ route('talkami') }}" target="_blank" class="btn-ghost !px-4 !py-2 !text-sm">Voir la page <flux:icon.arrow-up-right class="size-4" /></a>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-5 p-5 sm:p-6">
            <label class="flex cursor-pointer items-start justify-between gap-4" data-test="talkami-toggle">
                <span class="min-w-0">
                    <span class="block font-semibold text-white">Afficher Talkami sur le site</span>
                    <span class="block text-sm text-slate-400">Éteint : la section de l'accueil, la page, le lien du menu et la mention d'À propos disparaissent.</span>
                </span>
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($enabled) class="peer sr-only">
                <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
            </label>
            <x-admin.field name="url" label="Lien vers Talkami" type="url" :value="$talkami['url'] ?? Talkami::DEFAULT_URL" hint="https://talkami.net/… seulement. Le « ?utm_source=lome-ia-school » vous permet de compter les visites venues de l'école." />
            <x-admin.field name="whatsapp" label="Numéro WhatsApp de Talkami" :value="$talkami['whatsapp'] ?? ''" hint="Avec l'indicatif (ex. +228 72 24 02 31). Vide : pas de bouton WhatsApp sur la page Talkami." />
        </section>

        <section class="card space-y-4 p-5 sm:p-6" data-test="talkami-plans-editor">
            <h2 class="font-semibold text-white">Formules</h2>
            <p class="text-sm text-slate-400">Une ligne par formule. Une ligne sans nom ou sans prix est ignorée.</p>
            @foreach ($plans as $index => $plan)
                <div class="grid gap-3 rounded-2xl bg-veil/5 p-3 ring-1 ring-veil/10 sm:grid-cols-4">
                    <x-admin.field :name="'plans['.$index.'][name]'" label="Nom" :value="$plan['name'] ?? ''" placeholder="Essentiel" />
                    <x-admin.field :name="'plans['.$index.'][price]'" label="Prix (FCFA)" :value="$plan['price'] ?? ''" inputmode="numeric" placeholder="7500" />
                    <x-admin.field :name="'plans['.$index.'][period]'" label="Période" :value="$plan['period'] ?? ''" placeholder="par mois" />
                    <x-admin.field :name="'plans['.$index.'][detail]'" label="Détail" :value="$plan['detail'] ?? ''" placeholder="15 minutes par jour" />
                </div>
            @endforeach
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
