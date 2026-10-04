/**
 * Groupes de discussion : conversation, envoi (texte, photos, vidéos, fichiers, notes vocales),
 * temps réel avec Pusher et mise à jour régulière automatique si Pusher n'est pas disponible.
 *
 * Chargé uniquement sur la page d'un groupe (voir site.js), pour garder le reste du site léger.
 */

import { createSwipeGesture } from './chat-swipe.js';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const jsonHeaders = () => ({
    'X-CSRF-TOKEN': csrfToken(),
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
});

const formatSeconds = (value) => {
    const seconds = Math.max(0, Math.floor(Number(value) || 0));

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
};

const formatBytes = (bytes) =>
    bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} Mo`
        : `${Math.max(1, Math.round(bytes / 1024))} Ko`;

/**
 * Premier message d'erreur d'une réponse Laravel (validation ou autre).
 */
const firstError = (json) => {
    if (json?.errors) {
        const first = Object.values(json.errors)[0];

        return Array.isArray(first) ? first[0] : first;
    }

    return json?.message && json.message !== 'Server Error' ? json.message : null;
};

const errorForStatus = (status, json) => {
    if (status === 419) {
        return 'Votre session a expiré : rechargez la page.';
    }

    if (status === 413) {
        return 'Fichier trop lourd pour être envoyé.';
    }

    if (status === 429) {
        return 'Vous envoyez beaucoup de messages : patientez quelques secondes.';
    }

    return firstError(json) || 'Une erreur est survenue. Vérifiez votre connexion et réessayez.';
};

/**
 * Formats de note vocale, du plus compatible au moins compatible :
 * MP4/AAC se lit partout (iPhone et Android), WebM/Opus en secours sur les navigateurs plus anciens.
 */
const VOICE_TYPES = ['audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/aac', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'];

export default function registerChat(Alpine) {
    /**
     * Lecteur de note vocale avec barre de progression (une seule note joue à la fois).
     */
    Alpine.data('voicePlayer', (duration) => ({
        playing: false,
        current: 0,
        duration: Number(duration) || 0,
        progress: 0,

        init() {
            const audio = this.$refs.audio;

            audio.addEventListener('timeupdate', () => this.sync(audio.currentTime));
            audio.addEventListener('play', () => (this.playing = true));
            audio.addEventListener('pause', () => (this.playing = false));
            audio.addEventListener('ended', () => {
                this.playing = false;
                this.sync(0);
            });
            audio.addEventListener('loadedmetadata', () => {
                if (Number.isFinite(audio.duration) && audio.duration > 0) {
                    this.duration = audio.duration;
                    this.$refs.range.max = audio.duration;
                }
            });

            window.addEventListener('chat-voice-play', (event) => {
                if (event.detail !== audio) {
                    audio.pause();
                }
            });
        },

        sync(time) {
            this.current = time;
            this.progress = this.duration ? Math.min(100, (time / this.duration) * 100) : 0;
            this.$refs.range.value = time;
        },

        toggle() {
            const audio = this.$refs.audio;

            if (!audio.paused) {
                audio.pause();

                return;
            }

            window.dispatchEvent(new CustomEvent('chat-voice-play', { detail: audio }));
            audio.play().catch(() => {
                window.dispatchEvent(new CustomEvent('chat-toast', { detail: 'Lecture impossible sur cet appareil. Essayez avec un autre navigateur.' }));
            });
        },

        seek(value) {
            const time = Number(value);

            try {
                this.$refs.audio.currentTime = time;
            } catch (error) {
                // Note pas encore chargée : la position sera appliquée à la lecture.
            }

            this.sync(time);
        },

        format: formatSeconds,
    }));

    /**
     * Conversation d'un groupe.
     */
    Alpine.data('chatRoom', (config) => ({
        lastId: config.lastId,
        firstId: config.firstId,
        hasOlder: config.hasOlder,
        since: config.since,
        canPost: config.canPost,
        blockedReason: config.blockedReason || '',
        announcementsOnly: config.announcementsOnly,
        showRules: config.showRules,

        text: '',
        replyTo: null,
        pending: null,
        sending: false,
        uploading: false,
        uploadProgress: 0,

        recording: false,
        recordSeconds: 0,

        attachMenu: false,
        groupMenu: false,
        menu: null,
        dialog: null,
        report: { reason: '', details: '' },
        busy: false,
        lightbox: null,
        toast: '',
        removed: false,

        atBottom: true,
        newCount: 0,
        loadingOlder: false,
        polling: false,
        pollAgain: false,
        realtimeConnected: false,

        init() {
            // Éléments gardés une fois pour toutes : les menus (x-if) n'ont pas accès aux $refs du composant.
            this.els = {
                scroll: this.$refs.scroll,
                list: this.$refs.list,
                top: this.$refs.top,
                input: this.$refs.input,
                empty: this.$refs.empty,
            };

            this.$nextTick(() => {
                const unread = document.getElementById('premier-non-lu');

                if (unread) {
                    unread.scrollIntoView({ block: 'start' });
                } else {
                    this.scrollToBottom(false);
                }

                this.onScroll();
                setTimeout(() => this.watchTop(), 400);
            });

            this.els.list.addEventListener('click', (event) => this.onListClick(event));
            this.listenLongPress();
            this.listenSwipe();

            window.addEventListener('chat-toast', (event) => this.flash(event.detail));
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.poll();
                }
            });

            this.connectRealtime();
            this.schedulePoll();
        },

        /* ---------- Défilement ---------- */

        onScroll() {
            const el = this.els.scroll;
            this.atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;

            if (this.atBottom) {
                this.newCount = 0;
            }
        },

        scrollToBottom(smooth = true) {
            const el = this.els.scroll;
            el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
            this.newCount = 0;
        },

        watchTop() {
            if (!('IntersectionObserver' in window)) {
                return;
            }

            new IntersectionObserver(
                (entries) => {
                    if (entries.some((entry) => entry.isIntersecting)) {
                        this.loadOlder();
                    }
                },
                { root: this.els.scroll, rootMargin: '300px 0px 0px 0px' },
            ).observe(this.els.top);
        },

        /**
         * Historique : charge la page précédente en gardant la position de lecture.
         */
        async loadOlder() {
            if (!this.hasOlder || this.loadingOlder || !this.firstId) {
                return false;
            }

            this.loadingOlder = true;

            try {
                const url = new URL(config.urls.messages, window.location.origin);
                url.searchParams.set('before', this.firstId);
                const response = await fetch(url, { headers: jsonHeaders(), credentials: 'same-origin', cache: 'no-store' });

                if (!response.ok) {
                    return false;
                }

                const data = await response.json();
                const el = this.els.scroll;
                const previousHeight = el.scrollHeight;

                this.els.list.insertAdjacentHTML('afterbegin', data.html);
                this.dedupeDays();
                el.scrollTop += el.scrollHeight - previousHeight;

                this.firstId = data.first_id || this.firstId;
                this.hasOlder = Boolean(data.has_more);

                return true;
            } catch (error) {
                return false;
            } finally {
                this.loadingOlder = false;
            }
        },

        dedupeDays() {
            const seen = new Set();

            this.els.list.querySelectorAll('.chat-day').forEach((separator) => {
                if (seen.has(separator.dataset.day)) {
                    separator.remove();
                } else {
                    seen.add(separator.dataset.day);
                }
            });
        },

        async jumpTo(id) {
            let target = document.getElementById(`m-${id}`);

            for (let attempt = 0; !target && this.hasOlder && attempt < 10; attempt++) {
                if (!(await this.loadOlder())) {
                    break;
                }

                target = document.getElementById(`m-${id}`);
            }

            if (!target) {
                this.flash('Ce message n\'est plus disponible.');

                return;
            }

            target.scrollIntoView({ block: 'center', behavior: 'smooth' });
            target.classList.remove('chat-flash');
            void target.offsetWidth;
            target.classList.add('chat-flash');
        },

        /* ---------- Mises à jour : temps réel (Pusher) ou régulières ---------- */

        async connectRealtime() {
            if (!config.realtime) {
                return;
            }

            try {
                const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);
                window.Pusher = Pusher;

                this.echo = new Echo({
                    broadcaster: 'pusher',
                    key: config.realtime.key,
                    cluster: config.realtime.cluster,
                    forceTLS: true,
                    authEndpoint: config.urls.broadcastAuth,
                    auth: { headers: jsonHeaders() },
                });

                this.echo.connector.pusher.connection.bind('state_change', ({ current }) => {
                    const connected = current === 'connected';

                    if (connected !== this.realtimeConnected) {
                        this.realtimeConnected = connected;
                        this.schedulePoll();

                        if (connected) {
                            this.poll();
                        }
                    }
                });

                this.echo
                    .private(`chat.group.${config.groupId}`)
                    .listen('.chat.changed', () => this.poll())
                    .error(() => {
                        this.realtimeConnected = false;
                        this.schedulePoll();
                    });
            } catch (error) {
                // Pusher indisponible : la mise à jour régulière prend le relais, sans message d'erreur.
                this.realtimeConnected = false;
                this.schedulePoll();
            }
        },

        schedulePoll() {
            clearTimeout(this.pollTimer);

            const delay = this.realtimeConnected ? config.safetyPollInterval : config.pollInterval;

            this.pollTimer = setTimeout(async () => {
                if (document.visibilityState === 'visible' && !this.removed) {
                    await this.poll();
                }

                if (!this.removed) {
                    this.schedulePoll();
                }
            }, delay);
        },

        async poll({ scroll = false } = {}) {
            if (this.polling) {
                this.pollAgain = true;

                return;
            }

            this.polling = true;

            try {
                const url = new URL(config.urls.messages, window.location.origin);
                url.searchParams.set('after', this.lastId);
                url.searchParams.set('since', this.since);

                if (document.visibilityState === 'visible') {
                    url.searchParams.set('visible', '1');
                }

                const response = await fetch(url, { headers: jsonHeaders(), credentials: 'same-origin', cache: 'no-store' });

                if (response.status === 401 || response.status === 419) {
                    window.location.reload();

                    return;
                }

                if (response.status === 403) {
                    this.removed = true;
                    clearTimeout(this.pollTimer);

                    return;
                }

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                const wasAtBottom = this.atBottom;
                this.since = data.server_time;

                if (data.html) {
                    this.els.empty?.remove();
                    this.els.list.insertAdjacentHTML('beforeend', data.html);
                    this.dedupeDays();
                    this.lastId = data.last_id;

                    if (!wasAtBottom && !scroll) {
                        this.newCount += data.count;
                    }
                }

                (data.updates || []).forEach((update) => {
                    const element = document.getElementById(`m-${update.id}`);

                    if (element) {
                        element.outerHTML = update.html;
                    }
                });

                const pinned = document.getElementById('chat-pinned');

                if (pinned && typeof data.pinned_html === 'string' && pinned.outerHTML !== data.pinned_html) {
                    pinned.outerHTML = data.pinned_html;
                }

                this.canPost = data.can_post;
                this.blockedReason = data.blocked_reason || '';
                this.announcementsOnly = data.announcements_only;

                if (data.html && (wasAtBottom || scroll)) {
                    this.$nextTick(() => {
                        this.scrollToBottom(false);
                        // Deuxième passage : les images et vidéos peuvent agrandir la conversation.
                        setTimeout(() => this.scrollToBottom(false), 400);
                    });
                }
            } catch (error) {
                // Réseau momentanément coupé : nouvel essai à la prochaine mise à jour.
            } finally {
                this.polling = false;

                if (this.pollAgain) {
                    this.pollAgain = false;
                    this.poll({ scroll });
                }
            }
        },

        /* ---------- Menu d'un message ---------- */

        onListClick(event) {
            // Pastille de réaction sous un message : réagir avec cet emoji (ou retirer sa réaction).
            const reaction = event.target.closest('[data-react]');

            if (reaction) {
                this.react(reaction.dataset.react, reaction.closest('[data-message-id]')?.dataset.messageId);

                return;
            }

            const menuButton = event.target.closest('[data-menu]');

            if (menuButton) {
                this.openMenu(menuButton.closest('[data-message-id]'));

                return;
            }

            const image = event.target.closest('[data-lightbox]');

            if (image) {
                event.preventDefault();
                this.lightbox = image.getAttribute('href');

                return;
            }

            const quote = event.target.closest('[data-jump]');

            if (quote) {
                this.jumpTo(quote.dataset.jump);
            }
        },

        /**
         * Appui long sur une bulle (téléphone) : ouvre le menu du message.
         */
        listenLongPress() {
            let timer = null;

            const cancel = () => clearTimeout(timer);

            this.els.list.addEventListener(
                'touchstart',
                (event) => {
                    const bubble = event.target.closest('.chat-bubble');

                    if (!bubble || event.target.closest('a, button, input, video, audio')) {
                        return;
                    }

                    timer = setTimeout(() => {
                        navigator.vibrate?.(10);
                        this.openMenu(bubble.closest('[data-message-id]'));
                    }, 450);
                },
                { passive: true },
            );

            ['touchend', 'touchmove', 'touchcancel'].forEach((name) => this.els.list.addEventListener(name, cancel, { passive: true }));

            this.els.list.addEventListener('contextmenu', (event) => {
                const row = event.target.closest('[data-message-id]');

                if (row && !event.target.closest('a, video, img') && row.dataset.deleted !== '1') {
                    event.preventDefault();
                    this.openMenu(row);
                }
            });
        },

        openMenu(row) {
            if (!row || row.dataset.deleted === '1') {
                return;
            }

            const data = row.dataset;

            this.menu = {
                id: data.messageId,
                authorId: data.authorId,
                author: data.author,
                preview: data.preview,
                text: data.text,
                canDelete: data.canDelete === '1',
                canPin: data.canPin === '1',
                pinned: data.pinned === '1',
                canReport: data.canReport === '1',
                canModerate: data.canModerate === '1',
                canReact: data.canReact === '1',
                myReaction: data.myReaction || '',
            };
        },

        closeAll() {
            if (this.dialog === 'rules') {
                this.acceptRules();
            }

            this.menu = null;
            this.groupMenu = false;
            this.dialog = null;
            this.attachMenu = false;
        },

        reply() {
            this.startReply(document.getElementById(`m-${this.menu.id}`), this.menu);
        },

        /**
         * Prépare la réponse à un message (geste ou menu) : encadré au-dessus de la zone de saisie.
         */
        startReply(row, fallback = null) {
            const data = row?.dataset || {};
            const id = data.messageId || fallback?.id;

            if (!id || data.deleted === '1' || !this.canPost) {
                this.closeAll();

                return;
            }

            this.replyTo = {
                id,
                author: data.author || fallback?.author || '',
                preview: data.preview || fallback?.preview || '',
                thumb: data.thumb || '',
            };
            this.closeAll();
            this.$nextTick(() => this.els.input.focus());
        },

        cancelReply() {
            this.replyTo = null;
        },

        /**
         * Glisser un message vers la droite (téléphone) : prépare la réponse, comme sur WhatsApp.
         * La bulle suit le doigt, une flèche apparaît ; relâcher après le seuil prépare la réponse.
         */
        listenSwipe() {
            const gesture = createSwipeGesture();
            const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
            let row = null;
            let icon = null;

            const reset = () => {
                if (!row) {
                    return;
                }

                const current = row;
                const currentIcon = icon;
                current.style.transition = reduced ? 'none' : 'transform 180ms ease-out';
                current.style.transform = '';
                currentIcon?.remove();
                setTimeout(() => {
                    current.style.transition = '';
                    current.classList.remove('chat-swiping');
                }, reduced ? 0 : 200);
                row = null;
                icon = null;
            };

            this.els.list.addEventListener(
                'touchstart',
                (event) => {
                    reset();

                    const candidate = event.target.closest('[data-message-id]');

                    if (
                        event.touches.length !== 1 ||
                        !candidate ||
                        !this.canPost ||
                        candidate.dataset.deleted === '1' ||
                        event.target.closest('input, textarea, video, audio, .chat-voice, .chat-reactions')
                    ) {
                        return;
                    }

                    const touch = event.touches[0];

                    if (gesture.start(touch.clientX, touch.clientY, window.innerWidth).state === 'pending') {
                        row = candidate;
                    }
                },
                { passive: true },
            );

            this.els.list.addEventListener(
                'touchmove',
                (event) => {
                    if (!row) {
                        return;
                    }

                    const touch = event.touches[0];
                    const step = gesture.move(touch.clientX, touch.clientY);

                    if (step.state === 'scrolling') {
                        row = null;

                        return;
                    }

                    if (step.state !== 'swiping') {
                        return;
                    }

                    if (!icon) {
                        row.classList.add('chat-swiping');
                        row.style.transition = 'none';
                        icon = document.createElement('span');
                        icon.className = 'chat-swipe-icon';
                        icon.setAttribute('aria-hidden', 'true');
                        icon.innerHTML = '<svg viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M7.793 2.232a.75.75 0 0 1-.025 1.06L3.622 7.25h10.003a5.375 5.375 0 0 1 0 10.75H10.75a.75.75 0 0 1 0-1.5h2.875a3.875 3.875 0 0 0 0-7.75H3.622l4.146 3.957a.75.75 0 0 1-1.036 1.085l-5.5-5.25a.75.75 0 0 1 0-1.085l5.5-5.25a.75.75 0 0 1 1.06.025Z" clip-rule="evenodd"/></svg>';
                        row.prepend(icon);
                    }

                    row.style.transform = `translateX(${step.offset}px)`;
                    icon.style.opacity = String(Math.min(1, step.offset / 64));
                    icon.classList.toggle('chat-swipe-icon-armed', step.armed);

                    if (step.crossed) {
                        navigator.vibrate?.(10);
                    }
                },
                { passive: true },
            );

            const finish = () => {
                const target = row;
                const result = gesture.end();

                if (!target) {
                    return;
                }

                reset();

                if (result.swiped) {
                    // Le doigt a glissé : pas de clic (agrandir l'image, citation…) à la fin du geste.
                    const block = (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                    };
                    this.els.list.addEventListener('click', block, { capture: true, once: true });
                    setTimeout(() => this.els.list.removeEventListener('click', block, { capture: true }), 350);
                }

                if (result.reply) {
                    this.startReply(target);
                }
            };

            this.els.list.addEventListener('touchend', finish, { passive: true });
            this.els.list.addEventListener('touchcancel', () => {
                gesture.end();
                reset();
            }, { passive: true });
        },

        async copyText() {
            try {
                await navigator.clipboard.writeText(this.menu.text);
                this.flash('Texte copié.');
            } catch (error) {
                this.flash('Copie impossible sur cet appareil.');
            }

            this.closeAll();
        },

        url(name, id) {
            return config.urls[name].replace('__ID__', id);
        },

        async act(request, success) {
            this.busy = true;

            try {
                const response = await fetch(request.url, {
                    method: request.method || 'POST',
                    headers: { ...jsonHeaders(), ...(request.body ? { 'Content-Type': 'application/json' } : {}) },
                    body: request.body ? JSON.stringify(request.body) : null,
                    credentials: 'same-origin',
                });
                const json = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(errorForStatus(response.status, json));
                }

                this.flash(json.status || success);
                this.closeAll();
                await this.poll();
            } catch (error) {
                this.flash(error.message);
            } finally {
                this.busy = false;
            }
        },

        /**
         * Réaction par emoji : une seule par personne (un autre emoji remplace, le même retire).
         * Le message est remplacé tout de suite par sa nouvelle version (compteurs à jour).
         */
        async react(emoji, id = this.menu?.id) {
            if (!emoji || !id || this.busy) {
                return;
            }

            this.busy = true;

            try {
                const response = await fetch(this.url('react', id), {
                    method: 'POST',
                    headers: { ...jsonHeaders(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ emoji }),
                    credentials: 'same-origin',
                });
                const json = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(errorForStatus(response.status, json));
                }

                const element = document.getElementById(`m-${id}`);

                if (element && json.html) {
                    element.outerHTML = json.html;
                }

                navigator.vibrate?.(8);
                this.menu = null;
            } catch (error) {
                this.flash(error.message);
            } finally {
                this.busy = false;
            }
        },

        togglePin() {
            this.act({ url: this.url('pin', this.menu.id) }, 'C\'est fait.');
        },

        deleteMessage() {
            this.act({ url: this.url('destroy', this.menu.id), method: 'DELETE' }, 'Message supprimé.');
        },

        sendReport() {
            this.act({ url: this.url('report', this.menu.id), body: this.report }, 'Message signalé.');
        },

        mute(hours) {
            this.act({ url: this.url('mute', this.menu.authorId), body: { hours } }, 'Membre mis en sourdine.');
        },

        exclude() {
            this.act({ url: this.url('exclude', this.menu.authorId) }, 'Membre exclu du groupe.');
        },

        acceptRules() {
            if (!this.showRules) {
                this.dialog = this.dialog === 'rules' ? null : this.dialog;

                return;
            }

            this.showRules = false;
            this.dialog = null;
            fetch(config.urls.rulesSeen, { method: 'POST', headers: jsonHeaders(), credentials: 'same-origin' }).catch(() => {});
        },

        flash(message) {
            this.toast = message;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => (this.toast = ''), 3500);
        },

        /* ---------- Écriture ---------- */

        autosize() {
            const input = this.els.input;
            input.style.height = 'auto';
            input.style.height = `${Math.min(input.scrollHeight, 144)}px`;
        },

        onEnter(event) {
            // Ordinateur : Entrée envoie, Maj + Entrée va à la ligne. Téléphone : Entrée va à la ligne.
            if (!event.shiftKey && !window.matchMedia('(pointer: coarse)').matches) {
                event.preventDefault();
                this.sendText();
            }
        },

        formatSeconds,

        pick(event, type) {
            const file = event.target.files?.[0];
            event.target.value = '';
            this.attachMenu = false;

            if (!file) {
                return;
            }

            const extension = (file.name.split('.').pop() || '').toLowerCase();

            if (file.size > config.maxBytes[type]) {
                const limit = Math.round(config.maxBytes[type] / 1024 / 1024);
                this.flash(type === 'video' ? `Vidéo trop lourde : ${limit} Mo au maximum.` : `Fichier trop lourd : ${limit} Mo au maximum.`);

                return;
            }

            if (type === 'file' && !config.fileExtensions.includes(extension)) {
                this.flash('Fichier refusé : formats acceptés PDF, Word, Excel, PowerPoint, ZIP et TXT.');

                return;
            }

            if (type === 'image' && file.type && !file.type.startsWith('image/')) {
                this.flash('Choisissez une image (JPG, PNG, WebP ou GIF).');

                return;
            }

            this.clearPending();

            const labels = { image: 'Photo', video: 'Vidéo', file: extension.toUpperCase() || 'Fichier' };

            this.pending = {
                file,
                type,
                name: file.name,
                extension: extension.toUpperCase().slice(0, 4) || 'DOC',
                label: `${labels[type]} · ${formatBytes(file.size)}${type !== 'file' ? ' · ajoutez une légende si vous le souhaitez' : ''}`,
                preview: type === 'image' || type === 'video' ? URL.createObjectURL(file) : null,
            };

            this.$nextTick(() => this.els.input.focus());
        },

        clearPending() {
            if (this.pending?.preview) {
                URL.revokeObjectURL(this.pending.preview);
            }

            this.pending = null;
        },

        async sendText() {
            if (this.sending || this.uploading) {
                return;
            }

            if (this.pending) {
                await this.sendPending();

                return;
            }

            const body = this.text.trim();

            if (!body) {
                return;
            }

            const data = new FormData();
            data.append('type', 'text');
            data.append('body', body);

            if (this.replyTo) {
                data.append('reply_to_id', this.replyTo.id);
            }

            this.sending = true;
            this.text = '';
            this.$nextTick(() => this.autosize());

            try {
                await this.upload(data, false);
                this.replyTo = null;
                await this.poll({ scroll: true });
            } catch (error) {
                this.text = body;
                this.flash(error.message);
            } finally {
                this.sending = false;
            }
        },

        async sendPending() {
            const data = new FormData();
            data.append('type', this.pending.type);
            data.append('attachment', this.pending.file, this.pending.file.name);

            if (this.text.trim()) {
                data.append('body', this.text.trim());
            }

            if (this.replyTo) {
                data.append('reply_to_id', this.replyTo.id);
            }

            await this.uploadWithProgress(data, () => {
                this.clearPending();
                this.text = '';
                this.$nextTick(() => this.autosize());
            });
        },

        async uploadWithProgress(data, onSuccess) {
            this.uploading = true;
            this.uploadProgress = 0;

            try {
                await this.upload(data, true);
                onSuccess();
                this.replyTo = null;
                await this.poll({ scroll: true });
            } catch (error) {
                this.flash(error.message);
            } finally {
                this.uploading = false;
            }
        },

        /**
         * Envoi au serveur, avec suivi de la progression pour les fichiers.
         */
        upload(data, withProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', config.urls.store);
                Object.entries(jsonHeaders()).forEach(([name, value]) => xhr.setRequestHeader(name, value));

                if (withProgress) {
                    xhr.upload.addEventListener('progress', (event) => {
                        if (event.lengthComputable) {
                            this.uploadProgress = Math.min(99, Math.round((event.loaded / event.total) * 100));
                        }
                    });
                }

                xhr.addEventListener('load', () => {
                    let json = {};

                    try {
                        json = JSON.parse(xhr.responseText);
                    } catch (error) {
                        json = {};
                    }

                    if (xhr.status >= 200 && xhr.status < 300) {
                        this.uploadProgress = 100;
                        resolve(json);
                    } else if (xhr.status === 403) {
                        this.removed = true;
                        reject(new Error('Vous ne faites plus partie de ce groupe.'));
                    } else {
                        reject(new Error(errorForStatus(xhr.status, json)));
                    }
                });

                xhr.addEventListener('error', () => reject(new Error('Connexion interrompue : le message n\'a pas été envoyé. Réessayez.')));
                xhr.send(data);
            });
        },

        /* ---------- Notes vocales ---------- */

        async startRecording() {
            if (!navigator.mediaDevices?.getUserMedia || typeof window.MediaRecorder === 'undefined') {
                this.flash('Votre navigateur ne permet pas d\'enregistrer une note vocale. Mettez-le à jour (Chrome ou Safari).');

                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
            } catch (error) {
                this.flash('Autorisez l\'accès au micro dans les réglages de votre navigateur pour envoyer une note vocale.');

                return;
            }

            const mimeType = VOICE_TYPES.find((type) => window.MediaRecorder.isTypeSupported?.(type)) || '';

            this.chunks = [];
            this.recorder = new window.MediaRecorder(this.stream, mimeType ? { mimeType, audioBitsPerSecond: 64000 } : undefined);
            this.recorder.addEventListener('dataavailable', (event) => {
                if (event.data?.size) {
                    this.chunks.push(event.data);
                }
            });
            this.recorder.addEventListener('stop', () => this.onRecordingStopped());
            this.recorder.start(1000);

            this.recording = true;
            this.recordSeconds = 0;
            this.recordStartedAt = Date.now();
            this.recordTimer = setInterval(() => {
                this.recordSeconds = Math.floor((Date.now() - this.recordStartedAt) / 1000);

                // Limite atteinte : la note part automatiquement.
                if (this.recordSeconds >= config.voiceMaxSeconds) {
                    this.stopRecording(true);
                }
            }, 250);
        },

        stopRecording(send) {
            if (!this.recording) {
                return;
            }

            clearInterval(this.recordTimer);
            this.sendAfterStop = send;
            this.recordDuration = Math.min(config.voiceMaxSeconds, Math.max(1, Math.round((Date.now() - this.recordStartedAt) / 1000)));
            this.recording = false;

            if (this.recorder && this.recorder.state !== 'inactive') {
                this.recorder.stop();
            }

            this.stream?.getTracks().forEach((track) => track.stop());
        },

        cancelRecording() {
            this.stopRecording(false);
        },

        async onRecordingStopped() {
            if (!this.sendAfterStop) {
                this.chunks = [];

                return;
            }

            const type = (this.recorder?.mimeType || this.chunks[0]?.type || 'audio/webm').split(';')[0];
            const blob = new Blob(this.chunks, { type });
            this.chunks = [];

            if (blob.size < 800 || this.recordDuration < 1) {
                this.flash('Note vocale trop courte : maintenez l\'enregistrement au moins une seconde.');

                return;
            }

            if (blob.size > config.maxBytes.voice) {
                this.flash('Note vocale trop lourde.');

                return;
            }

            const extension = type.includes('mp4') || type.includes('aac') ? 'm4a' : type.includes('ogg') ? 'ogg' : 'webm';
            const data = new FormData();
            data.append('type', 'voice');
            data.append('attachment', blob, `note-vocale.${extension}`);
            data.append('duration', String(this.recordDuration));

            if (this.replyTo) {
                data.append('reply_to_id', this.replyTo.id);
            }

            this.pending = { type: 'voice', name: 'Note vocale', label: formatSeconds(this.recordDuration), extension: 'MIC', preview: null };
            await this.uploadWithProgress(data, () => (this.pending = null));

            if (this.pending?.type === 'voice') {
                this.pending = null;
            }
        },
    }));
}
