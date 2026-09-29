@php
    use App\Settings\SiteSettings;
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card space-y-2 p-4 text-sm text-slate-300">
        <p>Ces deux informations apparaissent dans les <a href="{{ route('legal.notice') }}" class="font-semibold text-electric-200 underline" target="_blank">mentions légales</a> et la <a href="{{ route('legal.privacy') }}" class="font-semibold text-electric-200 underline" target="_blank">politique de confidentialité</a>.</p>
        <p class="text-slate-400">À mettre à jour le jour du déménagement du site (par exemple vers Hostinger).</p>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <x-admin.field name="host" label="Hébergeur du site" type="textarea" :rows="2" :value="SiteSettings::get('site.legal.host')" hint="Nom de l'hébergeur et son site internet. Ex. : Laravel Cloud (https://cloud.laravel.com)." />
            <x-admin.field name="data_location" label="Lieu de stockage des données" :value="SiteSettings::get('site.legal.data_location')" hint="Ex. : Europe (Londres, Royaume-Uni)." />
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
