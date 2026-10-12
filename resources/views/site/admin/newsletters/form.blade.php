@php
    use App\Enums\NewsletterAudience;

    $editing = $newsletter->exists;
    $imageUrl = $newsletter->imageUrl();
@endphp

<x-admin.page :title="$editing ? 'Modifier le brouillon' : 'Nouvel e-mail'" subtitle="Texte, image, lien vidéo et bouton : l'aperçu et le test viennent juste après." :back="$editing ? route('admin.newsletters.show', $newsletter) : route('admin.newsletters.index')" :back-label="$editing ? 'Aperçu' : 'Envoi d\'e-mails'">
    @if ($errors->any())
        <x-site.alert type="error">{{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ $editing ? route('admin.newsletters.update', $newsletter) : route('admin.newsletters.store') }}" enctype="multipart/form-data" class="space-y-6" data-test="newsletter-form">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <section class="card space-y-5 p-5 sm:p-6">
            <x-admin.field name="subject" label="Objet de l'e-mail" :value="$newsletter->subject" placeholder="Ex. : Nouvelle formation disponible" />
            <x-admin.field name="body" label="Texte du message" type="textarea" rows="10" :value="$newsletter->body" hint="« Bonjour [prénom], » est ajouté automatiquement en haut. Les retours à la ligne sont gardés ; une ligne vide commence un nouveau paragraphe. Les liens écrits dans le texte deviennent cliquables." />
        </section>

        <section class="card space-y-5 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Image (facultative)</h2>
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="" class="w-full max-w-sm rounded-2xl ring-1 ring-night-600" data-test="newsletter-current-image">
            @endif
            <x-admin.field name="image" :label="$imageUrl ? 'Nouvelle image' : 'Image'" type="file" accept="image/png,image/jpeg,image/webp" hint="JPEG, PNG ou WebP, 8 Mo au plus, comme les affiches. Elle s'affiche en haut de l'e-mail, en entier (sans recadrage), réduite et compressée automatiquement." />
            @if ($imageUrl)
                <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remove_image" value="1" class="size-4 accent-gold-400"> Retirer l'image</label>
            @endif
        </section>

        <section class="card space-y-5 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Vidéo (facultative)</h2>
            <x-admin.field name="video_url" label="Lien de la vidéo" type="url" :value="$newsletter->video_url" placeholder="https://www.youtube.com/watch?v=…" hint="Lien YouTube ou adresse d'une page du site. Dans l'e-mail : une image cliquable et un bouton « Regarder la vidéo ». Aucune vidéo n'est jointe au message." />
        </section>

        <section class="card space-y-5 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Bouton d'action (facultatif)</h2>
            <x-admin.field name="button_label" label="Texte du bouton" :value="$newsletter->button_label" placeholder="Ex. : Découvrir la formation" />
            <x-admin.field name="button_url" label="Lien du bouton" type="url" :value="$newsletter->button_url" placeholder="/accompagnement" hint="Une page du site (par exemple /accompagnement) ou un lien complet commençant par https://." />
        </section>

        <section class="card space-y-3 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Destinataires</h2>
            @foreach (NewsletterAudience::cases() as $audience)
                <label class="flex items-start gap-3 rounded-2xl bg-night-900 p-3 text-sm text-slate-200 ring-1 ring-night-600">
                    <input type="radio" name="audience" value="{{ $audience->value }}" class="mt-0.5 size-4 accent-gold-400" @checked(old('audience', $newsletter->audience?->value ?? NewsletterAudience::All->value) === $audience->value)>
                    <span>{{ $audience->label() }} <strong class="text-white tabular-nums" data-test="audience-count-{{ $audience->value }}">({{ $counts[$audience->value] ?? 0 }})</strong></span>
                </label>
            @endforeach
            <p class="text-xs text-slate-400">Dans les deux cas : adresse e-mail confirmée, compte non bloqué, personne non désabonnée de ces e-mails.</p>
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer et voir l'aperçu</button>
    </form>
</x-admin.page>
