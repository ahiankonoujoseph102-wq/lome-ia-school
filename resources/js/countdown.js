/**
 * Compte à rebours de la fin des inscriptions d'une offre d'accompagnement.
 *
 * Calculé à partir de la date fixe enregistrée sur le serveur (jamais d'une date propre au visiteur) ; l'écart
 * entre l'horloge du téléphone et celle du serveur est corrigé, pour qu'un téléphone mal réglé affiche le même
 * temps restant que les autres. Même calcul que App\Packs\PackDeadline::remaining().
 */

/**
 * @param {number} deadlineMs date limite (millisecondes depuis 1970)
 * @param {number} nowMs heure actuelle (millisecondes depuis 1970)
 * @returns {{days: number, hours: number, minutes: number, closed: boolean}}
 */
export function countdownParts(deadlineMs, nowMs) {
    const milliseconds = deadlineMs - nowMs;

    if (!(milliseconds > 0)) {
        return { days: 0, hours: 0, minutes: 0, closed: true };
    }

    const minutes = Math.ceil(milliseconds / 60000);

    return {
        days: Math.floor(minutes / 1440),
        hours: Math.floor((minutes % 1440) / 60),
        minutes: minutes % 60,
        closed: false,
    };
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
