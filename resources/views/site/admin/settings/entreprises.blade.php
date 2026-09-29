@php
    use App\Settings\SiteSettings;

    $business = (array) SiteSettings::get('site.business');
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm text-slate-300">
        <p>Page « Entreprises et centres de formation », visible dans le menu, le pied de page et sur l'accueil.</p>
        <a href="{{ route('business') }}" target="_blank" class="btn-ghost !px-4 !py-2 !text-sm">Voir la page <flux:icon.arrow-up-right class="size-4" /></a>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Vidéo de présentation</h2>
            <x-admin.field name="video_url" label="Lien de la vidéo YouTube" type="url" :value="$business['video_url'] ?? ''" placeholder="https://www.youtube.com/watch?v=…" hint="Laissez vide pour masquer le bloc vidéo. Vidéo publique ou non répertoriée." />
            <x-admin.field name="transcript" label="Transcription de la vidéo" type="textarea" :rows="10" :value="$business['transcript'] ?? ''" hint="Collez ici le texte de la vidéo. Affiché sous la vidéo dans un bloc « Lire la transcription ». Laissez une ligne vide entre deux paragraphes. Vide = bloc masqué." />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Présentation</h2>
            <x-admin.field name="title" label="Titre de la page" type="textarea" :rows="2" :value="$business['title'] ?? ''" />
            <x-admin.list-editor name="intro" label="Texte d'introduction (un paragraphe par ligne)" :items="$business['intro'] ?? []" add-label="Ajouter un paragraphe" multiline />
            <x-admin.list-editor name="services" label="Services" :items="$business['services'] ?? []" add-label="Ajouter un service" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Bouton WhatsApp</h2>
            <x-admin.field name="whatsapp_number" label="Numéro WhatsApp" :value="'+'.ltrim((string) ($business['whatsapp_number'] ?? ''), '+')" hint="Format international, ex. +228 72 24 02 31." />
            <x-admin.field name="whatsapp_message" label="Message prérempli" type="textarea" :rows="3" :value="$business['whatsapp_message'] ?? ''" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Bloc de l'accueil</h2>
            <x-admin.field name="home_title" label="Titre" :value="$business['home_title'] ?? ''" />
            <x-admin.field name="home_text" label="Texte" type="textarea" :rows="3" :value="$business['home_text'] ?? ''" />
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer la page Entreprises</button>
    </form>
</x-admin.page>
