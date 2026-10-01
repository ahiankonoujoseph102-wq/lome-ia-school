{{--
    Bannière « Installer l'application » (pages de App\Support\InstallApp::BANNER_PAGES). Affichée seulement si le
    navigateur propose l'installation (Chrome, Android : « beforeinstallprompt »), sur écran tactile, après 20 secondes
    sur le site, jamais sur iPhone, jamais si l'application est installée, et plus pendant 30 jours après la croix
    (voir resources/js/install-app.js). Placée au-dessus des barres fixées en bas (data-bottom-bar), invisible tant
    qu'elle passerait sur un bouton principal (« Je m'inscris », « Acheter maintenant »).
    Style stable face au mode sombre forcé de Chrome : fond foncé, texte clair, contours en ombre (ring), bouton
    « Installer » en or translucide avec contour doré (jamais de texte foncé sur fond clair).
--}}
<div
    x-data="installBanner"
    x-show="$store.installApp.bannerVisible"
    x-cloak
    x-transition.opacity.duration.200ms
    :style="`bottom: calc(${bottom}px + env(safe-area-inset-bottom))`"
    :class="covering && 'invisible'"
    class="install-banner fixed inset-x-3 bottom-3 z-40 mx-auto flex max-w-md items-center gap-2.5 rounded-2xl bg-night-900/95 py-2.5 pr-3 pl-2.5 shadow-xl ring-1 shadow-black/50 ring-night-600 backdrop-blur-md"
    role="region"
    aria-label="Installer l'application"
    data-test="install-banner"
>
    {{-- Icône dessinée (et non l'image de l'icône du site : Chrome inverse les petites images en mode sombre forcé). --}}
    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-electric-500/25 text-electric-200 ring-1 ring-electric-300/50" aria-hidden="true">
        <flux:icon.device-phone-mobile class="size-5" />
    </span>
    <p class="min-w-0 flex-1 pr-1 text-[13px] leading-snug text-slate-100">{{ \App\Support\InstallApp::BANNER_TEXT }}</p>
    <button
        type="button"
        @click="$store.installApp.install()"
        :disabled="$store.installApp.busy"
        class="shrink-0 rounded-full bg-gold-500/20 px-3.5 py-2 text-sm font-semibold text-white ring-1 ring-gold-500 transition hover:bg-gold-500/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-300"
        data-test="install-banner-button"
    >Installer</button>
    <button
        type="button"
        @click="$store.installApp.dismiss()"
        class="absolute -top-3 -right-1.5 grid size-8 place-items-center rounded-full bg-night-800 text-slate-200 shadow-lg ring-1 shadow-black/40 ring-night-600 hover:text-white"
        aria-label="Fermer"
        data-test="install-banner-close"
    >
        <flux:icon.x-mark class="size-4" />
    </button>
</div>
