/**
 * Voix naturelle d'Ama (modèles « TTS » de Gemini, fabriqués par le serveur), avec la voix du téléphone en secours.
 *
 * createNaturalSynth() imite la synthèse vocale du navigateur (speak, cancel, getVoices et des phrases à lire avec
 * onstart, onend, onerror, onboundary) : le mode appel (ama-call.js) et « Lire les réponses à voix haute » l'utilisent
 * comme window.speechSynthesis, sans rien changer à l'écoute du micro ni à l'interruption.
 *
 * - Chaque texte est demandé au serveur du site (jamais à Google : la clé reste sur le serveur). Le prénom du visiteur
 *   est retiré du texte envoyé ; la voix du téléphone, elle, lit le texte complet.
 * - Une réponse courte (jusqu'à « splitLength » caractères, réglable dans l'admin) part en UNE seule demande : la limite
 *   de Google est de quelques demandes par minute. Plus longue : la première phrase seule (la voix commence vite), puis
 *   la suite d'un bloc dès que la première commence. En mode appel, la fin de la réponse est attendue au plus 1,5 s.
 * - Secours automatique : refus du serveur (quota, limite, erreur), audio pas prêt après « timeoutMs » (réglé dans
 *   l'admin, 10 s par défaut), serveur injoignable ou lecture impossible → la phrase est lue aussitôt par la voix du
 *   téléphone, et le reste de la réponse aussi (une seule voix par réponse). Jamais de silence ni de message d'erreur.
 *   Un audio arrivé après le délai n'est jamais joué (ni par-dessus la voix du téléphone, ni après elle).
 * - Chaque passage décidé par le navigateur est signalé avec sa cause exacte (onFallback : 'timeout', 'playback',
 *   'network') et l'attente en millisecondes ; les refus du serveur sont déjà comptés par le serveur.
 *
 * Aucune dépendance au navigateur : lecture audio, envoi au serveur et minuteries sont fournis (tests avec Node.js).
 */

import { createSentenceSplitter } from './ama.js';

// Délai par défaut avant la voix du téléphone (même valeur que App\Assistant\NaturalVoice::TIMEOUT, réglable dans l'admin).
export const NATURAL_TIMEOUT_MS = 10000;

// Réponse courte (jusqu'à ce nombre de caractères) : une seule demande. Plus longue : la première phrase, puis la suite.
// Réglable dans l'admin (App\Assistant\NaturalVoice::SPLIT_LENGTH).
export const SPLIT_LENGTH = 300;

// Mode appel : attente maximale de la fin de la réponse avant de décider « une ou deux demandes ».
export const STREAM_WAIT_MS = 1500;

// Écart (ms) au-delà duquel une nouvelle phrase commence une nouvelle réponse (la précédente est finie).
export const NEW_ANSWER_GAP_MS = 1000;

// Un morceau de silence très court (WAV), joué au toucher d'un bouton pour autoriser ensuite la lecture (iPhone).
export const SILENT_WAV = 'data:audio/wav;base64,UklGRiYAAABXQVZFZm10IBAAAAABAAEAQB8AAIA+AAACABAAZGF0YQIAAAAAAA==';

/**
 * Retire des mots (le prénom du visiteur) d'un texte, sans laisser d'espace avant la ponctuation.
 * « Bonsoir Kossi, je suis Ama » → « Bonsoir, je suis Ama ».
 */
export function hidePrivateWords(text, words = []) {
    let result = String(text ?? '');

    for (const word of words) {
        const clean = String(word ?? '').trim();

        if (clean.length < 2) {
            continue;
        }

        const escaped = clean.replace(/[.*+?^${}()|[\]\\]/gu, '\\$&');
        result = result.replace(new RegExp(`(?<![\\p{L}\\p{N}])${escaped}(?![\\p{L}\\p{N}])`, 'giu'), '');
    }

    return result
        .replace(/\s+([,.])/gu, '$1')
        .replace(/([,;:])(?=[,.!?;:])/gu, '')
        .replace(/\s+/gu, ' ')
        .trim();
}

/**
 * Phrases d'un texte long : la première, puis tout le reste d'un bloc (deux demandes au plus).
 */
export function splitForSpeech(text) {
    const splitter = createSentenceSplitter();
    const sentences = [...splitter.push(String(text ?? '')), ...splitter.flush()];

    if (sentences.length <= 1) {
        return sentences.length === 1 ? sentences : [];
    }

    return [sentences[0], sentences.slice(1).join(' ')];
}

/**
 * Phrase à lire, comme SpeechSynthesisUtterance.
 */
export class NaturalUtterance {
    constructor(text = '') {
        this.text = String(text ?? '');
        this.lang = 'fr-FR';
        this.rate = 1;
        this.pitch = 1;
        this.volume = 1;
        this.voice = null;
        this.onstart = null;
        this.onend = null;
        this.onerror = null;
        this.onboundary = null;
    }
}

/**
 * Lecteur audio du navigateur (un seul élément <audio>, réutilisé : sur iPhone, il reste autorisé après le premier
 * toucher). play(adresse, { onstart, onend, onerror }), stop(), unlock().
 */
export function createElementPlayer(element, { setTimer = (callback, delay) => setTimeout(callback, delay), clearTimer = (id) => clearTimeout(id) } = {}) {
    let handlers = null;
    let watchdog = null;
    const take = () => {
        const current = handlers;
        handlers = null;
        if (watchdog !== null) {
            clearTimer(watchdog);
            watchdog = null;
        }

        return current;
    };
    // Événement de la lecture en cours seulement (jamais celui, tardif, d'un son précédent ou du silence d'autorisation).
    const mine = () => handlers !== null && element.src === handlers.url;

    element.addEventListener('playing', () => {
        if (!mine()) {
            return;
        }
        handlers.onstart?.();
        // Sécurité : un son qui ne finit jamais (téléphone qui le bloque) passe à la voix du téléphone.
        const seconds = Number.isFinite(element.duration) && element.duration > 0 ? element.duration : 60;
        if (watchdog !== null) {
            clearTimer(watchdog);
        }
        watchdog = setTimer(() => {
            watchdog = null;
            if (mine()) {
                // Le son est arrêté avant que la voix du téléphone prenne le relais : jamais deux voix à la fois.
                const current = take();
                try {
                    element.pause();
                } catch {
                    // Déjà arrêté.
                }
                current?.onerror?.();
            }
        }, seconds * 1000 + 3000);
    });
    element.addEventListener('ended', () => mine() && take()?.onend?.());
    element.addEventListener('error', () => mine() && take()?.onerror?.());
    // Pause imposée par le téléphone (micro ouvert, appel entrant…) : la phrase passe à la voix du téléphone.
    element.addEventListener('pause', () => mine() && !element.ended && take()?.onerror?.());

    return {
        play(url, next) {
            take();
            handlers = { ...next, url };
            const current = handlers;
            try {
                element.src = url;
                current.url = element.src;
                const started = element.play();
                started?.catch?.(() => {
                    if (handlers === current) {
                        take()?.onerror?.();
                    }
                });
            } catch {
                if (handlers === current) {
                    take()?.onerror?.();
                }
            }
        },
        stop() {
            take();
            try {
                element.pause();
            } catch {
                // Rien à arrêter.
            }
        },
        // Au toucher d'un bouton : un silence très court autorise les lectures suivantes (iPhone, Safari).
        unlock() {
            if (handlers) {
                return;
            }
            try {
                element.src = SILENT_WAV;
                element.play()?.catch?.(() => {});
            } catch {
                // Lecture refusée : la voix du téléphone prendra le relais.
            }
        },
    };
}

/**
 * @param {object} options
 * @param {object} options.fallback  synthèse vocale du téléphone (window.speechSynthesis)
 * @param {Function} options.FallbackUtterance  constructeur d'une phrase de la voix du téléphone
 * @param {Function} options.fetchAudio  (texte, { signal }) → Promise<{ url, release } | null>
 * @param {object} options.player  lecteur audio : play(url, handlers), stop()
 * @param {object} [options.timers]  { setTimeout, clearTimeout }
 * @param {Function} [options.now]  horloge en millisecondes
 * @param {number} [options.timeoutMs]  délai avant la voix du téléphone
 * @param {number} [options.splitLength]  au-delà de ce nombre de caractères, une réponse est demandée en deux fois
 * @param {string[]} [options.privateWords]  mots jamais envoyés au serveur (prénom du visiteur)
 * @param {Function} [options.onFallback]  le navigateur reprend de lui-même la voix du téléphone : (cause, attente en ms),
 *   cause 'timeout' (audio pas prêt avant le délai), 'playback' (lecture refusée ou coupée) ou 'network' (serveur
 *   injoignable : fetchAudio a échoué au lieu de répondre null)
 */
export function createNaturalSynth({
    fallback,
    FallbackUtterance,
    fetchAudio,
    player,
    timers = { setTimeout: (callback, delay) => setTimeout(callback, delay), clearTimeout: (id) => clearTimeout(id) },
    now = () => Date.now(),
    timeoutMs = NATURAL_TIMEOUT_MS,
    splitLength = SPLIT_LENGTH,
    privateWords = [],
    onFallback = () => {},
}) {
    // Numéro de lecture : tout rappel d'une lecture arrêtée (cancel) est ignoré.
    let generation = 0;
    // Phrases annoncées par le mode appel, pas encore demandées au serveur.
    let pending = [];
    // Phrases déjà comprises dans une demande : { text, request, head } (head : la demande se joue à cette phrase).
    let covered = [];
    let requests = [];
    let busy = false;
    let lastEndAt = null;
    // Une seule voix par réponse : après un passage à la voix du téléphone, la suite de la réponse la garde.
    let answerFallback = false;
    // Mode appel : la réponse arrive encore (begin… finish), et la phrase en attente de la décision « une ou deux demandes ».
    let streaming = false;
    let waiter = null;

    const request = (text) => {
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const entry = { state: 'loading', result: null, controller, waiters: [] };
        const settle = (state, result) => {
            if (entry.state !== 'loading') {
                result?.release?.();

                return;
            }
            entry.state = state;
            entry.result = result;
            const waiters = entry.waiters;
            entry.waiters = [];
            waiters.forEach((waiter) => waiter());
        };
        const spoken = hidePrivateWords(text, privateWords);

        if (spoken === '') {
            settle('failed', null);
        } else {
            let sent;
            try {
                // Envoi immédiat : chaque milliseconde compte pour que la voix commence vite.
                sent = Promise.resolve(fetchAudio(spoken, { signal: controller?.signal }));
            } catch {
                sent = Promise.resolve(null);
            }
            sent
                .then((result) => settle(result?.url ? 'ready' : 'failed', result?.url ? result : null))
                .catch(() => {
                    // Serveur injoignable ou réponse inattendue (pas un refus du serveur, qui répond null).
                    if (entry.state === 'loading') {
                        entry.network = true;
                    }
                    settle('failed', null);
                });
        }
        requests.push(entry);

        return entry;
    };

    // Abandon d'une demande trop lente : la phrase passe à la voix du téléphone.
    const abandon = (entry) => {
        if (entry.state === 'loading') {
            entry.state = 'failed';
            try {
                entry.controller?.abort();
            } catch {
                // Déjà terminée.
            }
            const waiters = entry.waiters;
            entry.waiters = [];
            waiters.forEach((waiter) => waiter());
        }
    };

    const releaseAll = () => {
        requests.forEach((entry) => {
            try {
                entry.controller?.abort();
            } catch {
                // Déjà terminée.
            }
            entry.result?.release?.();
            entry.state = 'failed';
            entry.waiters = [];
        });
        requests = [];
    };

    // Demande d'un bloc toutes les phrases annoncées (la première porte l'audio, les autres sont « comprises »).
    const requestPending = () => {
        if (pending.length === 0 || answerFallback) {
            return;
        }
        const entry = request(pending.join(' '));
        covered.push(...pending.map((text, index) => ({ text, request: entry, head: index === 0 })));
        pending = [];
    };

    const takeCovered = (text) => {
        const index = covered.findIndex((item) => item.text === text);

        if (index === -1) {
            return null;
        }

        const [item] = covered.splice(index, 1);
        // Les phrases d'avant n'ont pas été lues par cette voie (cas rare) : oubliées.
        covered.splice(0, index);

        return item;
    };

    const takePending = (text) => {
        const index = pending.indexOf(text);

        if (index !== -1) {
            pending.splice(0, index + 1);
        }

        return index !== -1;
    };

    function speak(utterance) {
        const myGeneration = generation;
        const speakAt = now();
        const text = String(utterance?.text ?? '');
        const fresh = !busy && covered.length === 0 && (lastEndAt === null || now() - lastEndAt > NEW_ANSWER_GAP_MS);

        if (fresh) {
            answerFallback = false;
        }

        busy = true;
        let started = false;
        let finished = false;

        const start = () => {
            if (!started && myGeneration === generation) {
                started = true;
                utterance.onstart?.({});
            }
        };
        const end = () => {
            if (finished || myGeneration !== generation) {
                return;
            }
            finished = true;
            busy = false;
            lastEndAt = now();
            utterance.onend?.({});
        };

        // Voix du téléphone pour un morceau, puis then().
        const phone = (part, then) => {
            answerFallback = true;
            let native;
            try {
                native = new FallbackUtterance(part);
            } catch {
                then();

                return;
            }
            native.lang = utterance.lang ?? 'fr-FR';
            native.rate = utterance.rate ?? 1;
            native.pitch = utterance.pitch ?? 1;
            if (utterance.voice) {
                native.voice = utterance.voice;
            }
            native.onstart = start;
            native.onboundary = (event) => myGeneration === generation && utterance.onboundary?.(event);
            native.onend = () => myGeneration === generation && then();
            native.onerror = (event) => {
                if (myGeneration !== generation) {
                    return;
                }
                // Voix coupée par le micro (interruption à la voix) : le mode appel décide lui-même de la suite.
                if (['interrupted', 'canceled'].includes(event?.error) && utterance.onerror) {
                    finished = true;
                    busy = false;
                    utterance.onerror(event);

                    return;
                }
                then();
            };
            try {
                fallback.speak(native);
            } catch {
                then();
            }
        };

        // Joue l'audio d'une demande (en l'attendant au plus timeoutMs), sinon la voix du téléphone pour « part ».
        const play = (entry, part, then, budget = timeoutMs) => {
            const go = () => {
                if (myGeneration !== generation) {
                    return;
                }
                if (entry.state !== 'ready' || answerFallback) {
                    // Pas d'audio (ou la voix du téléphone a déjà pris le relais dans cette réponse) : jamais les deux.
                    if (entry.network && !entry.reported) {
                        entry.reported = true;
                        onFallback('network', Math.round(now() - speakAt));
                    }
                    phone(part, then);

                    return;
                }
                const result = entry.result;
                player.play(result.url, {
                    onstart: () => {
                        start();
                        // La première phrase commence : la suite de la réponse est demandée d'un bloc.
                        requestPending();
                    },
                    onend: () => {
                        result.release?.();
                        if (myGeneration === generation) {
                            then();
                        }
                    },
                    onerror: () => {
                        result.release?.();
                        if (myGeneration !== generation) {
                            return;
                        }
                        onFallback('playback', Math.round(now() - speakAt));
                        phone(part, then);
                    },
                });
            };

            if (entry.state !== 'loading') {
                go();

                return;
            }

            const timer = timers.setTimeout(() => {
                if (entry.state === 'loading' && myGeneration === generation) {
                    onFallback('timeout', Math.round(now() - speakAt));
                    abandon(entry);
                }
            }, budget);
            entry.waiters.push(() => {
                timers.clearTimeout(timer);
                go();
            });
        };

        if (text.trim() === '') {
            timers.setTimeout(end, 0);

            return;
        }

        // Phrase déjà comprise dans une demande d'un bloc.
        const item = takeCovered(text);

        if (item) {
            if (item.request.state === 'failed' || answerFallback) {
                phone(text, end);
            } else if (item.head) {
                play(item.request, text, end);
            } else {
                // Déjà dite avec la phrase qui portait l'audio.
                timers.setTimeout(end, 0);
            }

            return;
        }

        const announced = takePending(text);

        if (answerFallback) {
            phone(text, end);

            return;
        }

        // Une phrase arrivée après les autres (réponse déjà commencée) : demandée avec celles qui restent.
        if (!fresh) {
            pending.unshift(text);
            requestPending();
            const head = takeCovered(text);
            play(head.request, text, end);

            return;
        }

        // Début d'une réponse. Courte (jusqu'à « splitLength » caractères) : UNE seule demande pour toute la réponse.
        // Longue : la première phrase seule (la voix commence vite), puis tout le reste d'un bloc.
        const decide = (waited) => {
            if (myGeneration !== generation) {
                return;
            }
            const budget = Math.max(1000, timeoutMs - waited);

            if (!announced) {
                // Lecture à voix haute d'une réponse écrite : le texte entier est déjà connu.
                const parts = text.length > splitLength ? splitForSpeech(text) : [text];

                if (parts.length === 2) {
                    const first = request(parts[0]);
                    const rest = request(parts[1]);
                    play(first, parts[0], () => (answerFallback ? phone(parts[1], end) : play(rest, parts[1], end)), budget);
                } else {
                    play(request(text), text, end, budget);
                }

                return;
            }

            if ([text, ...pending].join(' ').length <= splitLength) {
                pending.unshift(text);
                requestPending();
                play(takeCovered(text).request, text, end, budget);

                return;
            }

            play(request(text), text, end, budget);
        };

        // Mode appel : la réponse arrive encore. On attend sa fin (au plus STREAM_WAIT_MS) pour savoir si elle est courte.
        if (announced && streaming && [text, ...pending].join(' ').length <= splitLength) {
            const startedAt = now();
            const timer = timers.setTimeout(() => {
                waiter = null;
                decide(now() - startedAt);
            }, STREAM_WAIT_MS);
            waiter = () => {
                if (myGeneration !== generation) {
                    waiter = null;
                    timers.clearTimeout(timer);

                    return;
                }
                if (!streaming || [text, ...pending].join(' ').length > splitLength) {
                    waiter = null;
                    timers.clearTimeout(timer);
                    decide(now() - startedAt);
                }
            };

            return;
        }

        decide(0);
    }

    return {
        Utterance: NaturalUtterance,
        speak,
        // Phrases que le mode appel va lire (pour les demander d'un bloc).
        prepare(texts) {
            pending.push(...(texts ?? []).map((text) => String(text ?? '')).filter((text) => text.trim() !== ''));
            waiter?.();
        },
        // Mode appel : une réponse commence à arriver, puis elle est complète.
        begin() {
            streaming = true;
        },
        finish() {
            streaming = false;
            waiter?.();
        },
        cancel() {
            generation += 1;
            waiter = null;
            streaming = false;
            pending = [];
            covered = [];
            busy = false;
            releaseAll();
            player.stop();
            try {
                fallback.cancel();
            } catch {
                // Rien à arrêter.
            }
        },
        getVoices() {
            try {
                return fallback.getVoices?.() ?? [];
            } catch {
                return [];
            }
        },
        get speaking() {
            return busy;
        },
    };
}
