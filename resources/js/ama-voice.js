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

// Pause imposée par le téléphone : temps laissé au navigateur pour relancer le son de lui-même avant de l'abandonner.
export const PAUSE_GRACE_MS = 1500;

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
 * Lecteur audio du navigateur (un seul élément <audio>, réutilisé : il reste autorisé après le premier toucher, sur
 * iPhone comme sur Android). play(adresse, { onstart, onend, onerror }), stop(), unlock().
 *
 * onerror(cause) donne la cause exacte : 'playback_refused' (lecture refusée par le navigateur : play() rejeté),
 * 'playback_paused' (son mis en pause par le téléphone et pas repris de lui-même : micro, appel, notification, sortie
 * audio changée), 'playback_error' (fichier illisible) ou 'playback_stalled' (son commencé qui ne finit jamais).
 *
 * Règle absolue : un son abandonné (arrêt, échec, passage à la voix du téléphone) est arrêté ET vidé (plus aucune source
 * dans l'élément) : Chrome Android, qui relance de lui-même un son qu'il avait mis en pause quand il retrouve la main sur
 * le haut-parleur, n'a plus rien à relancer. Et tout son qui démarre sans être la lecture en cours est coupé aussitôt.
 */
export function createElementPlayer(element, {
    setTimer = (callback, delay) => setTimeout(callback, delay),
    clearTimer = (id) => clearTimeout(id),
    pauseGraceMs = PAUSE_GRACE_MS,
} = {}) {
    let handlers = null;
    let watchdog = null;
    let grace = null;
    let unlocking = false;
    const clearTimers = () => {
        if (watchdog !== null) {
            clearTimer(watchdog);
            watchdog = null;
        }
        if (grace !== null) {
            clearTimer(grace);
            grace = null;
        }
    };
    const take = () => {
        const current = handlers;
        handlers = null;
        clearTimers();

        return current;
    };
    // Arrêt définitif : pause, retour au début et plus aucune source (rien ne peut reprendre, même relancé par le téléphone).
    const reset = () => {
        unlocking = false;
        try {
            element.pause();
        } catch {
            // Déjà arrêté.
        }
        try {
            element.removeAttribute('src');
            element.load();
        } catch {
            // Rien à vider.
        }
    };
    // Abandon de la lecture en cours avec sa cause : le son est vidé AVANT que la suite (voix du téléphone) commence.
    const fail = (cause) => {
        const current = take();
        reset();
        current?.onerror?.(cause);
    };
    // Événement de la lecture en cours seulement (jamais celui, tardif, d'un son précédent ou du silence d'autorisation).
    const mine = () => handlers !== null && element.src === handlers.url;

    // Garde : un son qui démarre sans être la lecture en cours (relance par le téléphone d'un son abandonné) est coupé.
    const guard = () => {
        if (!mine() && !unlocking) {
            reset();
        }
    };
    element.addEventListener('play', guard);
    element.addEventListener('playing', () => {
        if (!mine()) {
            guard();

            return;
        }
        // Son repris de lui-même après une courte pause du téléphone : la lecture continue, sans autre voix.
        if (grace !== null) {
            clearTimer(grace);
            grace = null;
        }
        if (!handlers.started) {
            handlers.started = true;
            handlers.onstart?.();
        }
        // Sécurité : un son qui ne finit jamais (téléphone qui le bloque) est abandonné.
        const seconds = Number.isFinite(element.duration) && element.duration > 0 ? element.duration : 60;
        if (watchdog !== null) {
            clearTimer(watchdog);
        }
        watchdog = setTimer(() => {
            watchdog = null;
            if (mine()) {
                fail('playback_stalled');
            }
        }, seconds * 1000 + 3000);
    });
    element.addEventListener('ended', () => {
        if (mine()) {
            const current = take();
            reset();
            current?.onend?.();
        }
    });
    element.addEventListener('error', () => mine() && fail('playback_error'));
    // Pause imposée par le téléphone (micro, appel, notification, écouteurs débranchés…) : Chrome Android relance souvent
    // le son de lui-même quelques instants plus tard. On l'attend un court moment ; sinon le son est abandonné et vidé.
    element.addEventListener('pause', () => {
        if (!mine() || element.ended || grace !== null) {
            return;
        }
        if (watchdog !== null) {
            clearTimer(watchdog);
            watchdog = null;
        }
        grace = setTimer(() => {
            grace = null;
            if (mine() && element.paused) {
                fail('playback_paused');
            }
        }, pauseGraceMs);
    });

    return {
        play(url, next) {
            take();
            unlocking = false;
            handlers = { ...next, url, started: false };
            const current = handlers;
            try {
                element.src = url;
                current.url = element.src;
                const started = element.play();
                started?.catch?.(() => {
                    if (handlers === current) {
                        fail('playback_refused');
                    }
                });
            } catch {
                if (handlers === current) {
                    fail('playback_refused');
                }
            }
        },
        stop() {
            take();
            reset();
        },
        // Au premier toucher (bouton « Parler avec Ama », « Envoyer ») : un silence très court autorise ce même lecteur
        // pour les lectures suivantes (iPhone, Safari ; Chrome Android garde aussi l'autorisation sur cet élément).
        unlock() {
            if (handlers) {
                return;
            }
            try {
                unlocking = true;
                element.src = SILENT_WAV;
                const started = element.play();
                const done = () => {
                    if (!handlers) {
                        reset();
                    }
                };
                if (started?.then) {
                    started.then(done, done);
                } else {
                    done();
                }
            } catch {
                // Lecture refusée : la lecture suivante le dira (cause « lecture refusée »).
                unlocking = false;
            }
        },
    };
}

/**
 * @param {object} options
 * @param {object} options.fallback  synthèse vocale du téléphone (window.speechSynthesis)
 * @param {Function} options.FallbackUtterance  constructeur d'une phrase de la voix du téléphone
 * @param {Function} options.fetchAudio  (texte, { signal, voiced }) → Promise<{ url, release } | null> ; voiced : la voix
 *   de Gemini a déjà été entendue dans cette réponse (un refus du serveur sera alors affiché sans voix, jamais lu par la
 *   voix du téléphone)
 * @param {object} options.player  lecteur audio : play(url, handlers), stop()
 * @param {object} [options.timers]  { setTimeout, clearTimeout }
 * @param {Function} [options.now]  horloge en millisecondes
 * @param {number} [options.timeoutMs]  délai avant la voix du téléphone
 * @param {number} [options.splitLength]  au-delà de ce nombre de caractères, une réponse est demandée en deux fois
 * @param {string[]} [options.privateWords]  mots jamais envoyés au serveur (prénom du visiteur)
 * @param {boolean} [options.phoneFallback]  voix du téléphone en secours (réglage de l'admin, allumé par défaut).
 *   Éteint : seule la voix de Gemini parle ; si elle échoue, la réponse reste affichée à l'écran sans être dite.
 * @param {Function} [options.onFallback]  le navigateur abandonne de lui-même la voix de Gemini : (cause, attente en ms,
 *   issue), cause 'timeout' (audio pas prêt avant le délai), 'network' (serveur injoignable : fetchAudio a échoué au lieu
 *   de répondre null), 'playback_refused', 'playback_paused', 'playback_error' ou 'playback_stalled' (voir
 *   createElementPlayer) ; issue 'phone' (voix du téléphone) ou 'silent' (réponse affichée sans voix)
 * @param {Function} [options.onSilent]  une partie de la réponse n'est pas dite (elle reste affichée à l'écran) ; aussi
 *   la propriété « onsilent » du moteur, que la page peut changer
 *
 * Une seule voix par réponse, toujours :
 * - dès que la voix du téléphone parle pour une réponse, le son de Gemini est arrêté et vidé, et plus aucun son de
 *   Gemini n'est joué pour cette réponse ;
 * - dès que la voix de Gemini a été entendue pour une réponse, la voix du téléphone ne parle plus pour cette réponse :
 *   si la suite de Gemini échoue, elle reste affichée à l'écran sans être dite.
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
    phoneFallback = true,
    onFallback = () => {},
    onSilent = () => {},
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
    // Une seule voix par réponse : null (rien d'entendu encore), 'natural' (voix de Gemini entendue), 'phone' (voix du
    // téléphone) ou 'silent' (le reste de la réponse est seulement affiché). Jamais de retour en arrière dans une réponse.
    let answerVoice = null;
    const api = {};
    // Mode appel : la réponse arrive encore (begin… finish), et la phrase en attente de la décision « une ou deux demandes ».
    let streaming = false;
    let waiter = null;

    const request = (text, voiced = false) => {
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
                sent = Promise.resolve(fetchAudio(spoken, { signal: controller?.signal, voiced }));
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
        if (pending.length === 0 || answerVoice === 'phone' || answerVoice === 'silent') {
            return;
        }
        const entry = request(pending.join(' '), answerVoice === 'natural');
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
            answerVoice = null;
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
            answerVoice = 'phone';
            // Le son de Gemini est arrêté et vidé avant que la voix du téléphone commence : jamais deux voix.
            player.stop();
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
                    // Même réponse (la phrase est relue) : elle garde la voix du téléphone.
                    lastEndAt = now();
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

        // Partie qui ne sera pas dite par Gemini : la voix du téléphone (si elle est permise et que Gemini n'a pas encore
        // été entendu dans cette réponse), sinon elle reste affichée à l'écran sans être dite.
        const silent = (then) => {
            answerVoice = 'silent';
            player.stop();
            try {
                onSilent();
                api.onsilent?.();
            } catch {
                // Affichage seulement.
            }
            timers.setTimeout(() => myGeneration === generation && then(), 0);
        };
        const fallBack = (part, then, cause = null) => {
            const outcome = answerVoice === 'phone' || (answerVoice === null && phoneFallback) ? 'phone' : 'silent';
            if (cause) {
                onFallback(cause, Math.round(now() - speakAt), outcome);
            }
            if (outcome === 'phone') {
                phone(part, then);
            } else {
                silent(then);
            }
        };

        // Joue l'audio d'une demande (en l'attendant au plus timeoutMs), sinon fallBack pour « part ».
        const play = (entry, part, then, budget = timeoutMs) => {
            const go = () => {
                if (myGeneration !== generation) {
                    return;
                }
                if (entry.state !== 'ready' || answerVoice === 'phone' || answerVoice === 'silent') {
                    // Pas d'audio (ou une autre issue a déjà été choisie pour cette réponse) : jamais deux voix.
                    let cause = null;
                    if (!entry.reported && entry.state !== 'ready') {
                        entry.reported = true;
                        cause = entry.timedOut ? 'timeout' : (entry.network ? 'network' : null);
                    }
                    fallBack(part, then, cause);

                    return;
                }
                const result = entry.result;
                player.play(result.url, {
                    onstart: () => {
                        if (myGeneration !== generation) {
                            return;
                        }
                        answerVoice = 'natural';
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
                    onerror: (cause) => {
                        result.release?.();
                        if (myGeneration !== generation) {
                            return;
                        }
                        fallBack(part, then, cause || 'playback_error');
                    },
                });
            };

            if (entry.state !== 'loading') {
                go();

                return;
            }

            const timer = timers.setTimeout(() => {
                if (entry.state === 'loading' && myGeneration === generation) {
                    entry.timedOut = true;
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
            if (answerVoice === 'phone' || answerVoice === 'silent' || item.request.state === 'failed') {
                fallBack(text, end);
            } else if (item.head) {
                play(item.request, text, end);
            } else {
                // Déjà dite avec la phrase qui portait l'audio.
                timers.setTimeout(end, 0);
            }

            return;
        }

        const announced = takePending(text);

        if (answerVoice === 'phone' || answerVoice === 'silent') {
            fallBack(text, end);

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
                    // La suite est demandée dès que la voix de la première phrase commence (comme en mode appel) : elle
                    // sait alors que Gemini a été entendu, et rien n'est demandé si la première phrase échoue.
                    pending = [parts[1]];
                    play(request(parts[0]), parts[0], () => {
                        const rest = takeCovered(parts[1]);
                        if (rest) {
                            play(rest.request, parts[1], end);
                        } else {
                            takePending(parts[1]);
                            fallBack(parts[1], end);
                        }
                    }, budget);
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

    Object.assign(api, {
        Utterance: NaturalUtterance,
        onsilent: null,
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
            answerVoice = null;
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
    });
    Object.defineProperty(api, 'speaking', { get: () => busy, enumerable: true });

    return api;
}
