/**
 * Chiffres clés qui « montent » quand ils arrivent à l'écran (accueil, À propos).
 *
 * Le chiffre final est toujours écrit dans la page : sans script, avec « moins d'animations », avec l'économie de
 * données ou s'il est déjà visible au chargement, il ne bouge pas. Seul le texte change (aucune mise en page).
 */

/**
 * Découpe « 1 000+ » en { value: 1000, suffix: '+', separator: ' ' } ; null pour ce qui n'est pas un nombre à
 * animer (texte, année comme « 2023 »).
 */
export function parseCount(text) {
    const match = String(text ?? '').trim().match(/^(\d{1,3}(?:[   ]\d{3})+|\d+)(\s*\+?)$/);

    if (!match) {
        return null;
    }

    const separator = (match[1].match(/[   ]/) || [''])[0];
    const value = Number(match[1].replace(/[   ]/g, ''));

    // Une année ne se compte pas (« 2023 » doit rester une date).
    if (!separator && value >= 1900 && value <= 2100) {
        return null;
    }

    return { value, suffix: match[2], separator };
}

/**
 * Texte affiché à un instant : progression de 0 à 1, adoucie en fin de course.
 */
export function countText(count, progress) {
    const eased = 1 - Math.pow(1 - Math.min(Math.max(progress, 0), 1), 3);
    const current = Math.round(count.value * eased);
    const digits = String(current);
    const grouped = count.separator ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, count.separator) : digits;

    return grouped + count.suffix;
}

export function initCountUp(root = document, { duration = 1400 } = {}) {
    const elements = [...root.querySelectorAll('[data-count-up]')];

    if (
        !elements.length ||
        !('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
        navigator.connection?.saveData === true
    ) {
        return;
    }

    const run = (element, count, finalText) => {
        const start = performance.now();
        const step = (now) => {
            const progress = (now - start) / duration;
            element.textContent = progress >= 1 ? finalText : countText(count, progress);

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };
        requestAnimationFrame(step);
    };

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                observer.unobserve(entry.target);
                const { count, finalText } = entry.target.__countUp;
                run(entry.target, count, finalText);
            });
        },
        { threshold: 0.6 },
    );

    elements.forEach((element) => {
        const finalText = element.textContent.trim();
        const count = parseCount(finalText);

        // Déjà à l'écran au chargement : on n'efface jamais un chiffre déjà lu.
        if (!count || element.getBoundingClientRect().top < window.innerHeight) {
            return;
        }

        element.__countUp = { count, finalText };
        element.textContent = countText(count, 0);
        observer.observe(element);
    });
}
