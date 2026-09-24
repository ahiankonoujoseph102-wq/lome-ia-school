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

/**
 * Page de paiement : choix du pays puis du moyen de paiement, copie du numéro, aperçu de la capture.
 */
Alpine.data('mobileMoneyPayment', ({ methods, initialMethod }) => ({
    methods,
    country: '',
    method: '',
    copied: false,
    preview: null,

    init() {
        const initial = this.methods.find((item) => item.key === initialMethod);
        this.country = initial ? initial.country : (this.methods[0]?.country ?? '');
        this.method = initial ? initial.key : '';
    },

    get countryMethods() {
        return this.methods.filter((item) => item.country === this.country);
    },

    get selected() {
        return this.methods.find((item) => item.key === this.method) ?? null;
    },

    chooseCountry(code) {
        this.country = code;
        if (this.selected?.country !== code) {
            this.method = this.countryMethods.length === 1 ? this.countryMethods[0].key : '';
        }
    },

    chooseMethod(key) {
        this.method = key;
        this.$nextTick(() => this.$refs.details?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
    },

    syncCountry() {
        if (this.selected) {
            this.country = this.selected.country;
        }
    },

    async copyNumber() {
        if (!this.selected) {
            return;
        }

        try {
            await navigator.clipboard.writeText(this.selected.raw_number);
        } catch {
            const range = document.createRange();
            range.selectNodeContents(this.$refs.number);
            window.getSelection()?.removeAllRanges();
            window.getSelection()?.addRange(range);
        }

        this.copied = true;
        setTimeout(() => (this.copied = false), 2000);
    },

    previewProof(event) {
        const file = event.target.files[0];
        this.preview = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
    },
}));

/**
 * Admin « Contenu des formations » : aperçu de la vidéo pendant la saisie du lien.
 * Même reconnaissance des liens que côté serveur (App\Support\YouTube, App\Support\GoogleDrive).
 */
const videoSources = {
    youtube: {
        parse(value) {
            const input = (value || '').trim();

            if (/^[\w-]{11}$/.test(input)) {
                return input;
            }

            const match = input.match(/(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([\w-]{11})(?![\w-])/);

            return match ? match[1] : null;
        },
        embed: (id) => `https://www.youtube-nocookie.com/embed/${id}?rel=0&modestbranding=1&playsinline=1`,
        problem: () => 'Lien YouTube non reconnu pour l\'instant.',
    },
    drive: {
        parse(value) {
            const input = (value || '').trim();

            if (/^[\w-]{25,}$/.test(input)) {
                return input;
            }

            if (!/^https:\/\/(drive|docs|drive\.usercontent)\.google\.com\//.test(input) || videoSources.drive.isFolder(input)) {
                return null;
            }

            const match = input.match(/\/file\/(?:u\/\d+\/)?d\/([\w-]{20,})/) || input.match(/[?&]id=([\w-]{20,})/);

            return match ? match[1] : null;
        },
        isFolder: (value) => /drive\.google\.com\/(?:drive\/(?:u\/\d+\/)?)?folders\//.test(value),
        embed: (id) => `https://drive.google.com/file/d/${id}/preview`,
        problem: (value) => (videoSources.drive.isFolder(value)
            ? 'C\'est le lien d\'un dossier : ouvrez la vidéo elle-même dans Drive, puis copiez son lien.'
            : 'Lien Google Drive non reconnu pour l\'instant.'),
    },
};

Alpine.data('lessonVideoPreview', (provider, initialUrl) => ({
    url: initialUrl || '',
    source: videoSources[provider] ?? videoSources.youtube,

    get videoId() {
        return this.source.parse(this.url);
    },

    get embedUrl() {
        return this.videoId ? this.source.embed(this.videoId) : '';
    },

    get problem() {
        return this.source.problem(this.url);
    },
}));

window.Alpine = Alpine;
Alpine.start();

initReveal();
