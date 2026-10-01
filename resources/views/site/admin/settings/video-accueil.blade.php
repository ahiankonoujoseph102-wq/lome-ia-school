@php
    use App\Settings\SiteSettings;
    use App\Support\HomeVideo;
    use App\Support\YouTube;

    $video = (array) SiteSettings::get('site.home_video');
    $enabled = (bool) old('enabled', $video['enabled'] ?? true);
    $hasLink = YouTube::id($video['video_url'] ?? null) !== null;
@endphp

<x-admin.page :title="$section['title']" :back="route('admin.settings.index')" back-label="Réglages du site">
    <div class="card flex flex-wrap items-center justify-between gap-3 p-4 text-sm text-slate-300">
        <p>Vidéo verticale (comme TikTok ou Shorts) affichée sur l'accueil, après le haut de page et avant les formations. Elle ne se charge que lorsque le visiteur touche le bouton lecture.</p>
        <a href="{{ route('home') }}" target="_blank" class="btn-ghost !px-4 !py-2 !text-sm">Voir l'accueil <flux:icon.arrow-up-right class="size-4" /></a>
    </div>

    @if (! $hasLink)
        <x-site.alert type="info" data-test="home-video-hidden-note">La section est masquée sur le site tant qu'aucun lien YouTube valide n'est enregistré.</x-site.alert>
    @endif

    @if ($errors->any())
        <x-site.alert type="error">Vérifiez les champs signalés : {{ $errors->first() }}</x-site.alert>
    @endif

    <form method="POST" action="{{ route('admin.settings.update', $key) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="card space-y-5 p-5 sm:p-6">
            <label class="flex cursor-pointer items-start justify-between gap-4" data-test="home-video-toggle">
                <span class="min-w-0">
                    <span class="block font-semibold text-white">Afficher la vidéo</span>
                    <span class="block text-sm text-slate-400">Éteint : la section disparaît de l'accueil, sans effacer vos réglages.</span>
                </span>
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($enabled) class="peer sr-only">
                <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full bg-veil/10 ring-1 ring-veil/20 transition peer-checked:bg-emerald-500/30 peer-checked:ring-emerald-400 peer-focus-visible:outline-2 peer-focus-visible:outline-gold-300 after:absolute after:top-1 after:left-1 after:size-5 after:rounded-full after:bg-slate-400 after:transition peer-checked:after:translate-x-5 peer-checked:after:bg-emerald-300" aria-hidden="true"></span>
            </label>

            <x-admin.field name="video_url" label="Lien de la vidéo YouTube" type="url" :value="$video['video_url'] ?? ''" placeholder="https://www.youtube.com/watch?v=…" hint="Accepte youtube.com/watch, youtu.be et youtube.com/shorts. Vidéo publique ou non répertoriée, intégration autorisée. Laissez vide pour masquer la section." />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Textes</h2>
            <x-admin.field name="title" label="Titre" :value="$video['title'] ?? HomeVideo::DEFAULT_TITLE" :placeholder="HomeVideo::DEFAULT_TITLE" hint="Champ vidé : le titre d'origine est remis." />
            <x-admin.field name="subtitle" label="Phrase courte sous le titre" :value="$video['subtitle'] ?? HomeVideo::DEFAULT_SUBTITLE" :placeholder="HomeVideo::DEFAULT_SUBTITLE" hint="Champ vidé : la phrase d'origine est remise." />
        </section>

        <section class="card space-y-4 p-5 sm:p-6">
            <h2 class="font-semibold text-white">Bouton TikTok</h2>
            <x-admin.field name="tiktok_url" label="Lien TikTok (facultatif)" type="url" :value="$video['tiktok_url'] ?? ''" placeholder="https://www.tiktok.com/@votre-compte" hint="Le bouton « Suivre sur TikTok » n'apparaît que si ce lien est rempli." />
        </section>

        <button type="submit" class="btn-gold w-full sm:w-auto">Enregistrer la vidéo d'accueil</button>
    </form>
</x-admin.page>
