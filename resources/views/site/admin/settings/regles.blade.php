@php
    use App\Settings\SiteSettings;
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Paiements</h2>
            <x-admin.field name="verification_threshold" label="Seuil d'accès provisoire" type="number" unit="FCFA" :value="SiteSettings::get('site.payments.verification_threshold')" hint="En dessous de ce montant (réellement payé, remise comprise), l'accès s'ouvre aussitôt à titre provisoire. À partir de ce montant, il s'ouvre après votre vérification. Les textes du site sont mis à jour automatiquement." />
            <x-admin.field name="verification_hours" label="Délai de vérification annoncé" :value="SiteSettings::get('site.payments.verification_hours')" hint="Exemple : du lundi au samedi, de 8 h à 20 h" />
            <x-admin.field name="fees_notice" label="Mention sur les frais de transfert" :value="SiteSettings::get('site.payments.fees_notice')" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Sessions en direct et à Lomé</h2>
            <x-admin.field name="default_location" label="Lieu proposé par défaut (présentiel)" :value="SiteSettings::get('live_sessions.default_location')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="default_duration" label="Durée proposée par défaut" type="number" unit="min" :value="SiteSettings::get('live_sessions.default_duration')" />
                <x-admin.field name="cancel_deadline_hours" label="Annulation par l'élève jusqu'à" type="number" unit="h avant" :value="SiteSettings::get('live_sessions.cancel_deadline_hours')" />
                <x-admin.field name="join_window_minutes" label="Bouton « Rejoindre » actif" type="number" unit="min avant" :value="SiteSettings::get('live_sessions.join_window_minutes')" />
                <x-admin.field name="payment_hold_hours" label="Délai pour payer un supplément" type="number" unit="h" :value="SiteSettings::get('live_sessions.payment_hold_hours')" />
            </div>
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
