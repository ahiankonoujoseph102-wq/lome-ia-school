import Alpine from 'alpinejs';
import { CALL_GREETING, createVoiceCall } from './ama-call.js';
import {
    NUDGE_AVOID,
    NUDGE_VISIBLE_MS,
    blinkDelay,
    createNudgeTimer,
    isSwipe,
    markNudgeDone,
    motionAllowed,
    nextBeat,
    nudgeDone,
} from './ama-presence.js';
import {
    buildVariables,
    createHelpTracker,
    createLineReader,
    handoffErrors,
    handoffFromDraft,
    handoffIsReady,
    lastVisitorQuestion,
    loadConversation,
    pick,
    pickFrenchVoice,
    saveConversation,
    speakable,
    SPEECH_RATE,
    speechSupport,
} from './ama.js';
import { isPlaybackError, listeningMessage, PLAYER_ORIGIN } from './home-video.js';
import { bottomOffset, createInstallState, isInstalled, isIos, overlaps } from './install-app.js';
import { initAnimatedBackgrounds } from './animated-background.js';

/**
 * Apparition douce des éléments marqués data-reveal, uniquement pour ceux encore sous l'écran.
 * Le contenu reste toujours visible : l'animation n'est qu'un effet ajouté juste avant l'arrivée à l'écran.
 */
function initReveal() {
    // Moins d'animations ou économie de données : aucune animation.
    if (
        !('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
        navigator.connection?.saveData === true
    ) {
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
 * Page de paiement en 4 étapes (pays, opérateur, paiement, capture), une étape à la fois.
 * La capture est envoyée dès qu'elle est choisie (un seul bouton), après un contrôle du type et de la
 * taille dans le navigateur (le serveur revérifie tout).
 */
Alpine.data('mobileMoneyPayment', ({ methods, initialMethod, initialStep, maxBytes, maxMb, labels }) => ({
    methods,
    labels,
    step: initialStep,
    country: '',
    method: '',
    copied: null,
    preview: null,
    fileError: '',
    sending: false,
    // Lien « tel: » avec * et # : inutilisable sur iPhone et iPad (y compris un iPad qui se présente comme un Mac).
    canDial: !(/iPhone|iPad|iPod/i.test(navigator.userAgent) || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1)),

    init() {
        const initial = this.methods.find((item) => item.key === initialMethod);
        this.country = initial ? initial.country : '';
        this.method = initial ? initial.key : '';
        this.tellAssistant();
    },

    // Étape, pays et opérateur transmis à l'assistante Ama (aucune donnée personnelle).
    tellAssistant() {
        const countryName = this.methods.find((item) => item.country === this.country)?.countryName ?? null;
        const operator = this.methods.find((item) => item.key === this.method)?.name ?? null;
        const detail = {
            numero_etape: this.step,
            etape: `étape ${this.step} sur 4 : ${['choisir le pays', 'choisir l\'opérateur Mobile Money', 'envoyer l\'argent par Mobile Money au numéro affiché', 'envoyer la capture d\'écran du paiement'][this.step - 1]}`,
            pays: countryName,
            operateur: this.step >= 3 ? operator : null,
        };
        // Après l'initialisation des autres composants (le bouton d'Ama peut être plus bas dans la page).
        queueMicrotask(() => window.dispatchEvent(new CustomEvent('ama-context', { detail })));
    },

    goTo(step) {
        this.step = step;
        this.tellAssistant();
        this.$nextTick(() => {
            const top = this.$refs.payment?.getBoundingClientRect().top ?? 0;
            if (top < 0) {
                this.$refs.payment.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    },

    chooseCountry(code) {
        this.country = code;
        if (this.methods.find((item) => item.key === this.method)?.country !== code) {
            this.method = '';
        }
        this.goTo(2);
    },

    chooseMethod(key) {
        this.method = key;
        this.goTo(3);
    },

    async copy(text, id) {
        await copyToClipboard(text);
        this.copied = id;
        setTimeout(() => {
            if (this.copied === id) {
                this.copied = null;
            }
        }, 2000);
    },

    sendProof(event) {
        const file = event.target.files[0];
        this.fileError = '';
        this.preview = null;

        if (!file) {
            return;
        }

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            this.fileError = 'La capture doit être une image JPG, PNG ou WebP.';
        } else if (file.size > maxBytes) {
            this.fileError = `La capture ne doit pas dépasser ${maxMb} Mo.`;
        }

        if (this.fileError) {
            event.target.value = '';
            window.dispatchEvent(new CustomEvent('ama-error', { detail: { cle: 'proof', texte: this.fileError, difficulte: 'capture_echec' } }));

            return;
        }

        this.preview = URL.createObjectURL(file);
        this.sending = true;
        this.$refs.proofForm.requestSubmit();
    },
}));

/**
 * Bouton « Copier » (liens d'affiliation). Si le presse-papiers est refusé, le texte est sélectionné.
 */
/**
 * Copie un texte dans le presse-papiers. Solution de secours (navigateur ancien, page sans https) :
 * sélection d'un champ temporaire et commande « copier » du navigateur.
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
        document.body.appendChild(field);
        field.select();
        let done = false;
        try {
            done = document.execCommand('copy');
        } catch {
            done = false;
        }
        field.remove();

        return done;
    }
}

Alpine.data('copyText', (text) => ({
    copied: false,

    async copy() {
        if (!(await copyToClipboard(text))) {
            const field = this.$refs.field;
            field?.focus();
            field?.select?.();
        }

        this.copied = true;
        setTimeout(() => (this.copied = false), 2000);
    },
}));

/**
 * Bouton « Partager cette formation » : sur téléphone, partage natif du navigateur s'il existe ;
 * sinon (ou sur ordinateur), menu « Copier le lien », « WhatsApp », « Facebook ».
 */
Alpine.data('shareFormation', ({ url, title }) => ({
    open: false,
    copied: false,
    failed: false,

    async share() {
        const phone = window.matchMedia?.('(pointer: coarse)').matches;

        if (phone && typeof navigator.share === 'function') {
            try {
                await navigator.share({ title, text: title, url });

                return;
            } catch (error) {
                // Partage fermé par l'élève : rien à faire. Autre erreur : on propose le menu.
                if (error?.name === 'AbortError') return;
            }
        }

        this.open = !this.open;
    },

    async copy() {
        this.copied = await copyToClipboard(url);
        this.failed = !this.copied;

        if (this.failed) {
            this.$refs.field?.select?.();

            return;
        }

        setTimeout(() => (this.copied = false), 2500);
    },
}));

/**
 * Coordonnées de retrait de l'affilié : les opérateurs proposés dépendent du pays choisi.
 */
Alpine.data('payoutForm', ({ methods, country, method }) => ({
    methods,
    country,
    method,

    get countryMethods() {
        return this.methods.filter((item) => item.country === this.country);
    },

    chooseCountry() {
        if (!this.countryMethods.some((item) => item.key === this.method)) {
            this.method = this.countryMethods.length === 1 ? this.countryMethods[0].key : '';
        }
    },
}));

/**
 * Consultation gratuite : choix du jour puis du créneau (heure de Lomé, avec l'équivalent au Bénin).
 */
Alpine.data('slotPicker', ({ days, selected }) => ({
    days,
    day: null,
    slot: selected || '',

    init() {
        const initial = this.days.find((item) => item.slots.some((slot) => slot.value === this.slot));
        this.day = (initial ?? this.days[0])?.date ?? null;
    },

    get current() {
        return this.days.find((item) => item.date === this.day) ?? null;
    },

    get chosen() {
        for (const item of this.days) {
            const found = item.slots.find((slot) => slot.value === this.slot);
            if (found) {
                return { ...found, title: item.title };
            }
        }

        return null;
    },

    chooseDay(date) {
        this.day = date;
    },

    chooseSlot(value) {
        this.slot = value;
        this.$nextTick(() => this.$refs.next?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    },
}));

/**
 * Admin « Contenu des formations » : aperçu de la vidéo pendant la saisie du lien.
 * Même reconnaissance des liens que côté serveur (App\Support\YouTube, App\Support\GoogleDrive).
 */
/**
 * Même nettoyage que App\Support\PastedLink côté serveur : caractères invisibles, espaces spéciaux,
 * texte autour du lien, majuscules du « https:// » et du nom de domaine, « https:// » manquant.
 */
const cleanPastedLink = (value) => {
    const text = (value || '')
        .replace(/[\u00AD\u180E\u200B-\u200F\u202A-\u202E\u2060-\u2064\u2066-\u2069\uFEFF]/g, '')
        .replace(/[\s\u00A0\u1680\u2000-\u200A\u2028\u2029\u202F\u205F\u3000]+/g, ' ')
        .trim();
    const domain = '(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z]{2,}';
    const match = text.match(/https?:\/+\S+/i)
        || text.match(new RegExp(`(?<![\\w@./-])${domain}[/?#]\\S*(?![\\w@-])`, 'i'))
        || text.match(new RegExp(`(?<![\\w@./-])${domain}(?![\\w@-])`, 'i'));

    if (!match) {
        return text;
    }

    const link = match[0]
        .replace(/^[<(["'«“]+/, '')
        .replace(/[.,;:!?)\]>"'»”]+$/, '')
        .replace(/^https?:\/+/i, '')
        .replace(/^[^/?#]+/, (host) => host.toLowerCase());

    return `https://${link}`;
};

const videoSources = {
    youtube: {
        parse(value) {
            const input = cleanPastedLink(value);

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
            const input = cleanPastedLink(value);

            if (/^[\w-]{25,}$/.test(input)) {
                return input;
            }

            if (!/^https:\/\/(drive|docs|drive\.usercontent)\.google\.com\//.test(input) || videoSources.drive.isFolder(input)) {
                return null;
            }

            const match = input.match(/\/file\/(?:u\/\d+\/)?d\/([\w-]{20,})/) || input.match(/[?&]id=([\w-]{20,})/);

            return match ? match[1] : null;
        },
        isFolder: (value) => /drive\.google\.com\/(?:drive\/(?:u\/\d+\/)?)?folders\//.test(cleanPastedLink(value)),
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
 * Vidéo d'accueil : seule l'image d'aperçu est chargée ; le lecteur YouTube (youtube-nocookie.com) n'est ajouté
 * qu'au toucher du bouton lecture. Aucune lecture automatique. Si la vidéo est illisible (aperçu introuvable, ou
 * erreur signalée par le lecteur), un message simple et le lien « Regarder sur YouTube » remplacent la zone vide.
 */
Alpine.data('homeVideo', () => ({
    playing: false,
    failed: false,
    listener: null,

    play() {
        if (this.playing) {
            return;
        }

        this.playing = true;
        this.listener = (event) => {
            const frame = this.$refs.frame;

            if (frame && event.source === frame.contentWindow && isPlaybackError(event.origin, event.data)) {
                this.fail();
            }
        };
        window.addEventListener('message', this.listener);
    },

    listen() {
        this.$refs.frame?.contentWindow?.postMessage(listeningMessage(), PLAYER_ORIGIN);
    },

    fail() {
        this.failed = true;
        this.playing = false;

        if (this.listener) {
            window.removeEventListener('message', this.listener);
            this.listener = null;
        }
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

/**
 * « Installer l'application » (voir resources/js/install-app.js) : état partagé par la bannière (partiel
 * install-banner, pages de App\Support\InstallApp::BANNER_PAGES) et le lien du pied de page (partiel install-link).
 * L'événement « beforeinstallprompt » est capté dès l'en-tête de la page (layout) : window.lisInstallPrompt.
 */
const storageOf = (name) => {
    try {
        return window[name];
    } catch {
        return null;
    }
};

const installState = createInstallState({
    ios: isIos({ userAgent: navigator.userAgent, platform: navigator.platform, maxTouchPoints: navigator.maxTouchPoints }),
    installed: isInstalled({ standaloneDisplay: window.matchMedia?.('(display-mode: standalone)').matches, navigatorStandalone: navigator.standalone === true }),
    touch: Boolean(window.matchMedia?.('(pointer: coarse)').matches),
    storage: storageOf('localStorage'),
    session: storageOf('sessionStorage'),
    schedule: (callback, ms) => window.setTimeout(callback, ms),
});

Alpine.store('installApp', installState);
Alpine.store('installApp').start();
Alpine.store('installApp').installable(window.lisInstallPrompt);
window.addEventListener('lis-installable', () => Alpine.store('installApp').installable(window.lisInstallPrompt));
window.addEventListener('appinstalled', () => Alpine.store('installApp').markInstalled());

/**
 * Bannière en bas de l'écran : au-dessus des barres fixées en bas (barre de prix, barre de l'espace élève, repérées
 * par data-bottom-bar), et invisible tant qu'elle passerait au-dessus d'un bouton principal (.btn-gold : « Je
 * m'inscris », « Acheter maintenant »…) ou d'un élément marqué data-install-avoid. Place réservée en bas de page et
 * bouton d'Ama remonté pendant qu'elle est affichée (classe install-banner-shown, site.css).
 */
Alpine.data('installBanner', () => ({
    bottom: 12,
    covering: false,
    frame: null,

    init() {
        const update = () => {
            if (this.frame === null) {
                this.frame = window.requestAnimationFrame(() => {
                    this.frame = null;
                    this.place();
                });
            }
        };

        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        this.$watch('$store.installApp.bannerVisible', (shown) => this.toggle(shown));
        this.toggle(this.$store.installApp.bannerVisible);
    },

    toggle(shown) {
        document.body.classList.toggle('install-banner-shown', shown);
        this.$nextTick(() => this.place());
    },

    place() {
        if (!this.$store.installApp.bannerVisible) {
            return;
        }

        const bars = [...document.querySelectorAll('[data-bottom-bar]')]
            .map((bar) => bar.getBoundingClientRect())
            .filter((rect) => rect.height > 1)
            .map((rect) => rect.top);
        this.bottom = bottomOffset(window.innerHeight, bars);

        // Position calculée (et non lue) : le nouveau « bottom » n'est appliqué qu'au prochain rendu.
        const rect = this.$el.getBoundingClientRect();
        const box = { left: rect.left, right: rect.right, bottom: window.innerHeight - this.bottom, top: window.innerHeight - this.bottom - rect.height };
        document.body.style.setProperty('--install-banner-space', `${Math.ceil(rect.height) + 12}px`);
        this.covering = [...document.querySelectorAll('.btn-gold, [data-install-avoid]')].some(
            (element) => !this.$el.contains(element) && overlaps(box, element.getBoundingClientRect()),
        );
    },
}));

/**
 * Assistante vocale IA (agent ElevenLabs) : bouton flottant des pages publiques (partiel voice-assistant).
 * Le script officiel du widget n'est chargé qu'au toucher du bouton ; l'élément <elevenlabs-convai> s'ouvre
 * alors directement (default-expanded), en bas à gauche, et notre bouton disparaît.
 * Si le widget ne s'affiche pas (réseau, adresse non autorisée chez ElevenLabs), un message clair le dit
 * et le bouton revient.
 */
let voiceAssistantScript = null;

const loadVoiceAssistantScript = (src) => {
    voiceAssistantScript ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.type = 'text/javascript';
        script.onload = resolve;
        script.onerror = () => {
            script.remove();
            voiceAssistantScript = null;
            reject(new Error('script'));
        };
        document.head.appendChild(script);
    });

    return voiceAssistantScript;
};

const within = (promise, milliseconds) =>
    Promise.race([promise, new Promise((resolve, reject) => setTimeout(() => reject(new Error('délai')), milliseconds))]);

// Le widget a-t-il dessiné quelque chose (sa configuration a été reçue d'ElevenLabs) ?
const widgetIsVisible = (widget) =>
    [...(widget.shadowRoot?.querySelectorAll('*') ?? [])].some((node) => {
        const box = node.getBoundingClientRect();

        return box.width > 0 && box.height > 0;
    });

/**
 * Bouton « Parler avec notre assistante » : Ama (Gemini, par défaut) ou l'agent ElevenLabs (config.provider).
 *
 * Ama (Gemini) : fenêtre de discussion de la page. L'écrit par défaut ; chaque message part au serveur du site
 * (route assistant.message), qui seul appelle Gemini : la clé n'est jamais dans le navigateur. Micro (reconnaissance
 * vocale du navigateur) et « Lire les réponses à voix haute » (synthèse vocale du navigateur) seulement si le
 * navigateur les gère ; le micro ne s'ouvre qu'au toucher de son bouton. Sans réponse du serveur : message clair et
 * bouton « Écrivez-nous sur WhatsApp », jamais d'erreur technique.
 * Mode appel (« Parler avec Ama », si le navigateur sait écouter ET lire à voix haute) : conversation à la voix
 * comme au téléphone, voir resources/js/ama-call.js. Il ne démarre qu'au toucher du bouton ; le micro se coupe
 * toujours en raccrochant, en fermant la fenêtre ou en quittant la page.
 *
 * Agent ElevenLabs :
 * - Contexte de la page (sans donnée personnelle, voir ama.js) envoyé à l'agent en variables dynamiques :
 *   attribut « dynamic-variables » du widget, remis à jour au démarrage de chaque appel par l'événement
 *   officiel « elevenlabs-convai:call » (detail.config.dynamicVariables).
 * - Changements pendant l'appel (étape, pays, opérateur, erreur) : événement officiel
 *   « elevenlabs-agent:contextual-update » (widget avec allow-events="true"), sans interrompre Ama.
 * - Difficulté repérée (erreur répétée, code promo refusé, capture refusée, étape trop longue,
 *   allers-retours) : petite bulle « Vous semblez bloqué ? », une fois par page et par visite. Elle ouvre
 *   seulement le panneau d'Ama : l'appel et le micro ne démarrent jamais sans que le visiteur touche
 *   lui-même « Démarrer l'appel » dans le widget.
 */
Alpine.data('voiceAssistant', (config) => ({
    loading: false,
    opened: false,
    error: '',
    bubble: false,
    covering: false,
    context: pick(config.context),
    widget: null,
    tracker: null,
    stepTimer: null,
    // Ama (Gemini) : fenêtre de discussion.
    provider: config.provider,
    messages: [],
    draft: '',
    notice: '',
    sending: false,
    conversation: null,
    storage: null,
    canListen: false,
    canSpeak: false,
    listening: false,
    recognition: null,
    usedVoice: false,
    speakReplies: false,
    suggestions: config.suggestions ?? [],
    // Mode appel.
    canCall: false,
    call: null,
    callState: 'idle',
    callEnd: null,
    bargeInUnavailable: false,
    // « Transmettre au fondateur » : carte de confirmation (ou formulaire de relève) et fiche proposée pendant un appel.
    handoff: null,
    callHandoff: null,
    // Visage animé d'Ama (attributs lus par le CSS) : clignement, signe de la main, bouche qui suit la synthèse vocale,
    // entrée du grand personnage. « motion » faux : moins d'animations demandé ou économie de données (visage fixe).
    character: Boolean(config.character),
    motion: true,
    faceBlink: false,
    faceWave: false,
    faceTalking: false,
    faceBeat: 'a',
    faceEnter: false,
    blinkTimer: null,
    enterTimer: null,
    // Bulle de présentation (texte écrit à l'avance, aucun appel à l'IA), une fois par visite.
    nudge: false,
    nudgeText: config.nudge?.text ?? '',
    nudgeShown: false,
    nudgeAbove: false,
    nudgeWatch: null,
    nudgeTimer: null,
    nudgeHideTimer: null,
    swipe: null,

    init() {
        let storage = null;
        try {
            storage = window.sessionStorage;
        } catch {
            storage = null;
        }

        this.tracker = createHelpTracker({ storage, pageKey: config.pageKey, enabled: config.proactive });
        this.storage = storage;
        this.motion = motionAllowed(window);

        if (this.character) {
            this.scheduleBlink();
        }

        this.initNudge();

        // Voix : seulement ce que le navigateur sait faire (rien n'est demandé ni ouvert ici).
        const speech = speechSupport(window);
        this.canListen = speech.listen;
        this.canSpeak = speech.speak;
        // Mode appel : il faut les deux (sinon le bouton n'apparaît pas et l'écrit reste disponible).
        this.canCall = speech.listen && speech.speak && Boolean(config.callEndpoint);

        // Le micro se coupe toujours en quittant la page ou en passant à une autre application.
        window.addEventListener('pagehide', () => this.hangUp());
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.hangUp();
            }
        });
        try {
            this.speakReplies = this.canSpeak && storage?.getItem('ama-voix') === '1';
        } catch {
            this.speakReplies = false;
        }

        if (this.context.difficulte) {
            this.difficulty(this.context.difficulte);
        } else if (this.context.erreur) {
            const found = this.tracker.errorShown(this.context.erreur);
            if (found) {
                this.difficulty(found);
            }
        }

        // La bulle ne reste jamais au-dessus d'un bouton ou d'un champ : elle s'efface le temps qu'il passe dessous.
        let frame = null;
        const recheck = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => this.checkCovering());
        };
        window.addEventListener('scroll', recheck, { passive: true });
        window.addEventListener('resize', recheck);

        // Informations envoyées par les autres composants de la page (paiement : étape, pays, opérateur, erreurs).
        window.addEventListener('ama-context', (event) => this.update(event.detail ?? {}));
        window.addEventListener('ama-error', (event) => {
            const detail = event.detail ?? {};
            this.update({ erreur: { cle: detail.cle ?? '', texte: detail.texte ?? '' } });
            if (detail.difficulte) {
                this.difficulty(detail.difficulte);
            }
        });
    },

    variables() {
        return buildVariables(this.context, { difficulties: config.difficulties, greeting: config.greeting });
    },

    update(values) {
        const before = JSON.stringify(this.context);
        this.context = pick({ ...this.context, ...values });

        if (typeof values.numero_etape === 'number') {
            this.watchStep(values.numero_etape);
        }

        if (JSON.stringify(this.context) !== before) {
            this.sendToWidget();
        }
    },

    // Étape du paiement : trop longue sans avancer, ou allers-retours entre deux étapes.
    watchStep(step) {
        clearTimeout(this.stepTimer);

        const found = this.tracker.stepChanged(step);
        if (found) {
            this.difficulty(found);
        }

        this.stepTimer = setTimeout(() => this.difficulty('etape_longue'), config.helpDelay * 1000);
    },

    difficulty(key) {
        if (this.opened) {
            // Ama est déjà ouverte : elle est prévenue sans bulle.
            this.update({ difficulte: key });

            return;
        }

        if (this.tracker.offer()) {
            this.closeNudge();
            this.context = pick({ ...this.context, difficulte: key });
            this.showBubble(true);
        }
    },

    // Place ajoutée en bas de page tant que la bulle est affichée (le bas de la page reste accessible).
    showBubble(shown) {
        this.bubble = shown;
        document.body.classList.toggle('ama-help-shown', shown);
        this.$nextTick(() => this.checkCovering());
    },

    checkCovering() {
        // L'aide « Vous semblez bloqué ? » a la priorité sur la bulle de présentation.
        const bubble = this.bubble ? this.$refs.bubble : this.nudge ? this.$refs.nudgeBubble : null;

        if (!bubble) {
            this.covering = false;

            return;
        }

        const box = bubble.getBoundingClientRect();

        // Bulle pas encore dessinée (affichage en cours) : nouvel essai à l'image suivante.
        if (box.width === 0) {
            requestAnimationFrame(() => this.checkCovering());

            return;
        }

        // La présentation ne recouvre jamais un bouton important (paiement, inscription, copier, envoi de capture…) ;
        // l'aide, qui répond à un blocage, évite aussi les liens et les étiquettes de champ.
        const selector = this.bubble ? 'a, button, input:not([type=hidden]), select, textarea, label[for]' : NUDGE_AVOID;
        const elements = [...document.querySelectorAll(selector)].filter((element) => !this.$el.contains(element)).map((element) => element.getBoundingClientRect());
        const hits = (left, top) =>
            elements.some((rect) => rect.width > 1 && rect.height > 1 && rect.left < left + box.width && left < rect.right && rect.top < top + box.height && top < rect.bottom);

        if (this.bubble || !this.$refs.launcher) {
            this.covering = hits(box.left, box.top);
        } else {
            // Présentation : à côté du visage, sinon au-dessus de lui (téléphone et tablette), sinon elle attend une
            // place libre (défilement).
            const launcher = this.$refs.launcher.getBoundingClientRect();
            const beside = this.nudgeAbove ? [launcher.left - 8 - box.width, launcher.bottom - box.height] : [box.left, box.top];
            const above = [launcher.right - box.width, launcher.top - 8 - box.height];
            const canStack = !window.matchMedia('(min-width: 64rem)').matches;

            let place = null;
            if (!hits(...beside)) {
                place = false;
            } else if (canStack && above[0] >= 0 && above[1] >= 0 && !hits(...above)) {
                place = true;
            }

            if (place === null) {
                this.covering = true;
            } else if (place !== this.nudgeAbove) {
                // Elle change de place : cachée le temps du déplacement, puis vérifiée à sa vraie position.
                this.nudgeAbove = place;
                this.covering = true;
                requestAnimationFrame(() => this.checkCovering());
            } else {
                this.covering = hits(box.left, box.top);
            }
        }

        if (this.nudge && !this.bubble && !this.nudgeShown && !this.covering) {
            this.nudgeVisible();
        }
    },

    // --- Visage animé et présentation d'Ama -----------------------------------------------------------------

    // Clignement des yeux toutes les 3 à 6 secondes (un minuteur, rien entre deux clignements).
    scheduleBlink() {
        if (!this.motion) {
            return;
        }

        clearTimeout(this.blinkTimer);
        this.blinkTimer = setTimeout(() => {
            if (document.visibilityState === 'visible') {
                this.faceBlink = true;
                setTimeout(() => {
                    this.faceBlink = false;
                }, 140);
            }
            this.scheduleBlink();
        }, blinkDelay());
    },

    // Petit signe de la main (sans mouvement si « moins d'animations » : la main s'affiche seulement).
    waveHand() {
        if (!this.character) {
            return;
        }

        this.faceWave = true;
        setTimeout(() => {
            this.faceWave = false;
        }, 2500);
    },

    // Après environ 8 secondes sur le site (cumulées d'une page à l'autre), une seule fois par visite.
    initNudge() {
        if (!config.nudge?.text || nudgeDone(this.storage)) {
            return;
        }

        this.nudgeTimer = createNudgeTimer({ storage: this.storage, seconds: config.nudge.seconds, onDue: () => this.offerNudge() });

        if (document.visibilityState === 'visible') {
            this.nudgeTimer.arm();
        }

        document.addEventListener('visibilitychange', () => (document.visibilityState === 'hidden' ? this.nudgeTimer.pause() : this.nudgeTimer.arm()));
        window.addEventListener('pagehide', () => this.nudgeTimer.pause());
    },

    offerNudge() {
        if (this.opened || this.bubble || this.nudge) {
            // Ama est déjà ouverte ou l'aide « bloqué » est affichée : pas de présentation.
            this.nudgeTimer?.stop();
            markNudgeDone(this.storage);

            return;
        }

        this.nudge = true;
        this.$nextTick(() => this.checkCovering());
        // La page change sous la bulle (étape suivante du paiement, menu ouvert…) : la place est revérifiée deux fois
        // par seconde tant qu'elle est là, pour ne jamais recouvrir un bouton qui vient d'apparaître.
        this.nudgeWatch = setInterval(() => this.checkCovering(), 500);
    },

    // La bulle est réellement visible (libre de tout bouton) : un signe de la main, puis elle s'efface seule.
    nudgeVisible() {
        this.nudgeShown = true;
        this.nudgeTimer?.stop();
        markNudgeDone(this.storage);
        document.body.classList.add('ama-help-shown');
        this.waveHand();
        this.nudgeHideTimer = setTimeout(() => this.closeNudge(), NUDGE_VISIBLE_MS);
    },

    closeNudge() {
        this.nudgeTimer?.stop();
        clearTimeout(this.nudgeHideTimer);
        clearInterval(this.nudgeWatch);

        // Ama touchée, aide « bloqué » affichée ou bulle fermée : plus de présentation pendant cette visite.
        if (this.nudgeTimer) {
            markNudgeDone(this.storage);
        }

        if (!this.nudge) {
            return;
        }

        this.nudge = false;

        if (!this.bubble) {
            this.covering = false;
            document.body.classList.remove('ama-help-shown');
        }
    },

    // Glissement sur le côté ou croix : la bulle se ferme et ne revient pas.
    swipeStart(event) {
        this.swipe = { x: event.clientX, y: event.clientY };
    },

    swipeEnd(event) {
        if (this.swipe && isSwipe(event.clientX - this.swipe.x, event.clientY - this.swipe.y)) {
            this.closeNudge();
        }

        this.swipe = null;
    },

    closeBubble() {
        this.showBubble(false);
    },

    sendToWidget() {
        if (!this.widget) {
            return;
        }

        const variables = this.variables();
        this.widget.setAttribute('dynamic-variables', JSON.stringify(variables));
        this.widget.dispatchEvent(new CustomEvent('elevenlabs-agent:contextual-update', { detail: { message: `Mise à jour du contexte de la page :\n${variables.contexte}` } }));
    },

    async open() {
        if (this.loading || this.opened) {
            return;
        }

        this.showBubble(false);
        this.closeNudge();

        if (this.provider === 'gemini') {
            this.openChat();

            return;
        }

        this.loading = true;
        this.error = '';

        const widget = document.createElement('elevenlabs-convai');
        widget.setAttribute('agent-id', config.agentId);
        // Même côté que notre bouton : à droite sur téléphone et tablette, à gauche sur ordinateur.
        widget.setAttribute('placement', window.matchMedia('(min-width: 64rem)').matches ? 'bottom-left' : 'bottom-right');
        // Panneau ouvert, sans démarrer l'appel : le visiteur touche lui-même « Démarrer l'appel ».
        widget.setAttribute('default-expanded', 'true');
        widget.setAttribute('allow-events', 'true');
        widget.setAttribute('text-contents', JSON.stringify(config.texts));
        widget.setAttribute('dynamic-variables', JSON.stringify(this.variables()));
        // Au démarrage de chaque appel : contexte le plus récent.
        widget.addEventListener('elevenlabs-convai:call', (event) => {
            if (event.detail?.config) {
                event.detail.config.dynamicVariables = this.variables();
            }
        });

        try {
            await within(loadVoiceAssistantScript(config.scriptUrl), 20000);
            document.body.appendChild(widget);
            await within(customElements.whenDefined('elevenlabs-convai'), 10000);

            const shown = await new Promise((resolve) => {
                const started = Date.now();
                const check = () => {
                    if (widgetIsVisible(widget)) {
                        resolve(true);
                    } else if (Date.now() - started > 12000) {
                        resolve(false);
                    } else {
                        setTimeout(check, 250);
                    }
                };
                check();
            });

            if (!shown) {
                throw new Error('affichage');
            }

            this.widget = widget;
            this.opened = true;
        } catch {
            widget.remove();
            this.error = 'L\'assistante n\'est pas disponible pour le moment. Réessayez dans quelques instants.';
        } finally {
            this.loading = false;
        }
    },

    // Ama (Gemini) : ouvre la fenêtre, avec la conversation de l'onglet et un message d'accueil adapté
    // à la difficulté repérée (affiché sans appel à l'IA).
    openChat() {
        const stored = loadConversation(this.storage);
        this.conversation = stored.id;
        this.messages = stored.messages;

        const difficulty = this.context.difficulte && config.difficulties[this.context.difficulte];
        const greeting = difficulty ? difficulty[1] : config.greeting;

        if (this.messages.length === 0 || (difficulty && this.messages.at(-1)?.text !== greeting)) {
            this.messages.push({ from: 'ama', text: greeting, fallback: false });
        }

        this.opened = true;
        document.documentElement.classList.add('ama-open');
        this.scrollDown();
        this.$nextTick(() => {
            if (window.matchMedia('(pointer: fine)').matches) {
                this.$refs.input?.focus();
            }
        });
    },

    closeChat() {
        this.hangUp();
        this.callEnd = null;
        this.recognition?.abort();
        this.listening = false;
        if (this.canSpeak) {
            window.speechSynthesis.cancel();
        }
        this.opened = false;
        document.documentElement.classList.remove('ama-open');
        this.persist();
    },

    persist() {
        saveConversation(this.storage, this.conversation, this.messages);
    },

    scrollDown() {
        this.$nextTick(() => {
            const list = this.$refs.messages;
            if (list) {
                list.scrollTop = list.scrollHeight;
            }
        });
    },

    get visitorHasWritten() {
        return this.messages.some((message) => message.from === 'visitor');
    },

    // Entrée envoie le message sur ordinateur ; sur téléphone, elle passe à la ligne (bouton « Envoyer »).
    enter(event) {
        if (!event.shiftKey && !event.isComposing && window.matchMedia('(pointer: fine)').matches) {
            event.preventDefault();
            this.send();
        }
    },

    async send(text = this.draft, voice = false) {
        const message = String(text ?? '').trim();

        if (message === '' || this.sending) {
            return;
        }

        if (message.length > config.maxLength) {
            this.notice = `Votre message est trop long : ${config.maxLength} caractères au maximum. Raccourcissez-le un peu.`;

            return;
        }

        this.notice = '';
        this.draft = '';
        this.messages.push({ from: 'visitor', text: message, fallback: false });
        this.sending = true;
        this.persist();
        this.scrollDown();

        let data = null;
        try {
            const response = await within(
                fetch(config.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ message, conversation: this.conversation, context: pick(this.context), voice: voice || this.usedVoice }),
                }),
                45000,
            );
            data = response.ok ? await response.json() : null;
        } catch {
            data = null;
        }

        this.sending = false;

        if (data?.notice) {
            // Message refusé avant l'envoi à l'IA (trop long, vide) : le texte revient dans le champ.
            this.messages.pop();
            this.draft = message;
            this.notice = data.notice;
        } else if (data && typeof data.reply === 'string' && data.reply !== '') {
            this.conversation = data.conversation ?? this.conversation;
            this.messages.push({ from: 'ama', text: data.reply, fallback: data.fallback === true });
            this.speak(data.reply);
            if (data.handoff) {
                // Récapitulatif d'Ama : carte « Envoyer à Coach Joseph » / « Corriger » (rien ne part sans le bouton).
                this.showHandoff(data.handoff, 'chat');
            } else if (data.fallback === true) {
                this.openHandoffForm();
            }
        } else {
            // Réseau coupé, session expirée, serveur indisponible : jamais d'erreur technique.
            this.messages.push({ from: 'ama', text: 'Ama ne peut pas répondre pour le moment. Notre équipe vous répond sur WhatsApp.', fallback: true });
            this.openHandoffForm();
        }

        this.persist();
        this.scrollDown();
    },

    // Micro : seulement au toucher de son bouton. Refusé, ou rien entendu : l'écrit reste disponible, sans message d'erreur.
    listen() {
        if (this.listening) {
            this.recognition?.stop();

            return;
        }

        const { Recognition } = speechSupport(window);

        if (!Recognition || this.sending) {
            return;
        }

        if (this.canSpeak) {
            window.speechSynthesis.cancel();
        }

        const recognition = new Recognition();
        recognition.lang = 'fr-FR';
        recognition.interimResults = true;
        recognition.continuous = false;
        recognition.maxAlternatives = 1;

        let heard = '';
        recognition.onresult = (event) => {
            let interim = '';
            for (let index = event.resultIndex; index < event.results.length; index += 1) {
                const result = event.results[index];
                if (result.isFinal) {
                    heard += result[0].transcript;
                } else {
                    interim += result[0].transcript;
                }
            }
            this.draft = `${heard}${interim}`.trim();
        };
        recognition.onerror = () => {};
        recognition.onend = () => {
            this.listening = false;
            this.recognition = null;

            if (heard.trim() !== '') {
                // Le visiteur a parlé : les réponses sont lues à voix haute (il peut l'arrêter).
                this.usedVoice = true;
                this.setSpeak(this.canSpeak);
                this.send(heard, true);
            }
        };

        try {
            recognition.start();
            this.recognition = recognition;
            this.listening = true;
        } catch {
            this.listening = false;
        }
    },

    setSpeak(on) {
        this.speakReplies = on && this.canSpeak;
        if (!this.speakReplies && this.canSpeak) {
            window.speechSynthesis.cancel();
        }
        try {
            this.storage?.setItem('ama-voix', this.speakReplies ? '1' : '0');
        } catch {
            // Stockage refusé : le choix vaut pour cette page.
        }
    },

    speak(text) {
        if (!this.speakReplies || !this.canSpeak) {
            return;
        }

        const synth = window.speechSynthesis;
        synth.cancel();
        const utterance = new window.SpeechSynthesisUtterance(speakable(text));
        utterance.lang = 'fr-FR';
        utterance.rate = SPEECH_RATE;
        const voice = pickFrenchVoice(synth.getVoices());
        if (voice) {
            utterance.voice = voice;
        }
        // L'en-tête de la discussion : la bouche d'Ama suit la lecture à voix haute.
        utterance.onstart = () => {
            this.faceTalking = true;
        };
        utterance.onboundary = () => {
            this.faceBeat = nextBeat(this.faceBeat);
        };
        utterance.onend = () => {
            this.faceTalking = false;
        };
        utterance.onerror = () => {
            this.faceTalking = false;
        };
        synth.speak(utterance);
    },

    // Mode appel : seulement au toucher du bouton « Parler avec Ama » (jamais tout seul).
    startCall() {
        if (!this.canCall || this.call || this.sending) {
            return;
        }

        const { Recognition } = speechSupport(window);
        this.recognition?.abort();
        this.listening = false;
        this.notice = '';
        this.callEnd = null;
        this.usedVoice = true;
        let amaIndex = null;

        this.bargeInUnavailable = false;
        this.call = createVoiceCall({
            Recognition,
            synth: window.speechSynthesis,
            Utterance: window.SpeechSynthesisUtterance,
            ask: (text, options) => this.askAloud(text, options),
            now: () => (window.performance?.now ? window.performance.now() : Date.now()),
            // Interruption à la voix : seulement si l'admin l'a activée (expérimental).
            bargeIn: Boolean(config.voiceInterrupt),
            onBargeInUnavailable: () => {
                this.bargeInUnavailable = true;
            },
            onMisheard: (text) => {
                // Ama demande de répéter : rien n'est envoyé au serveur (aucun message compté).
                this.messages.push({ from: 'ama', text, fallback: false });
                amaIndex = null;
                this.scrollDown();
            },
            onTiming: (durations, result) => this.sendTiming(durations, result),
            onState: (state) => {
                this.callState = state;
                if (state !== 'speaking') {
                    this.faceTalking = false;
                }
            },
            // La bouche suit la synthèse vocale du navigateur : elle bouge de « début » à « fin » de chaque phrase lue,
            // et change de rythme à chaque mot repéré (pas tous les téléphones les signalent).
            onSpeech: (event) => {
                if (event === 'start') {
                    this.faceTalking = true;
                } else if (event === 'boundary') {
                    this.faceBeat = nextBeat(this.faceBeat);
                } else {
                    this.faceTalking = false;
                }
            },
            onVisitor: (text) => {
                this.messages.push({ from: 'visitor', text, fallback: false });
                amaIndex = null;
                this.persist();
                this.scrollDown();
            },
            onAmaText: (text, final = false, fallback = false) => {
                if (amaIndex === null) {
                    this.messages.push({ from: 'ama', text, fallback });
                    amaIndex = this.messages.length - 1;
                } else {
                    this.messages[amaIndex] = { from: 'ama', text, fallback };
                }
                if (final) {
                    this.persist();
                }
                this.scrollDown();
            },
            onEnd: (reason) => {
                this.call = null;
                this.callState = 'idle';
                this.faceTalking = false;
                // Ama a proposé de transmettre la question pendant l'appel : la fiche s'affiche maintenant, pour écrire
                // la coordonnée (jamais dictée à voix haute).
                if (this.callHandoff) {
                    this.showHandoff(this.callHandoff, 'call', true);
                    this.callHandoff = null;
                }
                this.persist();
                this.scrollDown();
                if (reason === 'limit' || reason === 'error') {
                    // Limite atteinte ou Ama indisponible : l'écran d'appel reste, avec le bouton WhatsApp.
                    this.callEnd = { reason };
                } else if (reason === 'denied') {
                    this.notice = 'Le micro n\'est pas autorisé sur ce navigateur. Vous pouvez écrire votre question.';
                }
            },
        });
        if (this.messages.at(-1)?.text !== CALL_GREETING) {
            this.messages.push({ from: 'ama', text: CALL_GREETING, fallback: false });
        }
        // Le grand personnage apparaît en se dessinant (sans animation si le téléphone préfère moins de mouvement).
        this.faceEnter = true;
        clearTimeout(this.enterTimer);
        this.enterTimer = setTimeout(() => {
            this.faceEnter = false;
        }, 2200);
        this.call.start();
    },

    get callLabel() {
        return { listening: 'Ama vous écoute…', thinking: 'Ama réfléchit…', speaking: 'Ama parle…' }[this.callState] ?? 'Appel en cours…';
    },

    get callHint() {
        return {
            listening: 'Parlez quand vous voulez. Ama répond quand vous avez fini.',
            thinking: 'Un instant, s\'il vous plaît.',
            speaking: config.voiceInterrupt && !this.bargeInUnavailable
                ? 'Parlez ou touchez le cercle pour lui couper la parole.'
                : 'Touchez le cercle pour lui couper la parole.',
        }[this.callState] ?? '';
    },

    // Durées d'un tour de l'appel (millisecondes, sans aucun texte) : ajoutées à la mesure du serveur pour l'admin.
    sendTiming(durations, result) {
        if (!config.timingEndpoint || !result?.timing || !result?.conversation) {
            return;
        }

        try {
            fetch(config.timingEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ timing: result.timing, conversation: result.conversation, ...durations }),
            }).catch(() => {});
        } catch {
            // Mesure perdue : sans importance.
        }
    },

    // Toucher le cercle : Ama s'arrête de parler et le micro s'ouvre (pendant l'écoute : la phrase part tout de suite).
    touchCircle() {
        this.call?.touch();
    },

    hangUp() {
        this.call?.hangUp();
    },

    closeCallEnd() {
        this.callEnd = null;
        this.scrollDown();
    },

    // Appel terminé par une limite ou une panne : « Transmettre ma question à Coach Joseph ».
    handoffAfterCall() {
        this.closeCallEnd();
        if (!this.handoff) {
            this.openHandoffForm('call');
        }
        this.scrollDown();
    },

    // Carte de confirmation (prête : lecture seule avec « Envoyer » et « Corriger » ; incomplète : champs à remplir).
    showHandoff(draft, source, editing = false) {
        const handoff = handoffFromDraft(draft, { source, question: lastVisitorQuestion(this.messages) });
        this.handoff = { ...handoff, editing: editing || !handoffIsReady(handoff), sending: false, errors: {}, website: '' };
        this.scrollDown();
    },

    // Formulaire de relève (limite atteinte ou panne) : mêmes champs, à côté du bouton WhatsApp.
    openHandoffForm(source = 'form') {
        if (this.handoff) {
            return;
        }

        this.handoff = { ...handoffFromDraft({}, { source, question: lastVisitorQuestion(this.messages) }), editing: true, sending: false, errors: {}, website: '' };
        this.scrollDown();
    },

    // Lien permanent « Poser ma question à Coach Joseph » : carte vide, sans passer par Gemini (fonctionne même si
    // Ama oublie la ligne technique, est en panne ou a atteint sa limite). Une carte déjà ouverte reste telle quelle.
    askJoseph() {
        if (!this.handoff) {
            this.handoff = { ...handoffFromDraft({}, { source: 'link' }), editing: true, sending: false, errors: {}, website: '' };
        } else {
            this.handoff.editing = true;
        }
        // Haut de la carte en vue (le prénom est le premier champ à remplir).
        this.$nextTick(() => {
            const card = this.$el.querySelector('[data-test=ama-handoff]');
            const list = this.$refs.messages;
            if (card && list) {
                list.scrollTop += card.getBoundingClientRect().top - list.getBoundingClientRect().top - 12;
            }
            if (window.matchMedia('(pointer: fine)').matches) {
                this.$el.querySelector('#ama-handoff-name')?.focus({ preventScroll: true });
            }
        });
    },

    correctHandoff() {
        if (this.handoff) {
            this.handoff.editing = true;
            this.handoff.errors = {};
        }
    },

    cancelHandoff() {
        this.handoff = null;
    },

    // Envoi : seulement au toucher de « Envoyer à Coach Joseph ». Format faux : Ama le redemande poliment.
    async sendHandoff() {
        const handoff = this.handoff;

        if (!handoff || handoff.sending) {
            return;
        }

        const errors = handoffErrors(handoff);
        if (Object.keys(errors).length > 0) {
            handoff.editing = true;
            handoff.errors = errors;

            return;
        }

        handoff.sending = true;
        handoff.errors = {};

        let data = null;
        try {
            const response = await within(
                fetch(config.handoffEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        first_name: handoff.first_name,
                        channel: handoff.channel,
                        contact: handoff.contact,
                        question: handoff.question,
                        source: handoff.source,
                        conversation: this.conversation,
                        context: pick(this.context),
                        website: handoff.website,
                    }),
                }),
                30000,
            );
            data = response.ok ? await response.json() : null;
        } catch {
            data = null;
        }

        handoff.sending = false;

        if (data?.ok) {
            this.handoff = null;
            this.messages.push({ from: 'ama', text: data.reply, fallback: false });
            this.speak(data.reply);
        } else if (data?.limit) {
            this.handoff = null;
            this.messages.push({ from: 'ama', text: data.reply, fallback: true });
        } else if (data?.errors) {
            handoff.editing = true;
            handoff.errors = data.errors;
            if (data.reply) {
                this.messages.push({ from: 'ama', text: data.reply, fallback: false });
            }
        } else {
            handoff.errors = { form: 'L\'envoi n\'a pas abouti. Vérifiez votre connexion et réessayez, ou écrivez-nous sur WhatsApp.' };
        }

        this.persist();
        this.scrollDown();
    },

    // Dernières phrases de l'appel, affichées discrètement sous le cercle.
    get callTranscript() {
        return this.messages.slice(-2);
    },

    // Message dit pendant l'appel : envoyé au serveur, réponse reçue en continu (une ligne JSON par morceau).
    async askAloud(message, { onText, signal, interrupted = false } = {}) {
        let result = null;
        const timeout = new AbortController();
        const timer = setTimeout(() => timeout.abort(), 45000);
        signal?.addEventListener('abort', () => timeout.abort());

        try {
            const response = await fetch(config.callEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                signal: timeout.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ message, conversation: this.conversation, context: pick(this.context), voice: true, interrupted }),
            });

            if (!response.ok) {
                return null;
            }

            const reader = createLineReader((event) => {
                if (event?.type === 'text' && typeof event.text === 'string') {
                    onText?.(event.text);
                } else if (event?.type === 'done') {
                    result = event;
                    // Ama propose de transmettre la question : la fiche s'affichera à la fin de l'appel.
                    if (event.handoff) {
                        this.callHandoff = event.handoff;
                    }
                }
            });

            if (response.body?.getReader) {
                const stream = response.body.getReader();
                const decoder = new TextDecoder();
                for (;;) {
                    const { value, done } = await stream.read();
                    if (done) {
                        break;
                    }
                    reader.push(decoder.decode(value, { stream: true }));
                }
                reader.push(decoder.decode());
            } else {
                reader.push(await response.text());
            }
            reader.end();
        } catch {
            result = null;
        } finally {
            clearTimeout(timer);
        }

        if (result?.conversation) {
            this.conversation = result.conversation;
        }

        return result;
    },
}));

const startAlpine = () => {
    window.Alpine = Alpine;
    Alpine.start();
    initReveal();
    initAnimatedBackgrounds();
};

// Page d'un groupe de discussion : le code du chat est chargé seulement ici.
if (document.querySelector('[data-chat]')) {
    import('./chat.js')
        .then(({ default: registerChat }) => registerChat(Alpine))
        .finally(startAlpine);
} else {
    startAlpine();
}
