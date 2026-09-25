@php
    use App\Settings\SiteSettings;
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <p class="text-sm text-slate-400">Les pages publiques (accueil, « Devenez affilié », FAQ, CGV) et l'espace affilié affichent automatiquement ces valeurs. Une modification ne change pas les commissions déjà créées.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="rate" label="Commission" type="number" unit="%" :value="SiteSettings::get('affiliate.rate')" hint="Calculée sur le montant réellement payé." />
                <x-admin.field name="cookie_days" label="Durée de validité d'un lien" type="number" unit="jours" :value="SiteSettings::get('affiliate.cookie_days')" />
                <x-admin.field name="availability_days" label="Commission disponible après" type="number" unit="jours" :value="SiteSettings::get('affiliate.availability_days')" />
                <x-admin.field name="min_withdrawal" label="Retrait minimum" type="number" unit="FCFA" :value="SiteSettings::get('affiliate.min_withdrawal')" />
                <x-admin.field name="alert_signups_without_purchase" label="Alerte : inscriptions sans achat à partir de" type="number" :value="SiteSettings::get('affiliate.alert_signups_without_purchase')" />
            </div>
        </section>

        <x-admin.password-confirm />

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
