/**
 * Partage d'un lieu dans les groupes : messages clairs pour la géolocalisation du navigateur
 * et vérification légère d'un lien Google Maps avant l'envoi (le serveur vérifie toujours le domaine).
 * Logique pure, testée avec Node.js (tests/Feature/Chat/LocationTest.php).
 */

export function geolocationError(error) {
    switch (error?.code) {
        case 1:
            return 'Vous avez refusé l\'accès à votre position. Autorisez-la dans les réglages de votre navigateur, ou choisissez « Coller un lien Google Maps ».';
        case 2:
            return 'Position introuvable pour le moment. Activez la localisation du téléphone, ou choisissez « Coller un lien Google Maps ».';
        case 3:
            return 'La recherche de votre position a pris trop de temps. Réessayez, ou choisissez « Coller un lien Google Maps ».';
        default:
            return 'Votre navigateur ne permet pas de trouver votre position. Choisissez « Coller un lien Google Maps ».';
    }
}

/**
 * Lien collé (texte autour, espaces invisibles, https manquant) : premier lien trouvé, sinon le texte nettoyé.
 */
export function cleanMapsLink(value) {
    const text = String(value || '')
        .replace(/[­​-‏⁠﻿]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    const match = text.match(/(?:https?:\/\/)?(?:[a-z0-9-]+\.)+[a-z]{2,}\/\S*/i);

    if (!match) {
        return text;
    }

    const link = match[0].replace(/^https?:\/+/i, '');

    return `https://${link.replace(/^[^/?#]+/, (host) => host.toLowerCase())}`;
}
