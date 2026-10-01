/**
 * Vidéo d'accueil : lecteur YouTube chargé seulement au toucher.
 * Fonctions pures (aucune dépendance au navigateur), testées avec Node.js (tests/Feature/Site/HomeVideoTest.php).
 *
 * Si la vidéo ne peut pas être lue (intégration interdite, vidéo privée ou supprimée), le lecteur YouTube prévient
 * la page par un message « onError » : on remplace alors la zone vide par un lien « Regarder sur YouTube ».
 */

export const PLAYER_ORIGIN = 'https://www.youtube-nocookie.com';

/**
 * Message à envoyer au lecteur dès qu'il est chargé, pour qu'il prévienne la page (état, erreurs).
 */
export const listeningMessage = () => JSON.stringify({ event: 'listening', id: 'home-video', channel: 'widget' });

/**
 * Le message reçu du lecteur signale-t-il une vidéo illisible ?
 * Codes d'erreur YouTube : 2 (lien invalide), 5 (lecteur), 100 (introuvable ou privée), 101 et 150 (intégration interdite).
 */
export const isPlaybackError = (origin, raw) => {
    if (origin !== PLAYER_ORIGIN || typeof raw !== 'string') {
        return false;
    }

    try {
        const data = JSON.parse(raw);

        return data?.event === 'onError';
    } catch {
        return false;
    }
};
