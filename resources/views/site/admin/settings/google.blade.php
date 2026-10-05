@php
    use App\Settings\SiteSettings;
    use App\Support\SearchConsole;

    $code = (string) (((array) SiteSettings::get('site.seo'))['google_verification'] ?? '');
    $sitemap = SearchConsole::sitemapUrl();
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Vérification Google Search Console</h2>
            <p class="text-sm text-slate-400">Dans Google Search Console, ajoutez une propriété « Préfixe de l'URL » avec l'adresse <span class="font-semibold text-slate-200 break-all">{{ \App\Support\AppUrl::to(route('home')) }}</span>, choisissez la méthode « Balise HTML », copiez la balise et collez-la ici (la balise entière ou seulement le code). Enregistrez, puis cliquez sur « Valider » dans Search Console. Ce code n'est pas un secret.</p>
            <x-admin.field name="google_verification" label="Code de vérification Google Search Console" :value="$code" placeholder='<meta name="google-site-verification" content="…" />' hint="Laissez vide pour retirer la balise." data-test="google-verification-field" />
            @if (SearchConsole::code())
                <x-site.alert type="success">Balise de vérification présente dans toutes les pages du site.</x-site.alert>
            @endif
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>

    <section class="card space-y-3 p-5 sm:p-6" data-test="sitemap-address">
        <h2 class="font-semibold text-white">Plan du site à déclarer</h2>
        <p class="text-sm text-slate-400">Une fois le site validé, ouvrez « Sitemaps » dans Search Console et collez cette adresse :</p>
        <p class="rounded-xl bg-night-900 px-4 py-3 font-mono text-sm break-all text-gold-200 shadow-[0_0_0_1px_var(--color-night-600)]">{{ $sitemap }}</p>
    </section>
</x-admin.page>
