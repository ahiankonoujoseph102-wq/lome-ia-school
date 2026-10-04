/**
 * Groupes de discussion : glisser un message vers la droite pour y répondre, comme sur WhatsApp.
 *
 * Logique pure (aucun accès au DOM), testée avec Node.js (tests/Feature/Chat/ReplyTest.php) ; chat.js
 * l'applique aux bulles. Le geste ne gêne ni le défilement vertical (abandonné dès que le doigt part
 * surtout vers le haut ou le bas), ni l'appui long des réactions (annulé par chat.js au premier
 * mouvement, comme avant), ni le retour en arrière du téléphone (un geste qui commence au bord de
 * l'écran est laissé au système).
 */

export const SWIPE = {
    // Bande au bord gauche et droit de l'écran laissée au retour en arrière du système (Android, iPhone).
    edge: 28,
    // Mouvement minimal avant de décider entre glisser et faire défiler.
    slop: 10,
    // Distance à partir de laquelle relâcher prépare la réponse.
    threshold: 64,
    // La bulle ne suit plus le doigt au-delà.
    max: 96,
};

/**
 * Décalage de la bulle pour un déplacement horizontal du doigt : suit le doigt jusqu'au seuil,
 * puis résiste (comme un élastique), sans dépasser le maximum. Jamais vers la gauche.
 */
export function swipeOffset(dx, options = SWIPE) {
    if (!(dx > 0)) {
        return 0;
    }

    if (dx <= options.threshold) {
        return dx;
    }

    return Math.min(options.max, options.threshold + (dx - options.threshold) * 0.35);
}

/**
 * Suivi d'un geste : start(x, y, largeur de l'écran), move(x, y), end().
 *
 * États : « idle » (rien), « pending » (doigt posé, direction pas encore décidée), « swiping »
 * (la bulle suit le doigt), « scrolling » (défilement vertical ou geste vers la gauche : ignoré
 * jusqu'au lever du doigt), « ignored » (geste au bord de l'écran, laissé au système).
 */
export function createSwipeGesture(options = SWIPE) {
    let state = 'idle';
    let startX = 0;
    let startY = 0;
    let armed = false;
    let offset = 0;

    const snapshot = (crossed = false) => ({ state, offset, armed, crossed });

    return {
        get state() {
            return state;
        },

        start(x, y, width) {
            startX = x;
            startY = y;
            armed = false;
            offset = 0;
            state = x < options.edge || x > width - options.edge ? 'ignored' : 'pending';

            return snapshot();
        },

        move(x, y) {
            const dx = x - startX;
            const dy = y - startY;

            if (state === 'pending') {
                if (Math.hypot(dx, dy) < options.slop) {
                    return snapshot();
                }

                // Nettement vers la droite : glisser. Sinon (vers le haut, le bas ou la gauche) : on laisse faire.
                state = dx > 0 && dx > Math.abs(dy) * 1.5 ? 'swiping' : 'scrolling';
            }

            if (state !== 'swiping') {
                return snapshot();
            }

            offset = swipeOffset(dx, options);
            const wasArmed = armed;
            armed = offset >= options.threshold;

            return snapshot(armed && !wasArmed);
        },

        end() {
            const reply = state === 'swiping' && armed;
            const swiped = state === 'swiping';

            state = 'idle';
            armed = false;
            offset = 0;

            return { reply, swiped };
        },
    };
}
