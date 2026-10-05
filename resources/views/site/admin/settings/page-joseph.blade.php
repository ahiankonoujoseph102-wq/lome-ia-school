@php
    use App\Settings\SiteSettings;
    use App\Support\Founder;

    $founder = (array) SiteSettings::get('site.founder');
    $uploadedPhoto = \App\Support\FileStorage::publicUrl($founder['photo'] ?? null);
    $photo = Founder::photoUrl();
    $services = array_map(fn ($service) => ['question' => (string) ($service['title'] ?? ''), 'answer' => (string) ($service['text'] ?? '')], (array) ($founder['services'] ?? []));
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm text-slate-300">
        <p>Votre page personnelle, pour que votre nom ressorte sur Google. Elle est reliée à Lomé IA School (vous en êtes le fondateur), à la page À propos et à la signature en bas de toutes les pages. N'y écrivez que des faits vrais : ni témoignage, ni chiffre, ni client, ni récompense inventés.</p>
        <a href="{{ route('founder') }}" target="_blank" class="btn-ghost !px-4 !py-2 !text-sm">Voir la page <flux:icon.arrow-up-right class="size-4" /></a>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Google et haut de page</h2>
            <x-admin.field name="title" label="Titre de la page" type="textarea" :rows="2" :value="$founder['title'] ?? ''" hint="Affiché en grand en haut de la page et utilisé tel quel comme titre dans Google (60 à 70 caractères conseillés)." />
            <x-admin.field name="description" label="Description pour Google" type="textarea" :rows="3" :value="$founder['description'] ?? ''" hint="Texte que Google peut afficher sous le titre (environ 155 caractères)." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="name" label="Nom" :value="$founder['name'] ?? ''" />
                <x-admin.field name="nickname" label="Surnom" :value="$founder['nickname'] ?? ''" />
            </div>
            <x-admin.field name="job_title" label="Métier" :value="$founder['job_title'] ?? ''" hint="Envoyé à Google dans la fiche « Person » (données structurées)." />
            <x-admin.field name="tagline" label="Phrase sous le titre" type="textarea" :rows="2" :value="$founder['tagline'] ?? ''" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6" data-test="founder-photo-field">
            <h2 class="font-semibold text-white">Photo</h2>
            <p class="text-sm text-slate-400">Photo carrée, recadrée au centre en {{ Founder::PHOTO_SIZE }} × {{ Founder::PHOTO_SIZE }} et allégée automatiquement. Aussi utilisée dans l'image de partage WhatsApp et Facebook. Sans photo envoyée, la photo du formateur de la page À propos est utilisée.</p>
            <div class="flex items-end gap-4">
                <div class="size-24 shrink-0 overflow-hidden rounded-2xl bg-night-900 shadow-[0_0_0_1px_var(--color-night-600)]">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="Photo actuelle" class="size-full object-cover" width="96" height="96">
                    @endif
                </div>
                <p class="text-sm text-slate-400">{{ $uploadedPhoto ? 'Photo envoyée depuis l\'administration.' : 'Aucune photo envoyée : photo de la page À propos.' }}</p>
            </div>
            <x-admin.field name="photo" label="Nouvelle photo" type="file" accept="image/png,image/jpeg,image/webp" hint="JPEG, PNG ou WebP, 8 Mo au maximum." />
            @if ($uploadedPhoto)
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="hidden" name="remove_photo" value="0"><input type="checkbox" name="remove_photo" value="1" class="size-4 accent-gold-400"> Retirer la photo envoyée</label>
            @endif
            <x-admin.field name="photo_alt" label="Description de la photo (pour Google et les lecteurs d'écran)" :value="$founder['photo_alt'] ?? ''" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Présentation et parcours</h2>
            <x-admin.list-editor name="intro" label="Présentation (un paragraphe par ligne)" :items="$founder['intro'] ?? []" add-label="Ajouter un paragraphe" multiline />
            <x-admin.list-editor name="journey" label="Parcours (une étape par ligne)" :items="$founder['journey'] ?? []" add-label="Ajouter une étape" multiline />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Livres et services</h2>
            <x-admin.list-editor name="books" label="Livres (un titre par ligne)" :items="$founder['books'] ?? []" add-label="Ajouter un livre" />
            <x-admin.questions-editor name="services" label="Services" :items="$services" question-label="Service" answer-label="Description" add-label="Ajouter un service" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6" data-test="founder-links-field">
            <h2 class="font-semibold text-white">Vos liens</h2>
            <p class="text-sm text-slate-400">Collez l'adresse de chaque page. Un champ vide n'apparaît pas sur le site. Ces liens sont aussi donnés à Google pour relier vos comptes à votre nom.</p>
            @foreach (Founder::NETWORKS as $network => $meta)
                <x-admin.field :name="'links['.$network.']'" :label="$meta['label']" type="url" :value="$founder['links'][$network] ?? ''" :placeholder="$meta['example']" />
            @endforeach
            <x-admin.list-editor name="whatsapp_channels" label="Chaînes WhatsApp" :items="$founder['whatsapp_channels'] ?? []" add-label="Ajouter une chaîne" :placeholder="Founder::CHANNEL_EXAMPLE" hint="Dans WhatsApp : la chaîne > Partager > Copier le lien. 5 chaînes au maximum." />
            @error('whatsapp_channels') <p class="text-sm text-rose-300">{{ $message }}</p> @enderror
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Contact</h2>
            <x-admin.field name="contact[whatsapp]" label="Numéro WhatsApp" :value="$founder['contact']['whatsapp'] ?? ''" placeholder="+228 72 24 02 31" />
            <x-admin.field name="contact[email]" label="E-mail" type="email" :value="$founder['contact']['email'] ?? ''" />
            <x-admin.field name="contact[office]" label="Bureau" :value="$founder['contact']['office'] ?? ''" />
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer la page</button>
    </form>
</x-admin.page>
