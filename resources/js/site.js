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

/**
 * Notifications sur téléphone (Web Push) : activation en un clic depuis « Notifications ».
 * Sur iPhone, elles ne fonctionnent qu'une fois le site ajouté à l'écran d'accueil (iOS 16.4 ou plus récent).
 */
Alpine.data('pushNotifications', ({ publicKey, subscribeUrl, unsubscribeUrl }) => ({
    supported: false,
    ios: false,
    standalone: false,
    permission: 'default',
    subscribed: false,
    busy: false,
    message: '',

    async init() {
        this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        this.standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        this.supported = Boolean(publicKey) && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        this.permission = 'Notification' in window ? Notification.permission : 'default';

        if (this.supported) {
            try {
                const registration = await navigator.serviceWorker.getRegistration();
                this.subscribed = Boolean(await registration?.pushManager.getSubscription());
            } catch (error) {
                this.subscribed = false;
            }
        }
    },

    async request(url, method, body) {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error('request');
        }

        return response.json();
    },

    async enable() {
        this.busy = true;
        this.message = '';

        try {
            this.permission = await Notification.requestPermission();

            if (this.permission !== 'granted') {
                this.message = 'Les notifications sont bloquées. Autorisez-les dans les réglages du navigateur (icône à gauche de l\'adresse du site), puis réessayez.';

                return;
            }

            const registration = await navigator.serviceWorker.register('/sw.js');
            await navigator.serviceWorker.ready;

            const key = Uint8Array.from(atob((publicKey + '='.repeat((4 - (publicKey.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/')), (c) => c.charCodeAt(0));
            const subscription = (await registration.pushManager.getSubscription())
                || (await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key }));

            const encodings = window.PushManager?.supportedContentEncodings || ['aesgcm'];
            const data = await this.request(subscribeUrl, 'POST', {
                ...subscription.toJSON(),
                contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
            });

            this.subscribed = true;
            this.message = data.status;
        } catch (error) {
            this.message = 'Activation impossible pour le moment. Réessayez dans quelques instants.';
        } finally {
            this.busy = false;
        }
    },

    async disable() {
        this.busy = true;

        try {
            const registration = await navigator.serviceWorker.getRegistration();
            const subscription = await registration?.pushManager.getSubscription();

            if (subscription) {
                await this.request(unsubscribeUrl, 'DELETE', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }

            this.subscribed = false;
            this.message = 'Notifications désactivées sur cet appareil.';
        } catch (error) {
            this.message = 'Désactivation impossible pour le moment. Réessayez.';
        } finally {
            this.busy = false;
        }
    },
}));

/**
 * Site installable : service worker (il ne met jamais en cache les pages privées, voir /sw.js).
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

const startAlpine = () => {
    window.Alpine = Alpine;
    Alpine.start();
    initReveal();
};

// Page d'un groupe de discussion : le code du chat est chargé seulement ici.
if (document.querySelector('[data-chat]')) {
    import('./chat.js')
        .then(({ default: registerChat }) => registerChat(Alpine))
        .finally(startAlpine);
} else {
    startAlpine();
}
