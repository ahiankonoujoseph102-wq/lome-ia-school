/**
 * Compte à rebours de la fin des inscriptions d'une offre d'accompagnement.
 *
 * Calculé à partir de la date fixe enregistrée sur le serveur (jamais d'une date propre au visiteur) ; l'écart
 * entre l'horloge du téléphone et celle du serveur est corrigé, pour qu'un téléphone mal réglé affiche le même
 * temps restant que les autres. Même calcul que App\Packs\PackDeadline::remaining().
 */

/**
 * Temps restant, secondes arrondies au-dessus : « 0 seconde » n'apparaît jamais tant que les inscriptions sont
 * ouvertes, et l'affichage passe à « Inscriptions closes » à l'instant exact de la date.
 *
 * @param {number} deadlineMs date limite (millisecondes depuis 1970)
 * @param {number} nowMs heure actuelle (millisecondes depuis 1970)
 * @returns {{days: number, hours: number, minutes: number, seconds: number, closed: boolean}}
 */
export function countdownParts(deadlineMs, nowMs) {
    const milliseconds = deadlineMs - nowMs;

    if (!(milliseconds > 0)) {
        return { days: 0, hours: 0, minutes: 0, seconds: 0, closed: true };
    }

    const seconds = Math.ceil(milliseconds / 1000);

    return {
        days: Math.floor(seconds / 86400),
        hours: Math.floor((seconds % 86400) / 3600),
        minutes: Math.floor((seconds % 3600) / 60),
        seconds: seconds % 60,
        closed: false,
    };
}

/**
 * Délai avant le prochain changement de l'affichage (la seconde suivante), pour que les secondes changent au bon
 * moment même si le navigateur a pris du retard. Au moins 50 ms, au plus une seconde.
 *
 * @param {number} deadlineMs
 * @param {number} nowMs
 */
export function nextTickDelay(deadlineMs, nowMs) {
    const milliseconds = deadlineMs - nowMs;

    if (!(milliseconds > 0)) {
        return 1000;
    }

    const delay = milliseconds % 1000 || 1000;

    return Math.max(50, Math.min(1000, delay + 5));
}

/**
 * Texte pour les lecteurs d'écran (à la minute près, jamais annoncé automatiquement) :
 * « Temps restant : 2 jours, 4 heures et 1 minute ».
 *
 * @param {{days: number, hours: number, minutes: number, closed: boolean}} parts
 */
export function countdownLabel(parts) {
    if (parts.closed) {
        return 'Inscriptions closes';
    }

    const words = [
        [parts.days, 'jour', 'jours'],
        [parts.hours, 'heure', 'heures'],
        [parts.minutes, 'minute', 'minutes'],
    ]
        .filter(([value], index) => value > 0 || index === 2)
        .map(([value, one, many]) => `${value} ${value > 1 ? many : one}`);

    const last = words.pop();

    return `Temps restant : ${words.length ? `${words.join(', ')} et ${last}` : last}`;
}

/**
 * Écart entre l'heure du serveur (au moment où la page a été préparée) et l'heure du téléphone.
 *
 * @param {number} serverNowMs
 * @param {number} clientNowMs
 */
export function clockOffset(serverNowMs, clientNowMs) {
    return Number.isFinite(serverNowMs) ? serverNowMs - clientNowMs : 0;
}

/**
 * Écart mesuré par une demande de l'heure au serveur : on suppose que le serveur a répondu au milieu de l'aller-retour
 * (erreur au plus égale à la moitié de l'aller-retour). Null si la mesure n'est pas fiable (réponse trop lente,
 * heure illisible).
 *
 * @param {number} serverNowMs heure renvoyée par le serveur
 * @param {number} sentAtMs heure du téléphone à l'envoi de la demande
 * @param {number} receivedAtMs heure du téléphone à la réception
 * @param {number} maxRoundTripMs aller-retour au-delà duquel la mesure est ignorée
 * @returns {number|null}
 */
export function measuredOffset(serverNowMs, sentAtMs, receivedAtMs, maxRoundTripMs = 10000) {
    const roundTrip = receivedAtMs - sentAtMs;

    if (!Number.isFinite(serverNowMs) || !Number.isFinite(roundTrip) || roundTrip < 0 || roundTrip > maxRoundTripMs) {
        return null;
    }

    return Math.round(serverNowMs + roundTrip / 2 - receivedAtMs);
}

/**
 * Horloge du serveur, partagée par tous les comptes à rebours d'une page.
 *
 * 1. Tout de suite : l'heure du serveur écrite dans la page (hint) donne un premier écart, sans attendre le réseau.
 * 2. Puis une petite demande de l'heure au serveur (sync) corrige ce premier écart, qui serait faux d'une page
 *    gardée en mémoire par le navigateur (retour en arrière) ou arrivée lentement sur un réseau faible.
 * Si le réseau ne répond pas, le premier écart est gardé.
 *
 * @param {{ url?: string|null, fetcher?: Function, now?: () => number, minIntervalMs?: number }} options
 */
export function createServerClock({ url = null, fetcher = globalThis.fetch?.bind(globalThis), now = () => Date.now(), minIntervalMs = 60000 } = {}) {
    let offset = null;
    let measured = false;
    let syncedAt = null;
    let pending = null;
    const listeners = new Set();

    const notify = () => listeners.forEach((listener) => listener());

    return {
        offset: () => offset ?? 0,
        isMeasured: () => measured,
        now: () => now() + (offset ?? 0),

        hint(serverNowMs) {
            if (offset === null && Number.isFinite(serverNowMs)) {
                offset = clockOffset(serverNowMs, now());
            }
        },

        onChange(listener) {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },

        sync({ force = false } = {}) {
            if (!url || typeof fetcher !== 'function') {
                return Promise.resolve(false);
            }

            if (pending) {
                return pending;
            }

            if (!force && syncedAt !== null && now() - syncedAt < minIntervalMs) {
                return Promise.resolve(false);
            }

            const sentAt = now();

            pending = Promise.resolve()
                .then(() => fetcher(url, { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' } }))
                .then((response) => (response && response.ok ? response.json() : null))
                .then((data) => {
                    const receivedAt = now();
                    const value = measuredOffset(Number(data?.now), sentAt, receivedAt);

                    if (value === null) {
                        return false;
                    }

                    offset = value;
                    measured = true;
                    syncedAt = receivedAt;
                    notify();

                    return true;
                })
                .catch(() => false)
                .finally(() => {
                    pending = null;
                });

            return pending;
        },
    };
}
