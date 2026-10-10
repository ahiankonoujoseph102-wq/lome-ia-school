{{--
    Vidéo de présentation d'une formation gratuite, juste au-dessus du bloc d'inscription.
    Réglages : admin > Formations > Inscriptions > « Vidéo de présentation ». Absente si le lien est vide
    ou invalide, ou si l'interrupteur est éteint : la page reste alors exactement comme avant.
    Même lecteur que la vidéo d'accueil (x-site.lazy-youtube) : image d'aperçu seulement, YouTube chargé au toucher.
    Court sur téléphone pour que « Créer mon compte » reste à portée de pouce : vidéo verticale en aperçu étroit
    (10 rem, agrandi au toucher), vidéo horizontale sur toute la largeur (environ 185 px de haut à 360 px).
    Juste sous la vidéo, la même action que le bloc d'inscription (même lien, parcours inchangé), pour qu'elle reste
    visible sans défiler : « Créer mon compte » pour un visiteur, « Confirmer mon e-mail » ou « Choisir mon option »
    pour un compte pas encore inscrit. Rien de plus pour une personne inscrite, des inscriptions closes ou complètes.
    Ama et la bannière d'installation ne s'y posent jamais (data-ama-avoid, data-ama-clear, data-install-avoid).
--}}
@php
    $presentationVideoId = $formation->presentationVideoId();
    $presentationVertical = $formation->presentationVideoIsVertical();
    // Formation terminée : titre neutre (la présentation invitait à venir).
    $presentationTitle = $formation->isFreeEnded() ? 'La vidéo de présentation' : 'Coach Joseph vous présente la formation';

    $videoUser = auth()->user();
    $videoAction = null;

    if ($registration === null && ! $closed && ! ($options->isNotEmpty() && $availableOptions->isEmpty())) {
        $videoAction = match (true) {
            ! $videoUser => ['Créer mon compte', route('free-registration.start', $formation)],
            ! $videoUser->hasConfirmedEmail() => ['Confirmer mon e-mail', route('free-registration.start', $formation)],
            $options->isNotEmpty() => ['Choisir mon option', route('free-registration.options', $formation)],
            default => null,
        };
    }
@endphp

@if ($presentationVideoId)
    <section class="mb-6" aria-labelledby="video-presentation-titre" data-test="free-video" data-video-format="{{ $presentationVertical ? 'vertical' : 'horizontal' }}">
        <h2 id="video-presentation-titre" class="text-xl font-bold text-balance text-white sm:text-2xl">{{ $presentationTitle }}</h2>

        <x-site.lazy-youtube
            :video-id="$presentationVideoId"
            :title="$presentationTitle.' : '.$formation->title"
            :vertical="$presentationVertical"
            test="free-video"
            :class="\Illuminate\Support\Arr::toCssClasses([
                'mt-3 sm:mt-4',
                'mx-auto w-40 transition-[width] duration-300 data-[open=true]:w-[min(78%,17rem)] sm:w-72 sm:data-[open=true]:w-72 motion-reduce:transition-none' => $presentationVertical,
                'w-full' => ! $presentationVertical,
            ])"
            data-test="free-video-frame"
            data-ama-avoid
            data-ama-clear
            data-install-avoid
        />

        @if ($videoAction)
            <div class="mt-4" data-ama-avoid data-ama-clear data-install-avoid>
                <a href="{{ $videoAction[1] }}" class="btn-gold w-full !py-4 text-lg" data-test="free-video-action">
                    {{ $videoAction[0] }} <flux:icon.arrow-right class="size-5" />
                </a>
            </div>
        @endif
    </section>
@endif
