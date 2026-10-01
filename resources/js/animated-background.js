/*
 * Fond animé de l'accueil et du haut du catalogue (partiel site/partials/animated-background.blade.php).
 * Les animations sont en CSS ; ce script ne fait que :
 * - les couper (attribut data-still) si le visiteur préfère moins d'animations ou économise ses données ;
 * - les mettre en pause (data-paused) quand le fond n'est pas à l'écran ou que l'onglet est masqué ;
 * - décaler très légèrement les motifs quand on fait défiler (transform seulement, une fois par image).
 */

export function motionAllowed({ reducedMotion = false, saveData = false } = {}) {
    return !reducedMotion && !saveData;
}

/** Décalage d'un calque : une fraction du défilement, limitée à la hauteur de la zone. */
export function layerOffset(scrollY, depth, limit) {
    const y = Math.max(0, Math.min(Number(scrollY) || 0, Math.max(0, Number(limit) || 0)));

    return Math.round(y * (Number(depth) || 0) * 10) / 10;
}

export function initAnimatedBackgrounds(doc = document, win = window) {
    const roots = [...doc.querySelectorAll('[data-animated-bg]')];

    if (roots.length === 0) {
        return null;
    }

    const reducedMotion = Boolean(win.matchMedia?.('(prefers-reduced-motion: reduce)').matches);
    const saveData = win.navigator?.connection?.saveData === true;

    if (!motionAllowed({ reducedMotion, saveData })) {
        roots.forEach((root) => root.setAttribute('data-still', ''));

        return null;
    }

    const items = roots.map((root) => ({
        root,
        layers: [...root.querySelectorAll('[data-depth]')],
        height: root.offsetHeight,
        visible: false,
    }));
    let ticking = false;

    const pause = () => {
        items.forEach((item) => item.root.toggleAttribute('data-paused', doc.hidden || !item.visible));
    };

    const shift = () => {
        ticking = false;
        items.forEach((item) => {
            if (!item.visible) {
                return;
            }

            item.layers.forEach((layer) => {
                layer.style.transform = `translate3d(0, ${layerOffset(win.scrollY, layer.dataset.depth, item.height)}px, 0)`;
            });
        });
    };

    const onScroll = () => {
        if (!ticking) {
            ticking = true;
            win.requestAnimationFrame(shift);
        }
    };

    if ('IntersectionObserver' in win) {
        const observer = new win.IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const item = items.find((candidate) => candidate.root === entry.target);

                if (item) {
                    item.visible = entry.isIntersecting;
                }
            });
            pause();
        });
        items.forEach((item) => observer.observe(item.root));
    } else {
        items.forEach((item) => (item.visible = true));
    }

    pause();
    doc.addEventListener('visibilitychange', pause);
    win.addEventListener('scroll', onScroll, { passive: true });
    win.addEventListener('resize', () => items.forEach((item) => (item.height = item.root.offsetHeight)), { passive: true });

    return { items, pause, shift };
}
