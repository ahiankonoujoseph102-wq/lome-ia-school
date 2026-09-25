@php
    use App\Settings\SiteSettings;
    use App\Support\Brand;

    $brand = (array) SiteSettings::get('site.brand');
    $defaults = (array) SiteSettings::default('site.brand');
    $custom = fn (string $key): bool => ($brand[$key] ?? null) !== ($defaults[$key] ?? null);
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Logo (en-tête et pied de page)</h2>
            <div class="flex items-center gap-4 rounded-2xl bg-night-950 p-4 ring-1 ring-veil/10">
                <x-site.logo height="h-10" />
            </div>
            <p class="text-sm text-slate-400">{{ $custom('logo') ? 'Logo envoyé depuis l\'administration.' : 'Logo d\'origine de Lomé IA School.' }}</p>
            <x-admin.field name="logo" label="Nouveau logo horizontal" type="file" accept="image/png,image/jpeg,image/webp" hint="PNG à fond transparent, texte clair (le site est toujours sombre). Il est réduit automatiquement, sans être recadré." />
            @if ($custom('logo'))
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="reset[]" value="logo" class="size-4 accent-gold-400"> Revenir au logo d'origine</label>
            @endif
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Icône (onglet du navigateur, écran d'accueil du téléphone)</h2>
            <div class="flex items-end gap-4">
                <img src="{{ Brand::icon('icon-192.png') }}" alt="Icône actuelle" class="size-20 rounded-2xl ring-1 ring-veil/15" width="80" height="80">
                <img src="{{ Brand::icon('icon-maskable-192.png') }}" alt="Icône découpée en cercle" class="size-14 rounded-full ring-1 ring-veil/15" width="56" height="56">
                <img src="{{ Brand::icon('favicon-32.png') }}" alt="Icône de l'onglet" class="size-8" width="32" height="32">
            </div>
            <x-admin.field name="icon" label="Nouvelle icône" type="file" accept="image/png,image/jpeg,image/webp" hint="Carrée, au moins 512 × 512 px. Toutes les tailles utiles (favicon, écran d'accueil, notifications) sont créées automatiquement." />
            @if ($custom('icons'))
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="reset[]" value="icons" class="size-4 accent-gold-400"> Revenir à l'icône d'origine</label>
            @endif
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Image de partage</h2>
            <p class="text-sm text-slate-400">Affichée quand le lien du site est partagé sur WhatsApp ou Facebook. Une image envoyée est recadrée automatiquement en 1200 × 630.</p>
            <img src="{{ route('og.site') }}" alt="Image de partage actuelle" class="w-full rounded-2xl ring-1 ring-veil/10" loading="lazy" width="1200" height="630">
            <x-admin.field name="og_image" label="Nouvelle image de partage" type="file" accept="image/png,image/jpeg,image/webp" />
            @if ($custom('og_image'))
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="reset[]" value="og_image" class="size-4 accent-gold-400"> Revenir à l'image d'origine (logo vertical)</label>
            @endif
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
