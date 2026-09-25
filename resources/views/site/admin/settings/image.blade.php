@php
    use App\Services\ImageUploader;
    use App\Settings\SiteSettings;

    $brand = (array) SiteSettings::get('site.brand');
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <form method="POST" action="{{ route('admin.settings.update', $key) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Logo</h2>
            <div class="flex items-center gap-4 rounded-2xl bg-night-950 p-4 ring-1 ring-veil/10">
                <x-site.logo />
                <span class="text-sm text-slate-400">{{ ! empty($brand['logo']) ? 'Votre logo' : 'Logo provisoire' }}</span>
            </div>
            <x-admin.field name="logo" label="Nouveau logo" type="file" accept="image/png,image/jpeg,image/webp" hint="PNG avec fond transparent de préférence. Il est réduit automatiquement." />
            @if (! empty($brand['logo']))
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remove_logo" value="1" class="size-4 accent-gold-400"> Retirer le logo (revenir au logo provisoire)</label>
            @endif
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Image de partage</h2>
            <p class="text-sm text-slate-400">Affichée quand le lien du site est partagé sur WhatsApp ou Facebook. Elle est recadrée automatiquement en 1200 × 630.</p>
            <img src="{{ ! empty($brand['og_image']) ? ImageUploader::url($brand['og_image']) : route('og.site') }}" alt="Image de partage actuelle" class="w-full rounded-2xl ring-1 ring-veil/10" loading="lazy" width="1200" height="630">
            <x-admin.field name="og_image" label="Nouvelle image de partage" type="file" accept="image/png,image/jpeg,image/webp" />
            @if (! empty($brand['og_image']))
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remove_og_image" value="1" class="size-4 accent-gold-400"> Retirer (revenir à l'image générée automatiquement)</label>
            @endif
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
