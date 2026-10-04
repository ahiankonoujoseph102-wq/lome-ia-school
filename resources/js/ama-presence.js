/**
 * Présence d'Ama sur le site : visage animé et bulle de présentation. Fonctions pures, sans dépendance au navigateur
 * (testées avec Node.js, tests/Feature/Site/AmaPresenceTest.php) ; le composant « voiceAssistant » de site.js les utilise.
 *
 * Rien ici n'appelle Gemini ni le serveur : la bulle est un texte écrit à l'avance.
 */

// Temps passé sur le site (cumulé sur la visite) avant la bulle, et durée d'affichage de la bulle.
export const NUDGE_SECONDS = 8;
export const NUDGE_VISIBLE_MS = 14000;
// Glissement horizontal (pixels) qui ferme la bulle.
export const SWIPE_DISTANCE = 40;

const DONE_KEY = 'ama-nudge';
const TIME_KEY = 'ama-time';

const read = (storage, key) => {
    try {
        return storage?.getItem(key) ?? null;
    } catch {
        return null;
    }
};

const write = (storage, key, value) => {
    try {
        storage?.setItem(key, value);
    } catch {
        // Stockage bloqué : la bulle pourra revenir sur une autre page, sans aucune erreur.
    }
};

/**
 * Mouvement permis ? Non si le téléphone demande moins d'animations ou si le navigateur économise les données.
 */
export function motionAllowed(win) {
    try {
        if (win.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
            return false;
        }
    } catch {
        // Question inconnue du navigateur : on garde le mouvement.
    }

    return win.navigator?.connection?.saveData !== true;
}

// La bulle a-t-elle déjà été montrée (ou fermée) pendant cette visite ?
export const nudgeDone = (storage) => read(storage, DONE_KEY) === '1';

export const markNudgeDone = (storage) => write(storage, DONE_KEY, '1');

// Millisecondes déjà passées sur le site pendant cette visite.
export const timeOnSite = (storage) => {
    const value = Number(read(storage, TIME_KEY));

    return Number.isFinite(value) && value > 0 ? value : 0;
};

export const addTimeOnSite = (storage, milliseconds) => {
    if (milliseconds > 0) {
        write(storage, TIME_KEY, String(Math.round(timeOnSite(storage) + milliseconds)));
    }
};

// Millisecondes à attendre avant la bulle (0 : tout de suite).
export const remainingMs = (storage, seconds = NUDGE_SECONDS) => Math.max(0, seconds * 1000 - timeOnSite(storage));

// Délai avant le prochain clignement : de 3 à 6 secondes (random : nombre entre 0 et 1).
export const blinkDelay = (random = Math.random()) => 3000 + Math.round(random * 3000);

// Un glissement vers le côté (plus horizontal que vertical) ferme la bulle.
export const isSwipe = (dx, dy) => Math.abs(dx) >= SWIPE_DISTANCE && Math.abs(dx) > Math.abs(dy) * 1.5;

// La bouche change de rythme à chaque mot repéré par la synthèse vocale.
export const nextBeat = (beat) => (beat === 'a' ? 'b' : 'a');

/**
 * Éléments qu'une bulle de présentation ne doit jamais recouvrir : boutons, champs, boutons dorés (paiement,
 * inscription), tout élément marqué data-ama-avoid et les liens des barres fixées en bas (« Acheter maintenant »).
 * Les simples liens de texte ne comptent pas.
 */
export const NUDGE_AVOID = 'button, input:not([type=hidden]), select, textarea, .btn-gold, [data-ama-avoid], [data-bottom-bar] a';

/**
 * Minuteur de la bulle de présentation : compte le temps passé sur le site pendant la visite (cumulé d'une page à
 * l'autre, en pause quand l'onglet est masqué ou la page quittée) et appelle onDue() une seule fois, à 8 secondes.
 * Rien n'est lancé si la bulle a déjà été montrée ou fermée pendant cette visite.
 *
 * @param {object} options
 * @param {object} options.storage  stockage de la visite (sessionStorage), peut être absent ou bloqué
 * @param {Function} options.onDue  appelée quand le délai est atteint
 * @param {number} [options.seconds]  délai (8 secondes)
 * @param {Function} [options.now]  horloge en millisecondes
 * @param {object} [options.timers]  { setTimeout, clearTimeout }
 */
export function createNudgeTimer({
    storage,
    onDue,
    seconds = NUDGE_SECONDS,
    now = () => Date.now(),
    timers = { setTimeout: (callback, delay) => setTimeout(callback, delay), clearTimeout: (id) => clearTimeout(id) },
}) {
    let active = !nudgeDone(storage);
    let timer = null;
    let since = null;

    const save = () => {
        if (since !== null) {
            addTimeOnSite(storage, now() - since);
            since = null;
        }
    };

    const pause = () => {
        save();

        if (timer !== null) {
            timers.clearTimeout(timer);
            timer = null;
        }
    };

    return {
        get active() {
            return active;
        },
        // Page visible : le compte (re)part de ce qui reste à attendre.
        arm() {
            if (!active || timer !== null) {
                return;
            }

            since = now();
            timer = timers.setTimeout(() => {
                timer = null;
                save();
                onDue();
            }, remainingMs(storage, seconds));
        },
        // Onglet masqué ou page quittée : le temps passé est gardé pour la page suivante.
        pause,
        // La bulle a été montrée, fermée ou n'a plus lieu d'être : plus jamais pendant cette visite.
        stop() {
            active = false;
            pause();
        },
    };
}
