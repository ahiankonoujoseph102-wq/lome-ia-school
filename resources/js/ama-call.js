/**
 * Mode appel d'Ama (Gemini) : une conversation à la voix, comme au téléphone.
 *
 * Boucle : Ama salue → le micro s'ouvre (« Ama vous écoute… ») → fin de la phrase repérée par le navigateur →
 * la phrase part au serveur (« Ama réfléchit… ») → la réponse arrive en continu et est lue phrase par phrase dès la
 * première (« Ama parle… ») → le micro se rouvre tout seul.
 * - Toucher le cercle pendant qu'Ama parle (ou réfléchit) : elle s'arrête et le micro s'ouvre.
 * - 20 secondes de silence : courte phrase d'au revoir, puis fin de l'appel.
 * - Limite atteinte ou Ama indisponible : le message est lu, puis l'appel se termine (bouton WhatsApp affiché).
 * - Le micro se coupe toujours à la fin de l'appel (raccrocher, fenêtre fermée, page quittée).
 *
 * Aucune dépendance au navigateur : reconnaissance vocale, synthèse vocale, minuteries et envoi des messages sont
 * fournis par l'appelant (composant « voiceAssistant » de site.js), et remplacés par de fausses fonctions dans les
 * tests (tests/Feature/Site/AmaCallTest.php, exécutés avec Node.js).
 * Le micro ne s'ouvre qu'après start(), appelé seulement par le bouton « Parler avec Ama ».
 */
import { createSentenceSplitter, pickFrenchVoice, speakable, SPEECH_RATE } from './ama.js';

export const SILENCE_SECONDS = 20;

export const CALL_GREETING = 'Bonjour, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.';

export const CALL_GOODBYE = 'Je ne vous entends plus. Merci pour votre appel, et à bientôt sur Lomé IA School !';

export const CALL_ERROR = 'Ama ne peut pas répondre pour le moment. Notre équipe vous répond sur WhatsApp.';

// Erreurs du micro qui terminent l'appel (micro refusé par le visiteur ou par le navigateur).
const DENIED = ['not-allowed', 'service-not-allowed', 'audio-capture'];

/**
 * @param {object} options
 * @param {Function} options.Recognition  constructeur de la reconnaissance vocale du navigateur
 * @param {object} options.synth  synthèse vocale (speak, cancel, getVoices)
 * @param {Function} options.Utterance  constructeur d'une phrase à lire
 * @param {Function} options.ask  (texte, { onText, signal }) → Promise<{ reply, fallback, notice } | null>
 * @param {object} [options.timers]  { setTimeout, clearTimeout }
 * @param {Function} [options.onState]  état : listening, thinking, speaking, ended
 * @param {Function} [options.onVisitor]  phrase du visiteur envoyée
 * @param {Function} [options.onAmaText]  réponse d'Ama en cours (texte reçu jusqu'ici), puis complète (final = true)
 * @param {Function} [options.onEnd]  fin de l'appel : hangup, silence, limit, error, denied
 */
export function createVoiceCall({
    Recognition,
    synth,
    Utterance,
    ask,
    timers = { setTimeout: (callback, delay) => setTimeout(callback, delay), clearTimeout: (id) => clearTimeout(id) },
    onState = () => {},
    onVisitor = () => {},
    onAmaText = () => {},
    onEnd = () => {},
    greeting = CALL_GREETING,
    goodbye = CALL_GOODBYE,
    silenceSeconds = SILENCE_SECONDS,
}) {
    let state = 'idle';
    // Numéro du tour en cours : les rappels d'un tour interrompu (lecture, reconnaissance, réponse) sont ignorés.
    let turn = 0;
    let recognition = null;
    let silenceTimer = null;
    let restartTimer = null;
    let controller = null;
    let queue = [];
    let speaking = false;
    let streamDone = true;
    let afterSpeech = 'listen';
    let failures = 0;

    const setState = (next) => {
        if (state !== next) {
            state = next;
            onState(next);
        }
    };

    const clearSilence = () => {
        if (silenceTimer !== null) {
            timers.clearTimeout(silenceTimer);
            silenceTimer = null;
        }
    };

    const startSilence = () => {
        clearSilence();
        silenceTimer = timers.setTimeout(() => {
            silenceTimer = null;
            if (state === 'listening') {
                stopMicrophone();
                say([goodbye], 'end-silence');
            }
        }, silenceSeconds * 1000);
    };

    const stopMicrophone = () => {
        if (restartTimer !== null) {
            timers.clearTimeout(restartTimer);
            restartTimer = null;
        }

        const current = recognition;
        recognition = null;

        if (current) {
            current.onresult = null;
            current.onend = null;
            current.onerror = null;
            current.onspeechstart = null;
            try {
                current.abort();
            } catch {
                // Déjà arrêtée.
            }
        }
    };

    const stopSpeech = () => {
        queue = [];
        speaking = false;
        try {
            synth.cancel();
        } catch {
            // Rien à arrêter.
        }
    };

    const end = (reason) => {
        if (state === 'ended' || state === 'idle') {
            return;
        }

        turn += 1;
        clearSilence();
        stopMicrophone();
        stopSpeech();
        controller?.abort();
        controller = null;
        setState('ended');
        onEnd(reason);
    };

    // Après la dernière phrase lue : micro rouvert, ou fin de l'appel.
    const finishSpeech = () => {
        if (afterSpeech === 'listen') {
            listen();
        } else {
            end(afterSpeech === 'end-silence' ? 'silence' : afterSpeech.replace('end-', ''));
        }
    };

    const speakNext = (myTurn) => {
        if (myTurn !== turn || state === 'ended') {
            return;
        }

        const sentence = queue.shift();

        if (sentence === undefined) {
            speaking = false;
            if (streamDone) {
                finishSpeech();
            }

            return;
        }

        const text = speakable(sentence);

        if (text === '') {
            speakNext(myTurn);

            return;
        }

        speaking = true;
        setState('speaking');

        const utterance = new Utterance(text);
        utterance.lang = 'fr-FR';
        utterance.rate = SPEECH_RATE;
        utterance.pitch = 1;
        const voice = pickFrenchVoice(synth.getVoices?.() ?? []);
        if (voice) {
            utterance.voice = voice;
        }

        let done = false;
        const next = () => {
            if (!done) {
                done = true;
                speakNext(myTurn);
            }
        };
        utterance.onend = next;
        // Phrase refusée par le navigateur : on passe à la suivante (jamais d'appel bloqué).
        utterance.onerror = next;

        try {
            synth.speak(utterance);
        } catch {
            next();
        }
    };

    const enqueue = (sentences) => {
        if (sentences.length === 0) {
            return;
        }

        queue.push(...sentences);

        if (!speaking) {
            speakNext(turn);
        }
    };

    // Lit des phrases déjà connues (accueil, au revoir, message de limite), puis « then ».
    const say = (sentences, then) => {
        turn += 1;
        stopSpeech();
        afterSpeech = then;
        streamDone = true;
        // Chaque message est lu phrase par phrase (une phrase très longue peut être coupée par certains navigateurs).
        enqueue(sentences.flatMap((text) => {
            const splitter = createSentenceSplitter();

            return [...splitter.push(String(text ?? '')), ...splitter.flush()];
        }));

        if (!speaking) {
            finishSpeech();
        }
    };

    const listen = () => {
        if (state === 'ended') {
            return;
        }

        turn += 1;
        const myTurn = turn;
        stopMicrophone();
        setState('listening');

        if (silenceTimer === null) {
            startSilence();
        }

        let heard = '';
        const current = new Recognition();
        current.lang = 'fr-FR';
        current.interimResults = true;
        current.continuous = false;
        current.maxAlternatives = 1;

        current.onspeechstart = () => {
            if (myTurn === turn) {
                startSilence();
            }
        };
        current.onresult = (event) => {
            if (myTurn !== turn) {
                return;
            }

            // Le visiteur parle : le compte à rebours du silence repart de zéro.
            startSilence();
            for (let index = event.resultIndex ?? 0; index < event.results.length; index += 1) {
                const result = event.results[index];
                if (result.isFinal) {
                    heard += `${result[0].transcript} `;
                }
            }
        };
        current.onerror = (event) => {
            if (myTurn === turn && DENIED.includes(event?.error)) {
                end('denied');
            }
        };
        current.onend = () => {
            if (myTurn !== turn || state !== 'listening') {
                return;
            }

            recognition = null;
            const phrase = heard.trim();

            if (phrase !== '') {
                failures = 0;
                send(phrase);

                return;
            }

            // Rien entendu (le navigateur s'arrête seul après quelques secondes) : le micro se rouvre jusqu'au
            // délai de silence. Plusieurs échecs de suite sans même démarrer : l'appel se termine.
            restartTimer = timers.setTimeout(() => {
                restartTimer = null;
                if (myTurn === turn && state === 'listening') {
                    listen();
                }
            }, 250);
        };

        try {
            current.start();
            recognition = current;
        } catch {
            failures += 1;
            if (failures >= 3) {
                end('error');
            } else {
                restartTimer = timers.setTimeout(() => {
                    restartTimer = null;
                    if (myTurn === turn && state === 'listening') {
                        listen();
                    }
                }, 500);
            }
        }
    };

    const send = async (phrase) => {
        turn += 1;
        const myTurn = turn;
        clearSilence();
        stopMicrophone();
        setState('thinking');
        onVisitor(phrase);

        const splitter = createSentenceSplitter();
        let received = '';
        queue = [];
        speaking = false;
        streamDone = false;
        afterSpeech = 'listen';
        controller = typeof AbortController === 'function' ? new AbortController() : null;

        let result = null;
        try {
            result = await ask(phrase, {
                signal: controller?.signal,
                onText: (delta) => {
                    if (myTurn !== turn) {
                        return;
                    }

                    received += delta;
                    onAmaText(received, false);
                    enqueue(splitter.push(delta));
                },
            });
        } catch {
            result = null;
        }

        if (myTurn !== turn || state === 'ended') {
            return;
        }

        controller = null;

        if (result?.notice) {
            // Phrase refusée avant l'envoi à l'IA (trop longue) : Ama le dit et réécoute.
            say([result.notice], 'listen');

            return;
        }

        if (!result || typeof result.reply !== 'string' || result.reply === '') {
            onAmaText(CALL_ERROR, true, true);
            say([CALL_ERROR], 'end-error');

            return;
        }

        onAmaText(result.reply, true, result.fallback === true);

        if (result.fallback === true) {
            // Limite atteinte ou Gemini indisponible : le message est lu, puis l'appel se termine.
            say([result.reply], 'end-limit');

            return;
        }

        if (received === '') {
            // Réponse reçue d'un bloc (réseau qui ne transmet pas en continu) : lue maintenant, phrase par phrase.
            const whole = createSentenceSplitter();
            enqueue([...whole.push(result.reply), ...whole.flush()]);
        } else {
            enqueue(splitter.flush());
        }

        streamDone = true;

        if (!speaking) {
            finishSpeech();
        }
    };

    return {
        get state() {
            return state;
        },

        /**
         * Démarre l'appel (seulement au toucher du bouton « Parler avec Ama ») : Ama salue, puis le micro s'ouvre.
         */
        start() {
            if (state !== 'idle') {
                return;
            }

            setState('speaking');
            say([greeting], 'listen');
        },

        /**
         * Toucher le cercle : Ama s'arrête de parler (ou de réfléchir) et le micro s'ouvre ; pendant l'écoute,
         * la phrase est envoyée tout de suite.
         */
        touch() {
            if (state === 'speaking' && (afterSpeech === 'end-limit' || afterSpeech === 'end-error')) {
                // Dernier message (limite atteinte, Ama indisponible) : l'appel se termine.
                end(afterSpeech.replace('end-', ''));
            } else if (state === 'speaking' || state === 'thinking') {
                turn += 1;
                stopSpeech();
                controller?.abort();
                controller = null;
                clearSilence();
                listen();
            } else if (state === 'listening') {
                try {
                    recognition?.stop();
                } catch {
                    // Déjà arrêtée.
                }
            }
        },

        /**
         * Raccrocher (bouton rouge, fenêtre fermée, page quittée) : le micro et la voix s'arrêtent tout de suite.
         */
        hangUp(reason = 'hangup') {
            end(reason);
        },
    };
}
