@php
    use App\Settings\SiteSettings;

    $hero = (array) SiteSettings::get('site.hero');
    $trainer = (array) SiteSettings::get('site.trainer');
    $faq = (array) SiteSettings::get('site.faq');
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card space-y-2 p-4 text-sm text-slate-300">
        <p class="font-semibold text-white">Marqueurs remplacés automatiquement</p>
        <p>Écrivez-les tels quels dans un texte : ils sont remplacés par la valeur du réglage, même si elle change plus tard.</p>
        <ul class="space-y-1">
            @foreach (SiteSettings::TOKENS as $token => $meaning)
                <li><code class="rounded bg-veil/10 px-1.5 py-0.5 text-gold-100">{{ $token }}</code> : {{ $meaning }}</li>
            @endforeach
        </ul>
        <p class="text-slate-400">Rappel : décrire ce que l'élève apprend à faire, jamais un résultat garanti.</p>
    </div>

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Accueil : accroche</h2>
            <x-admin.field name="hero[badge]" label="Petite mention au-dessus du titre" :value="$hero['badge'] ?? ''" />
            <x-admin.field name="hero[title]" label="Titre" type="textarea" :rows="2" :value="$hero['title'] ?? ''" />
            <x-admin.field name="hero[subtitle]" label="Sous-titre" type="textarea" :rows="3" :value="$hero['subtitle'] ?? ''" />
            <x-admin.list-editor name="hero[points]" label="Arguments sous les boutons" :items="$hero['points'] ?? []" add-label="Ajouter un argument" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Formateur</h2>
            <x-admin.field name="trainer[name]" label="Nom" :value="$trainer['name'] ?? ''" />
            <x-admin.field name="trainer[role]" label="Fonction" :value="$trainer['role'] ?? ''" />
            <x-admin.field name="trainer[short_bio]" label="Présentation courte (accueil)" type="textarea" :rows="3" :value="$trainer['short_bio'] ?? ''" />
            <x-admin.list-editor name="trainer[bio]" label="Biographie (un paragraphe par ligne)" :items="$trainer['bio'] ?? []" add-label="Ajouter un paragraphe" multiline />
            <x-admin.list-editor name="trainer[highlights]" label="Points forts" :items="$trainer['highlights'] ?? []" add-label="Ajouter un point fort" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Questions fréquentes de l'accueil</h2>
            <x-admin.questions-editor name="faq_home" label="Questions" :items="$faq['home'] ?? []" />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Questions fréquentes des pages formation</h2>
            <x-admin.questions-editor name="faq_formation" label="Questions" :items="$faq['formation'] ?? []" />
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer les textes</button>
    </form>
</x-admin.page>
