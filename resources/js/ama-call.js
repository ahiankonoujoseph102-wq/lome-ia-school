/**
 * Mode appel d'Ama (Gemini) : une conversation à la voix, comme au téléphone.
 *
 * Boucle : Ama salue → le micro s'ouvre (« Ama vous écoute… ») → le visiteur parle, avec des pauses s'il le veut →
 * après PAUSE_MS de silence suivant sa dernière parole, tout ce qu'il a dit part en une seule demande (« Ama
 * réfléchit… ») → la réponse arrive en continu et est lue phrase par phrase dès la première (« Ama parle… ») → le micro
 * se rouvre tout seul.
 * - Écoute : le micro reste ouvert en continu ; un morceau de phrase n'est jamais envoyé seul. Si le navigateur arrête
 *   le micro pendant une pause, il est rouvert et les morceaux sont regroupés.
 * - Phrase vide, faite d'hésitations, peu sûre (confiance faible) ou manifestement coupée (« je voudrais savoir ») :
 *   Ama attend encore un peu (phrase coupée), puis dit MISHEARD (« Excusez-moi, je n'ai pas bien entendu… ») sans rien
 *   envoyer au serveur : aucune demande à Gemini, aucun message compté.
 * - Toucher le cercle pendant qu'Ama parle (ou réfléchit) : elle s'arrête et le micro s'ouvre ; pendant l'écoute, la
 *   phrase est envoyée tout de suite.
 * - Interruption à la voix (option bargeIn, réglage expérimental de l'admin) : pendant qu'Ama parle, le micro écoute
 *   aussi ; dès que le visiteur dit au moins deux mots qui ne viennent pas de ce qu'Ama est en train de dire (écho),
 *   elle se tait tout de suite, sans rien dire, et l'écoute jusqu'à la fin de sa phrase (même délai de silence). Un
 *   bruit isolé ou un seul mot n'interrompt jamais. Si le micro ne peut pas écouter pendant la lecture (erreur, ou la
 *   voix coupée par le micro), l'interruption à la voix est abandonnée pour cet appel et la lecture reprend.
 * - 20 secondes de silence : courte phrase d'au revoir, puis fin de l'appel.
 * - Limite atteinte ou Ama indisponible : le message est lu, puis l'appel se termine (bouton WhatsApp affiché).
 * - Le micro se coupe toujours à la fin de l'appel (raccrocher, fenêtre fermée, page quittée).
 *
 * Aucune dépendance au navigateur : reconnaissance vocale, synthèse vocale, minuteries, horloge et envoi des messages
 * sont fournis par l'appelant (composant « voiceAssistant » de site.js), et remplacés par de fausses fonctions dans les
 * tests (tests/Feature/Site/AmaCallTest.php et AmaListeningTest.php, exécutés avec Node.js).
 * Le micro ne s'ouvre qu'après start(), appelé seulement par le bouton « Parler avec Ama ».
 */
import { createSentenceSplitter, pickFrenchVoice, speakable, SPEECH_RATE } from './ama.js';

export const SILENCE_SECONDS = 20;

// Silence après la dernière parole avant d'envoyer la phrase (le visiteur peut faire une petite pause en parlant).
export const PAUSE_MS = 1800;

// Attente supplémentaire quand la phrase semble coupée au milieu (« je voudrais savoir », « le prix de »).
export const CUT_PAUSE_MS = 1700;

// En dessous, la reconnaissance vocale n'est pas sûre de ce qu'elle a entendu (0 = valeur inconnue, ignorée).
export const MIN_CONFIDENCE = 0.3;

// Mots (au moins) qu'il faut entendre, hors écho et hésitations, pour couper la parole à Ama.
export const BARGE_IN_WORDS = 2;

export const CALL_GREETING = 'Bonjour, je suis Ama, l\'assistante de Lomé IA School. Je vous écoute.';

export const CALL_GOODBYE = 'Je ne vous entends plus. Merci pour votre appel, et à bientôt sur Lomé IA School !';

export const CALL_ERROR = 'Ama ne peut pas répondre pour le moment. Notre équipe vous répond sur WhatsApp.';

// Même phrase que App\Assistant\AmaPrompt::MISHEARD_REPLY.
export const MISHEARD = 'Excusez-moi, je n\'ai pas bien entendu. Pouvez-vous répéter votre question ?';

// Erreurs du micro qui terminent l'appel (micro refusé par le visiteur ou par le navigateur).
const DENIED = ['not-allowed', 'service-not-allowed', 'audio-capture'];

// Hésitations sans contenu (même liste que App\Assistant\AmaChat::FILLERS).
const FILLERS = ['euh', 'heu', 'hum', 'hmm', 'mmh', 'mh', 'ben', 'bah', 'beh', 'hein', 'ah', 'oh', 'eh', 'pff', 'bon'];

// Derniers mots qui montrent qu'une phrase est coupée au milieu.
const DANGLING = [
    'de', 'du', 'des', 'le', 'la', 'les', 'l', 'un', 'une', 'et', 'ou', 'mais', 'donc', 'car', 'que', 'qu', 'qui', 'à',
    'au', 'aux', 'en', 'pour', 'par', 'avec', 'sans', 'sur', 'dans', 'chez', 'vers', 'je', 'j', 'tu', 'il', 'elle', 'on',
    'nous', 'vous', 'ils', 'elles', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'votre', 'leur',
    'ce', 'cet', 'cette', 'ces', 'si', 'comme', 'parce', 'puis', 'alors', 'd', 'c', 's', 'n', 'm', 't', 'quel', 'quelle',
    'quels', 'quelles', 'comment', 'savoir', 'demander', 'connaître', 'connaitre', 'voudrais', 'veux', 'aimerais',
    'souhaite', 'souhaiterais', 'est-ce',
];

/**
 * Mots d'un texte, en minuscules, sans ponctuation.
 */
export function words(text) {
    return String(text ?? '')
        .toLowerCase()
        .replace(/[’']/gu, ' ')
        .split(/[^\p{L}\p{N}-]+/u)
        .map((word) => word.replace(/^-+|-+$/gu, ''))
        .filter((word) => word !== '');
}

/**
 * Regroupe des morceaux reconnus, sans répéter ce que certains navigateurs (Chrome Android) renvoient en double
 * (un morceau peut contenir tout le texte précédent).
 */
export function joinTranscripts(parts) {
    let result = '';

    for (const raw of parts) {
        const part = String(raw ?? '').trim();
        if (part === '') {
            continue;
        }

        const current = result.toLowerCase();
        const next = part.toLowerCase();

        if (current === '' || next.startsWith(current)) {
            result = part;
        } else if (!current.endsWith(next)) {
            result = `${result} ${part}`;
        }
    }

    return result.trim();
}

/**
 * Que faire d'une phrase entendue : « send » (l'envoyer), « cut » (elle semble coupée : attendre encore), ou
 * « misheard » (vide, hésitations seules, une seule lettre, confiance faible : demander de répéter).
 */
export function assessPhrase(text, confidence = 0) {
    const all = words(text);
    const meaningful = all.filter((word) => !FILLERS.includes(word));

    if (meaningful.length === 0 || (meaningful.length === 1 && meaningful[0].length < 2)) {
        return 'misheard';
    }

    if (confidence > 0 && confidence < MIN_CONFIDENCE) {
        return 'misheard';
    }

    const trimmed = String(text).trim();
    if (!/[?.!]$/u.test(trimmed) && meaningful.length >= 2 && DANGLING.includes(all.at(-1))) {
        return 'cut';
    }

    return 'send';
}

/**
 * Le texte entendu pendant qu'Ama parle vient-il du visiteur ? Il faut au moins BARGE_IN_WORDS mots (hors hésitations)
 * qui ne sont pas dans ce qu'Ama dit (écho capté par le micro), et qu'ils fassent au moins la moitié des mots entendus.
 */
export function isVisitorSpeech(heard, amaText) {
    const meaningful = words(heard).filter((word) => !FILLERS.includes(word));
    const ama = new Set(words(amaText));
    const novel = meaningful.filter((word) => !ama.has(word));

    return novel.length >= BARGE_IN_WORDS && novel.length * 2 >= meaningful.length;
}

// Un morceau entendu pendant qu'Ama parle contient-il au moins un mot du visiteur (et pas seulement de l'écho) ?
function hasVisitorWords(heard, amaText) {
    const meaningful = words(heard).filter((word) => !FILLERS.includes(word));
    const ama = new Set(words(amaText));
    const novel = meaningful.filter((word) => !ama.has(word));

    return novel.length >= 1 && novel.length * 2 >= meaningful.length;
}

/**
 * @param {object} options
 * @param {Function} options.Recognition  constructeur de la reconnaissance vocale du navigateur
 * @param {object} options.synth  synthèse vocale (speak, cancel, getVoices)
 * @param {Function} options.Utterance  constructeur d'une phrase à lire
 * @param {Function} options.ask  (texte, { onText, signal, interrupted }) → Promise<{ reply, fallback, notice } | null>
 * @param {object} [options.timers]  { setTimeout, clearTimeout }
 * @param {Function} [options.now]  horloge en millisecondes (mesure des durées)
 * @param {boolean} [options.bargeIn]  interruption à la voix (expérimental)
 * @param {Function} [options.onState]  état : listening, thinking, speaking, ended
 * @param {Function} [options.onSpeech]  synthèse vocale : 'start' et 'end' de chaque phrase lue, 'boundary' à chaque mot repéré
 * @param {Function} [options.onVisitor]  phrase du visiteur envoyée
 * @param {Function} [options.onAmaText]  réponse d'Ama en cours (texte reçu jusqu'ici), puis complète (final = true)
 * @param {Function} [options.onMisheard]  Ama demande de répéter (rien n'est envoyé)
 * @param {Function} [options.onBargeInUnavailable]  le micro ne peut pas écouter pendant la lecture sur ce téléphone
 * @param {Function} [options.onTiming]  durées d'un tour (ms) : { pause, first, voice }, avec le résultat du serveur
 * @param {Function} [options.onEnd]  fin de l'appel : hangup, silence, limit, error, denied
 */
export function createVoiceCall({
    Recognition,
    synth,
    Utterance,
    ask,
    timers = { setTimeout: (callback, delay) => setTimeout(callback, delay), clearTimeout: (id) => clearTimeout(id) },
    now = () => Date.now(),
    bargeIn = false,
    onState = () => {},
    onSpeech = () => {},
    onVisitor = () => {},
    onAmaText = () => {},
    onMisheard = () => {},
    onBargeInUnavailable = () => {},
    onTiming = () => {},
    onEnd = () => {},
    greeting = CALL_GREETING,
    goodbye = CALL_GOODBYE,
    silenceSeconds = SILENCE_SECONDS,
    pauseMs = PAUSE_MS,
    cutPauseMs = CUT_PAUSE_MS,
}) {
    let state = 'idle';
    // Numéro du tour en cours : les rappels d'un tour interrompu (lecture, reconnaissance, réponse) sont ignorés.
    let turn = 0;
    let recognition = null;
    let silenceTimer = null;
    let restartTimer = null;
    let pauseTimer = null;
    let controller = null;
    let queue = [];
    let speaking = false;
    let streamDone = true;
    let afterSpeech = 'listen';
    let failures = 0;
    // Écoute : texte des sessions du micro déjà terminées, et session en cours.
    let committed = [];
    let session = { final: '', interim: '', confidence: 0 };
    let cutWaited = false;
    let lastSpeechAt = null;
    // Le visiteur a coupé la parole à Ama : signalé avec sa prochaine phrase.
    let interrupted = false;
    // Interruption à la voix : texte qu'Ama lit pendant ce tour (protection contre l'écho), état du micro.
    let amaSpoken = '';
    let bargeBroken = !bargeIn;
    let bargeRestarts = 0;
    let bargeResults = 0;
    let measure = null;

    const setState = (next) => {
        if (state !== next) {
            state = next;
            onState(next);
        }
    };

    const clearTimer = (id) => {
        if (id !== null) {
            timers.clearTimeout(id);
        }

        return null;
    };

    const clearSilence = () => {
        silenceTimer = clearTimer(silenceTimer);
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
        restartTimer = clearTimer(restartTimer);
        pauseTimer = clearTimer(pauseTimer);

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

    // Ouvre le micro (seul endroit du moteur qui le démarre). Renvoie false si le navigateur refuse.
    const openRecognition = (handlers) => {
        const current = new Recognition();
        current.lang = 'fr-FR';
        current.interimResults = true;
        // Écoute continue : le navigateur ne coupe pas la phrase à la première pause.
        current.continuous = true;
        current.maxAlternatives = 1;
        Object.assign(current, handlers);

        try {
            current.start();
        } catch {
            return null;
        }

        recognition = current;

        return current;
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

    // Après la dernière phrase lue : micro rouvert (ou gardé, s'il écoutait déjà pendant la lecture), ou fin de l'appel.
    const finishSpeech = () => {
        if (afterSpeech === 'listen') {
            const current = recognition;
            listen(current ? { reuse: current, startIndex: bargeResults } : {});
        } else {
            end(afterSpeech === 'end-silence' ? 'silence' : afterSpeech.replace('end-', ''));
        }
    };

    const reportTiming = () => {
        if (measure && measure.voice !== null && measure.result) {
            const { result, ...durations } = measure;
            measure = null;
            onTiming(durations, result);
        }
    };

    // Interruption à la voix : le micro écoute pendant qu'Ama parle (jamais pour un dernier message ni l'au revoir).
    const openBargeIn = () => {
        if (bargeBroken || recognition || afterSpeech !== 'listen' || state !== 'speaking') {
            return;
        }

        const myTurn = turn;
        const opened = openRecognition({
            onresult: (event) => {
                if (myTurn !== turn || state !== 'speaking') {
                    return;
                }

                bargeResults = event.results.length;
                let heard = '';
                let start = null;
                for (let index = 0; index < event.results.length; index += 1) {
                    const text = event.results[index][0].transcript;
                    // Les morceaux qui reprennent ce qu'Ama dit (écho) avant la première parole du visiteur sont ignorés.
                    if (start === null && hasVisitorWords(text, amaSpoken)) {
                        start = index;
                    }
                    if (start !== null) {
                        heard = `${heard} ${text}`;
                    }
                }

                if (start !== null && isVisitorSpeech(heard, amaSpoken)) {
                    interrupt(opened, start);
                }
            },
            onerror: (event) => {
                if (myTurn !== turn) {
                    return;
                }
                if (event?.error !== 'no-speech' && event?.error !== 'aborted') {
                    // Micro indisponible pendant la lecture sur ce téléphone : on n'essaie plus pendant cet appel.
                    giveUpBargeIn();
                }
            },
            onend: () => {
                if (myTurn !== turn || recognition !== opened) {
                    return;
                }
                recognition = null;
                // Le navigateur a fermé le micro sans rien entendre : rouvert tant qu'Ama parle (sans boucle infinie).
                if (state === 'speaking' && !bargeBroken && bargeRestarts < 5) {
                    bargeRestarts += 1;
                    restartTimer = timers.setTimeout(() => {
                        restartTimer = null;
                        if (myTurn === turn) {
                            openBargeIn();
                        }
                    }, 300);
                }
            },
        });

        if (!opened) {
            giveUpBargeIn();
        }
    };

    const giveUpBargeIn = () => {
        if (bargeBroken) {
            return;
        }

        bargeBroken = true;
        stopMicrophone();
        onBargeInUnavailable();
    };

    // Le visiteur parle pendant qu'Ama parle : elle se tait tout de suite et l'écoute (même micro, sans rien perdre).
    const interrupt = (current, startIndex) => {
        turn += 1;
        stopSpeech();
        controller?.abort();
        controller = null;
        interrupted = true;
        measure = null;
        listen({ reuse: current, startIndex, heard: true });
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
        amaSpoken = `${amaSpoken} ${text}`;

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
                if (myTurn === turn) {
                    onSpeech('end');
                }
                speakNext(myTurn);
            }
        };
        utterance.onboundary = () => myTurn === turn && onSpeech('boundary');
        utterance.onstart = () => {
            if (myTurn === turn) {
                onSpeech('start');
            }
            if (myTurn === turn && measure && measure.voice === null) {
                measure.voice = Math.round(now() - measure.sentAt);
                delete measure.sentAt;
                reportTiming();
            }
        };
        utterance.onend = next;
        utterance.onerror = (event) => {
            // Voix coupée par le micro ouvert pendant la lecture (certains téléphones) : l'interruption à la voix est
            // abandonnée pour cet appel et la phrase est relue.
            if (!done && myTurn === turn && recognition && !bargeBroken && ['interrupted', 'canceled'].includes(event?.error)) {
                done = true;
                giveUpBargeIn();
                queue.unshift(sentence);
                speakNext(myTurn);

                return;
            }
            // Phrase refusée par le navigateur : on passe à la suivante (jamais d'appel bloqué).
            next();
        };

        try {
            synth.speak(utterance);
        } catch {
            next();

            return;
        }

        openBargeIn();
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
        stopMicrophone();
        afterSpeech = then;
        streamDone = true;
        amaSpoken = '';
        bargeRestarts = 0;
        // Chaque message est lu phrase par phrase (une phrase très longue peut être coupée par certains navigateurs).
        enqueue(sentences.flatMap((text) => {
            const splitter = createSentenceSplitter();

            return [...splitter.push(String(text ?? '')), ...splitter.flush()];
        }));

        if (!speaking) {
            finishSpeech();
        }
    };

    const heardText = () => joinTranscripts([...committed, joinTranscripts([session.final, session.interim])]);

    // Fin de la phrase (silence après la dernière parole, ou cercle touché) : envoi, attente, ou demande de répéter.
    const finalize = (force = false) => {
        if (state !== 'listening') {
            return;
        }

        const phrase = heardText();
        const verdict = assessPhrase(phrase, session.confidence);

        if (phrase === '' && !force) {
            return;
        }

        if (verdict === 'cut' && !cutWaited && !force) {
            // Phrase coupée au milieu : on attend encore un peu la suite.
            cutWaited = true;
            pauseTimer = timers.setTimeout(() => {
                pauseTimer = null;
                finalize();
            }, cutPauseMs);

            return;
        }

        const pause = lastSpeechAt !== null ? Math.round(now() - lastSpeechAt) : 0;
        stopMicrophone();

        if (verdict !== 'send') {
            // Rien n'est envoyé : aucune demande à Gemini, aucun message compté.
            onMisheard(MISHEARD);
            say([MISHEARD], 'listen');

            return;
        }

        failures = 0;
        send(phrase, pause);
    };

    const schedulePause = () => {
        pauseTimer = clearTimer(pauseTimer);
        pauseTimer = timers.setTimeout(() => {
            pauseTimer = null;
            finalize();
        }, pauseMs);
    };

    const listen = ({ reuse = null, startIndex = 0, heard = false } = {}) => {
        if (state === 'ended') {
            return;
        }

        turn += 1;
        const myTurn = turn;

        if (reuse === null) {
            stopMicrophone();
        } else {
            restartTimer = clearTimer(restartTimer);
            pauseTimer = clearTimer(pauseTimer);
        }
        committed = [];
        cutWaited = false;
        lastSpeechAt = null;
        bargeResults = 0;
        session = { final: '', interim: '', confidence: 0 };
        setState('listening');

        if (silenceTimer === null || heard) {
            startSilence();
        }

        let offset = startIndex;
        const handlers = {
            onspeechstart: () => {
                if (myTurn === turn) {
                    startSilence();
                }
            },
            onresult: (event) => {
                if (myTurn !== turn) {
                    return;
                }

                const finals = [];
                const interims = [];
                const confidences = [];
                for (let index = offset; index < event.results.length; index += 1) {
                    const result = event.results[index];
                    if (result.isFinal) {
                        finals.push(result[0].transcript);
                        if (result[0].confidence > 0) {
                            confidences.push(result[0].confidence);
                        }
                    } else {
                        interims.push(result[0].transcript);
                    }
                }

                session = {
                    final: joinTranscripts(finals),
                    interim: joinTranscripts(interims),
                    confidence: confidences.length ? confidences.reduce((sum, value) => sum + value, 0) / confidences.length : 0,
                };

                if (heardText() !== '') {
                    // Le visiteur parle : le silence repart de zéro, l'envoi attend la fin de sa phrase.
                    lastSpeechAt = now();
                    cutWaited = false;
                    startSilence();
                    schedulePause();
                }
            },
            onerror: (event) => {
                if (myTurn === turn && DENIED.includes(event?.error)) {
                    end('denied');
                }
            },
            onend: () => {
                if (myTurn !== turn || state !== 'listening') {
                    return;
                }

                recognition = null;
                // Le navigateur a fermé le micro (pause, ou rien entendu) : ce qui a été dit est gardé, et le micro est
                // rouvert pour la suite ; la phrase part seulement après le silence (PAUSE_MS).
                const sessionText = joinTranscripts([session.final, session.interim]);
                if (sessionText !== '') {
                    committed.push(sessionText);
                }
                session = { final: '', interim: '', confidence: session.confidence };
                offset = 0;

                restartTimer = timers.setTimeout(() => {
                    restartTimer = null;
                    if (myTurn === turn && state === 'listening') {
                        reopen();
                    }
                }, committed.length ? 0 : 250);
            },
        };

        const reopen = () => {
            const opened = openRecognition(handlers);
            if (!opened) {
                failures += 1;
                if (failures >= 3) {
                    end('error');
                } else {
                    restartTimer = timers.setTimeout(() => {
                        restartTimer = null;
                        if (myTurn === turn && state === 'listening') {
                            reopen();
                        }
                    }, 500);
                }
            }
        };

        if (reuse) {
            // Micro déjà ouvert pendant la lecture (interruption à la voix) : on continue sur lui, sans le bip d'une
            // réouverture. Visiteur qui vient de couper la parole : ce qu'il a déjà dit compte.
            Object.assign(reuse, handlers);
            recognition = reuse;
            if (heard) {
                lastSpeechAt = now();
                schedulePause();
            }
        } else {
            reopen();
        }
    };

    const send = async (phrase, pause) => {
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
        amaSpoken = '';
        bargeRestarts = 0;
        const wasInterrupted = interrupted;
        interrupted = false;
        measure = { sentAt: now(), pause, first: null, voice: null, result: null };
        controller = typeof AbortController === 'function' ? new AbortController() : null;

        let result = null;
        try {
            result = await ask(phrase, {
                signal: controller?.signal,
                interrupted: wasInterrupted,
                onText: (delta) => {
                    if (myTurn !== turn) {
                        return;
                    }

                    if (measure && measure.first === null) {
                        measure.first = Math.round(now() - measure.sentAt);
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
            // Phrase refusée avant l'envoi à l'IA (trop longue, ou mal entendue) : Ama le dit et réécoute.
            measure = null;
            if (result.misheard) {
                onMisheard(result.notice);
            }
            say([result.notice], 'listen');

            return;
        }

        if (!result || typeof result.reply !== 'string' || result.reply === '') {
            measure = null;
            onAmaText(CALL_ERROR, true, true);
            say([CALL_ERROR], 'end-error');

            return;
        }

        onAmaText(result.reply, true, result.fallback === true);

        if (result.fallback === true) {
            // Limite atteinte ou Gemini indisponible : le message est lu, puis l'appel se termine.
            measure = null;
            say([result.reply], 'end-limit');

            return;
        }

        if (measure) {
            measure.result = result;
            reportTiming();
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
                measure = null;
                clearSilence();
                interrupted = state === 'speaking' || interrupted;
                listen();
            } else if (state === 'listening' && heardText() !== '') {
                finalize(true);
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
