/**
 * « Installer l'application » : bannière discrète (Chrome et Android) et lien du pied de page (tous les navigateurs
 * qui permettent l'installation, explication sur iPhone et iPad).
 * Fonctions pures (aucune dépendance au navigateur), testées avec Node.js (tests/Feature/Site/InstallAppTest.php).
 *
 * Règles :
 * - la bannière n'existe que là où le navigateur propose l'installation (événement « beforeinstallprompt » :
 *   Chrome, Edge, Samsung Internet…), sur un écran tactile, après BANNER_DELAY_SECONDS secondes passées sur le site
 *   (cumulées sur toute la visite, toutes pages confondues) ;
 * - jamais sur iPhone ou iPad (aucune bannière automatique), jamais si l'application est déjà installée ;
 * - fermée par le visiteur (croix, ou « Annuler » dans la fenêtre d'installation) : plus rien pendant DISMISS_DAYS jours ;
 * - le lien reste disponible : il ouvre la fenêtre d'installation, ou l'explication sur iPhone et iPad.
 * Les pages où la bannière n'apparaît jamais (paiement, connexion, lecture de cours, admin…) sont choisies par le
 * serveur (App\Support\InstallApp::BANNER_PAGES) : la bannière n'y est pas dans la page.
 */

export const BANNER_DELAY_SECONDS = 20;
export const DISMISS_DAYS = 30;
export const VISIT_KEY = 'lis-install-visit';
export const DISMISS_KEY = 'lis-install-dismissed';

const DAY = 24 * 60 * 60 * 1000;

// Navigation privée, stockage bloqué : jamais d'erreur, on fait simplement comme si rien n'était enregistré.
const read = (storage, key) => {
    try {
        return storage?.getItem(key) ?? null;
    } catch {
        return null;
    }
};

const write = (storage, key, value) => {
    try {
        storage?.setItem(key, String(value));
    } catch {
        // Rien à faire : la bannière se comportera comme pour une première visite.
    }
};

/**
 * iPhone, iPad ou iPod (y compris un iPad qui se présente comme un Mac).
 */
export function isIos({ userAgent = '', platform = '', maxTouchPoints = 0 } = {}) {
    return /iPhone|iPad|iPod/i.test(userAgent) || (platform === 'MacIntel' && maxTouchPoints > 1);
}

/**
 * Site ouvert depuis l'icône de l'écran d'accueil (application installée).
 */
export function isInstalled({ standaloneDisplay = false, navigatorStandalone = false } = {}) {
    return Boolean(standaloneDisplay || navigatorStandalone);
}

/**
 * Bannière fermée il y a moins de DISMISS_DAYS jours.
 */
export function dismissedRecently(storage, now) {
    const at = Number(read(storage, DISMISS_KEY));

    return Number.isFinite(at) && at > 0 && at <= now && now - at < DISMISS_DAYS * DAY;
}

export function rememberDismissal(storage, now) {
    write(storage, DISMISS_KEY, now);
}

/**
 * Millisecondes restantes avant de pouvoir montrer la bannière : le temps passé sur le site est compté depuis la
 * première page de la visite (stockage de l'onglet), pas page par page.
 */
export function remainingDelay(session, now, seconds = BANNER_DELAY_SECONDS) {
    let start = Number(read(session, VISIT_KEY));

    if (!Number.isFinite(start) || start <= 0 || start > now) {
        start = now;
        write(session, VISIT_KEY, now);
    }

    return Math.max(0, seconds * 1000 - (now - start));
}

/**
 * Deux rectangles (getBoundingClientRect) se chevauchent-ils ? Un élément invisible (taille nulle) ne compte pas.
 */
export function overlaps(box, rect) {
    if (!box || !rect || rect.width <= 1 || rect.height <= 1) {
        return false;
    }

    return rect.left < box.right && box.left < rect.right && rect.top < box.bottom && box.top < rect.bottom;
}

/**
 * Distance au bas de l'écran pour la bannière : juste au-dessus des barres fixées en bas (barre de prix d'une
 * formation, barre de navigation de l'espace élève), sinon `gap` pixels.
 */
export function bottomOffset(viewportHeight, barTops, gap = 12) {
    const tops = barTops.filter((top) => Number.isFinite(top) && top < viewportHeight);

    return tops.length ? Math.ceil(viewportHeight - Math.min(...tops)) + gap : gap;
}

/**
 * État partagé par la bannière et le lien (magasin Alpine « installApp » de site.js).
 *
 * @param {object} options
 * @param {boolean} options.ios iPhone ou iPad
 * @param {boolean} options.installed application déjà ouverte depuis l'écran d'accueil
 * @param {boolean} options.touch écran tactile (la bannière parle du téléphone)
 * @param {object} options.storage stockage durable (localStorage) : fermeture de la bannière
 * @param {object} options.session stockage de l'onglet (sessionStorage) : début de la visite
 * @param {() => number} options.now horloge
 * @param {(callback: Function, ms: number) => unknown} options.schedule minuterie (setTimeout)
 */
export function createInstallState({ ios = false, installed = false, touch = false, storage = null, session = null, now = () => Date.now(), schedule = () => {} } = {}) {
    // Événement « beforeinstallprompt » gardé hors de l'état réactif : enveloppé par Alpine, son prompt() échouerait.
    let deferred = null;

    return {
        ios,
        installed,
        touch,
        canPrompt: false,
        delayDone: false,
        dismissed: dismissedRecently(storage, now()),
        helpOpen: false,
        busy: false,

        // Le compte à rebours commence dès l'arrivée sur une page du site.
        start() {
            const remaining = remainingDelay(session, now());

            if (remaining === 0) {
                this.delayDone = true;
            } else {
                schedule(() => {
                    this.delayDone = true;
                }, remaining);
            }

            return this;
        },

        // Événement « beforeinstallprompt » reçu : le navigateur accepte d'installer le site.
        installable(event) {
            if (!this.installed && event && typeof event.prompt === 'function') {
                deferred = event;
                this.canPrompt = true;
            }
        },

        // Événement « appinstalled », ou installation acceptée.
        markInstalled() {
            this.installed = true;
            deferred = null;
            this.canPrompt = false;
            this.helpOpen = false;
        },

        // Lien « Installer l'application » : seulement s'il fait vraiment quelque chose.
        get linkVisible() {
            return !this.installed && (this.canPrompt || this.ios);
        },

        get bannerVisible() {
            return !this.installed && !this.ios && this.touch && this.canPrompt && this.delayDone && !this.dismissed;
        },

        dismiss() {
            this.dismissed = true;
            rememberDismissal(storage, now());
        },

        /**
         * Bouton « Installer » ou lien : fenêtre d'installation du navigateur, ou explication sur iPhone.
         * Résultat : 'accepted', 'dismissed', 'help' ou 'none'.
         */
        async install() {
            if (deferred && !this.busy) {
                const event = deferred;
                this.busy = true;
                // Une fenêtre d'installation ne peut s'ouvrir qu'une fois par événement.
                deferred = null;
                this.canPrompt = false;

                try {
                    await event.prompt();
                    const choice = await event.userChoice;

                    if (choice?.outcome === 'accepted') {
                        this.markInstalled();

                        return 'accepted';
                    }

                    this.dismiss();

                    return 'dismissed';
                } catch {
                    return 'none';
                } finally {
                    this.busy = false;
                }
            }

            if (this.ios && !this.installed) {
                this.helpOpen = !this.helpOpen;

                return 'help';
            }

            return 'none';
        },
    };
}
