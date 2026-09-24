import Alpine from 'alpinejs';

/**
 * Apparition douce des éléments marqués data-reveal, uniquement pour ceux encore sous l'écran.
 * Le contenu reste toujours visible : l'animation n'est qu'un effet ajouté juste avant l'arrivée à l'écran.
 */
function initReveal() {
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-play');
                    observer.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px 10% 0px' },
    );

    document.querySelectorAll('[data-reveal]').forEach((element) => {
        // Déjà à l'écran au chargement : aucun effet, pour ne jamais masquer ce qui est lu.
        if (element.getBoundingClientRect().top < window.innerHeight) {
            return;
        }

        observer.observe(element);
    });
}

/**
 * Supprime les accents et met en minuscules pour une recherche tolérante.
 */
const normalize = (value) =>
    (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

/**
 * Catalogue : filtre par catégorie et recherche, sans rechargement de page.
 * Les filtres sont gardés dans l'adresse (ex. /formations?categorie=web) pour pouvoir être partagés.
 */
Alpine.data('catalogue', (items) => ({
    items,
    category: '',
    search: '',

    init() {
        const params = new URLSearchParams(window.location.search);
        this.category = params.get('categorie') || '';
        this.search = params.get('q') || '';

        this.$watch('category', () => this.syncUrl());
        this.$watch('search', () => this.syncUrl());
    },

    matches(id) {
        const item = this.items[id];

        if (!item) {
            return false;
        }

        if (this.category && item.category !== this.category) {
            return false;
        }

        const terms = normalize(this.search).split(/\s+/).filter(Boolean);

        return terms.every((term) => item.text.includes(term));
    },

    get count() {
        return Object.keys(this.items).filter((id) => this.matches(id)).length;
    },

    get hasFilters() {
        return this.category !== '' || this.search !== '';
    },

    reset() {
        this.category = '';
        this.search = '';
    },

    syncUrl() {
        const params = new URLSearchParams();

        if (this.category) params.set('categorie', this.category);
        if (this.search) params.set('q', this.search);

        const query = params.toString();
        window.history.replaceState({}, '', window.location.pathname + (query ? `?${query}` : ''));
    },
}));

window.Alpine = Alpine;
Alpine.start();

initReveal();
