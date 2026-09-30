@php
    use App\Settings\SiteSettings;

    $contact = (array) SiteSettings::get('site.contact');
    $whatsapp = (array) SiteSettings::get('site.whatsapp');
    $social = (array) SiteSettings::get('site.social');
    $stats = (array) SiteSettings::get('site.stats');
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Coordonnées</h2>
            <x-admin.list-editor name="phones" label="Numéros de téléphone" :items="$contact['phones'] ?? []" add-label="Ajouter un numéro" placeholder="+228 72 24 02 31" hint="Le premier numéro est affiché en priorité." />
            <x-admin.field name="email" label="Adresse e-mail" type="email" :value="$contact['email'] ?? ''" />
            <x-admin.field name="address" label="Adresse" :value="$contact['address'] ?? ''" />
            <x-admin.field name="map_url" label="Lien de la carte (facultatif)" type="url" :value="$contact['map_url'] ?? ''" hint="Lien Google Maps. Vide : un lien est créé à partir de l'adresse." />
            <x-admin.field name="hours" label="Horaires" :value="$contact['hours'] ?? ''" hint="Exemple : Du lundi au samedi, de 8 h à 20 h" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">WhatsApp</h2>
            <x-admin.field name="whatsapp_number" label="Numéro WhatsApp principal" :value="'+'.ltrim((string) ($whatsapp['number'] ?? ''), '+')" hint="Utilisé par tous les boutons WhatsApp du site." />
            <x-admin.field name="whatsapp_message" label="Message pré-rempli (informations)" type="textarea" :rows="2" :value="$whatsapp['message'] ?? ''" />
            <x-admin.field name="consultation_message" label="Message pré-rempli (consultation)" type="textarea" :rows="2" :value="$whatsapp['consultation_message'] ?? ''" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Réseaux sociaux</h2>
            <p class="text-sm text-slate-400">Adresse complète commençant par https://. Un réseau laissé vide est masqué sur le site.</p>
            <x-admin.field name="social[facebook]" label="Facebook" type="url" :value="$social['facebook'] ?? ''" placeholder="https://www.facebook.com/…" />
            <x-admin.field name="social[tiktok]" label="TikTok" type="url" :value="$social['tiktok'] ?? ''" placeholder="https://www.tiktok.com/@…" />
            <x-admin.field name="social[youtube]" label="YouTube" type="url" :value="$social['youtube'] ?? ''" placeholder="https://www.youtube.com/@…" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Chiffres clés</h2>
            <p class="text-sm text-slate-400">Affichés sur l'accueil et la page À propos. Uniquement des chiffres réels.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="trained" label="Personnes formées" :value="$stats[0]['value'] ?? ''" hint="Exemple : 1 000+" />
                <x-admin.field name="trainer_since" label="Année où Joseph a commencé à former" type="number" :value="$stats[1]['value'] ?? ''" />
            </div>
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer</button>
    </form>
</x-admin.page>
