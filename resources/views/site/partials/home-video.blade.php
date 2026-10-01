{{--
    Vidéo d'accueil (verticale 9:16), entre le haut de page et le catalogue. Réglages : admin > Vidéo d'accueil.
    Section absente tant qu'il n'y a pas de lien YouTube valide (ou si l'interrupteur est éteint).
    Seule l'image d'aperçu est chargée : le lecteur (youtube-nocookie.com) n'est ajouté qu'au toucher du bouton lecture.
    Sans JavaScript, le bouton est un simple lien vers YouTube. Aucune transcription ni sous-titre n'est affiché.
    Style stable face au mode sombre forcé : fonds foncés, contours dessinés en ombre, icône blanche, image jamais recolorée.
--}}
@php
    use App\Support\HomeVideo;
    use App\Support\YouTube;

    $homeVideoId = HomeVideo::videoId();
    $homeVideoTiktok = HomeVideo::tiktokUrl();
    $homeVideoCatalogue = $featured->isNotEmpty() ? '#catalogue' : route('formations.index');
@endphp

@if ($homeVideoId)
    <section id="video-accueil" class="section !py-10 sm:!py-14" data-test="home-video">
        <x-site.section-heading :title="HomeVideo::title()" :subtitle="HomeVideo::subtitle()" />

        {{-- Largeur limitée sur téléphone (la vidéo ne doit pas écraser le reste), 20 rem dès les écrans moyens. --}}
        <div class="mx-auto mt-8 w-[min(78%,17rem)] sm:w-80" x-data="homeVideo" data-test="home-video-frame" data-reveal>
            <div class="relative aspect-[9/16] overflow-hidden rounded-3xl bg-night-900 shadow-[0_0_0_1px_var(--color-night-600),0_24px_48px_-24px_rgb(0_0_0/0.7)]">
                <template x-if="playing">
                    <iframe
                        x-ref="frame"
                        src="{{ HomeVideo::embedUrl($homeVideoId) }}"
                        title="{{ HomeVideo::title() }}"
                        class="absolute inset-0 size-full"
                        allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                        x-on:load="listen()"
                    ></iframe>
                </template>

                <template x-if="failed">
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-4 bg-night-900 p-6 text-center" data-test="home-video-fallback" role="status">
                        <flux:icon.exclamation-triangle class="size-8 text-slate-300" />
                        <p class="text-sm text-slate-200">Cette vidéo ne peut pas être lue ici pour le moment.</p>
                        <a href="{{ HomeVideo::watchUrl($homeVideoId) }}" target="_blank" rel="noopener noreferrer" class="btn-ghost !px-4 !py-2 !text-sm">
                            Regarder sur YouTube <flux:icon.arrow-up-right class="size-4" />
                        </a>
                    </div>
                </template>

                <a
                    href="{{ HomeVideo::watchUrl($homeVideoId) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    x-show="! playing && ! failed"
                    x-on:click.prevent="play()"
                    class="group absolute inset-0 block"
                    aria-label="Lire la vidéo : {{ HomeVideo::title() }}"
                    data-test="home-video-play"
                >
                    <img
                        src="{{ YouTube::thumbnailUrl($homeVideoId) }}"
                        alt=""
                        class="size-full object-cover"
                        width="480"
                        height="360"
                        loading="lazy"
                        decoding="async"
                        x-on:error="fail()"
                    >
                    <span class="absolute inset-0 grid place-items-center bg-night-950/30 transition group-hover:bg-night-950/10">
                        <span class="grid size-16 place-items-center rounded-full bg-night-950/85 text-white shadow-[0_0_0_2px_var(--color-gold-500),0_10px_30px_rgb(0_0_0/0.5)] transition group-hover:scale-105">
                            <flux:icon.play class="size-7 translate-x-0.5 text-white" variant="solid" />
                        </span>
                    </span>
                </a>
            </div>
        </div>

        {{-- La vidéo se termine par un appel à s'abonner qui n'a pas de sens ici : ces boutons servent de suite. --}}
        <div class="mx-auto mt-8 flex max-w-sm flex-col gap-3 sm:max-w-none sm:flex-row sm:justify-center" data-reveal>
            <a href="{{ $homeVideoCatalogue }}" class="btn-gold sm:whitespace-nowrap" data-test="home-video-formations">
                Voir les formations <flux:icon.arrow-right class="size-5" />
            </a>
            @if ($homeVideoTiktok)
                <a href="{{ $homeVideoTiktok }}" target="_blank" rel="noopener noreferrer" class="btn-ghost sm:whitespace-nowrap" data-test="home-video-tiktok">
                    Suivre sur TikTok <flux:icon.arrow-up-right class="size-5" />
                </a>
            @endif
        </div>
    </section>
@endif
