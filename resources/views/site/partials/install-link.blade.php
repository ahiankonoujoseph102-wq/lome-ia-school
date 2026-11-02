{{--
    Lien discret « Installer l'application » (pied de page). Visible seulement s'il fait quelque chose : fenêtre
    d'installation du navigateur (Chrome, Android, ordinateur), ou explication sur iPhone et iPad (aucune bannière
    automatique). Masqué si l'application est déjà installée. Voir resources/js/install-app.js.
--}}
<div x-data x-show="$store.installApp.linkVisible" x-cloak class="{{ $class ?? '' }}" data-test="install-link">
    <button
        type="button"
        @click="$store.installApp.install()"
        :aria-expanded="$store.installApp.ios ? String($store.installApp.helpOpen) : null"
        class="inline-flex min-h-11 items-center gap-1.5 text-slate-400 hover:text-white sm:min-h-0"
    >
        <flux:icon.device-phone-mobile class="size-4 shrink-0" />
        Installer l'application
    </button>
    <p
        x-show="$store.installApp.ios && $store.installApp.helpOpen"
        x-cloak
        role="status"
        class="mt-2 flex max-w-xs items-start gap-2 rounded-xl bg-night-900 px-3 py-2.5 text-xs leading-relaxed text-slate-200 ring-1 ring-night-600"
        data-test="install-ios-help"
    >
        <flux:icon.arrow-up-on-square class="mt-0.5 size-4 shrink-0 text-electric-300" />
        <span>{{ \App\Support\InstallApp::IOS_HELP }}</span>
    </p>
</div>
