/**
 * Assistante « Ama » : contexte transmis à l'IA (Gemini ou agent ElevenLabs), repérage des difficultés sans IA,
 * voix du navigateur et conversation gardée dans l'onglet.
 * Fonctions pures (aucune dépendance), testées avec Node.js (tests/Feature/Site/VoiceAssistantContextTest.php).
 *
 * Aucune donnée personnelle n'est transmise : la liste des champs est fermée (FIELDS), ses valeurs viennent
 * du site (jamais d'une saisie du client) et le texte des erreurs affichées est nettoyé (clean), comme côté
 * serveur (App\Support\VoiceAssistant::clean).
 */

/**
 * Champs du contexte, dans l'ordre où Ama les lit. Tout autre champ est ignoré.
 */
export const FIELDS = ['page', 'role_page', 'etape', 'formation', 'prix_formation', 'montant_a_payer', 'pays', 'operateur', 'connecte', 'email_confirme', 'erreur', 'difficulte'];

/**
 * Texte sans donnée personnelle : adresses e-mail, liens, numéros et codes (3 chiffres ou plus) remplacés,
 * 200 caractères au plus.
 */
export function clean(value) {
    let text = String(value ?? '');
    text = text.replace(/[^\s@]+@[^\s@]+/gu, '[adresse e-mail]');
    text = text.replace(/https?:\/\/\S+/giu, '[lien]');
    text = text.replace(/\+?\d[\d .-]{1,}\d/gu, '[nombre]');
    text = text.replace(/\s+/gu, ' ').trim();

    return text.length > 200 ? `${text.slice(0, 199).trimEnd()}…` : text;
}

/**
 * Garde seulement les champs autorisés du contexte (valeurs simples).
 */
export function pick(context) {
    const result = {};

    for (const field of FIELDS) {
        const value = context?.[field];

        if (field === 'erreur') {
            result.erreur = value && typeof value === 'object' && value.texte ? { cle: String(value.cle ?? ''), texte: clean(value.texte) } : null;
        } else if (typeof value === 'boolean' || value === null || value === undefined) {
            result[field] = value ?? null;
        } else {
            // Textes fournis par le site lui-même (page, formation, prix, étape, pays, opérateur) : jamais saisis
            // par le client, ils gardent leurs chiffres (montants) ; seule la longueur est limitée.
            const text = String(value).replace(/\s+/gu, ' ').trim();
            result[field] = text.length > 200 ? `${text.slice(0, 199).trimEnd()}…` : text;
        }
    }

    return result;
}

const yesNo = (value, unknown = 'sans objet') => (value === true ? 'oui' : value === false ? 'non' : unknown);

/**
 * Variables dynamiques envoyées à l'agent (chaînes de caractères, toutes toujours présentes) :
 * « contexte » (tout, en une phrase par ligne) et « message_accueil » (premier message d'Ama),
 * plus chaque information séparément pour qui préfère les utiliser une à une.
 */
export function buildVariables(rawContext, { difficulties = {}, greeting = '' } = {}) {
    const context = pick(rawContext);
    const difficulty = context.difficulte && difficulties[context.difficulte] ? difficulties[context.difficulte] : null;
    const lines = [
        `Page : ${context.page || 'page du site'}${context.role_page ? ` (${context.role_page})` : ''}.`,
        context.etape ? `Étape : ${context.etape}.` : null,
        context.formation ? `Formation : ${context.formation}${context.prix_formation ? `, prix ${context.prix_formation}` : ''}.` : null,
        context.montant_a_payer ? `Montant à payer : ${context.montant_a_payer}.` : null,
        context.pays ? `Pays choisi : ${context.pays}.` : null,
        context.operateur ? `Opérateur choisi : ${context.operateur}.` : null,
        `Client connecté : ${yesNo(context.connecte)}.`,
        context.connecte ? `Adresse e-mail confirmée : ${yesNo(context.email_confirme)}.` : null,
        context.erreur ? `Dernière erreur affichée : « ${context.erreur.texte} ».` : null,
        difficulty ? `Difficulté repérée par le site : ${difficulty[0]}` : null,
    ].filter(Boolean);

    return {
        contexte: lines.join('\n'),
        message_accueil: difficulty ? difficulty[1] : greeting,
        page: context.page || 'page du site',
        etape: context.etape || 'aucune',
        formation: context.formation || 'aucune',
        prix_formation: context.prix_formation || 'aucun',
        montant_a_payer: context.montant_a_payer || 'aucun',
        pays: context.pays || 'non choisi',
        operateur: context.operateur || 'non choisi',
        connecte: yesNo(context.connecte),
        email_confirme: yesNo(context.connecte ? context.email_confirme : null),
        derniere_erreur: context.erreur ? context.erreur.texte : 'aucune',
        difficulte: difficulty ? difficulty[0] : 'aucune',
    };
}

const read = (storage, key) => {
    try {
        return storage?.getItem(key) ?? null;
    } catch {
        return null;
    }
};

const write = (storage, key, value) => {
    try {
        storage?.setItem(key, value);
    } catch {
        // Stockage refusé (navigation privée) : la bulle reste limitée à une fois pour cette page affichée.
    }
};

/**
 * Suivi des difficultés d'une page. « storage » : sessionStorage (une visite = un onglet ouvert).
 * La bulle d'aide est proposée au maximum une fois par page et par visite.
 */
export function createHelpTracker({ storage = null, pageKey = '', enabled = true } = {}) {
    const offeredKey = `ama-aide:${pageKey}`;
    let offered = read(storage, offeredKey) === '1';
    let lastStep = null;
    let backwards = 0;

    return {
        /**
         * Erreur affichée au chargement de la page : renvoie la difficulté à proposer, ou null.
         * Code promo et capture : tout de suite ; code de confirmation de l'e-mail et autres erreurs : à la deuxième fois.
         */
        errorShown(error) {
            if (!error?.texte) {
                return null;
            }

            if (error.cle === 'promo_code') {
                return 'promo_refuse';
            }

            if (error.cle === 'proof') {
                return 'capture_echec';
            }

            const countKey = `ama-erreur:${pageKey}:${error.cle}:${clean(error.texte)}`;
            const count = Number(read(storage, countKey) ?? 0) + 1;
            write(storage, countKey, String(count));

            if (error.cle === 'code' && pageKey === 'email-confirmation.show') {
                // Tous les messages « code incorrect » comptent ensemble (le nombre d'essais restants change).
                const codeKey = `ama-code-faux:${pageKey}`;
                const codeCount = Number(read(storage, codeKey) ?? 0) + 1;
                write(storage, codeKey, String(codeCount));

                return codeCount >= 2 ? 'code_email_faux' : null;
            }

            return count >= 2 ? 'meme_erreur' : null;
        },

        /**
         * Changement d'étape du paiement : renvoie « allers_retours » au deuxième retour en arrière.
         */
        stepChanged(step) {
            if (lastStep !== null && step < lastStep) {
                backwards += 1;
            }

            lastStep = step;

            return backwards >= 2 ? 'allers_retours' : null;
        },

        /**
         * La bulle peut-elle être proposée ? Si oui, elle est comptée comme proposée pour cette page.
         */
        offer() {
            if (!enabled || offered || read(storage, offeredKey) === '1') {
                return false;
            }

            offered = true;
            write(storage, offeredKey, '1');

            return true;
        },

        get offered() {
            return offered;
        },
    };
}

/**
 * Ama (Gemini) : ce que le navigateur sait faire avec la voix, sans rien demander ni ouvrir.
 * - listen : reconnaissance vocale du navigateur (gratuite ; Chrome sur Android, Safari récent…) ;
 * - speak : synthèse vocale du navigateur (lecture des réponses à voix haute).
 * Sans l'une ou l'autre, le bouton correspondant n'est pas affiché : l'écrit reste disponible, sans message d'erreur.
 */
export function speechSupport(win) {
    const recognition = win?.SpeechRecognition ?? win?.webkitSpeechRecognition ?? null;

    return {
        listen: typeof recognition === 'function' && win?.isSecureContext !== false,
        speak: typeof win?.speechSynthesis?.speak === 'function' && typeof win?.SpeechSynthesisUtterance === 'function',
        Recognition: typeof recognition === 'function' ? recognition : null,
    };
}

/**
 * Débit de lecture d'Ama : légèrement plus lent que la normale, plus facile à suivre au téléphone.
 */
export const SPEECH_RATE = 0.92;

// Voix plus naturelles (voix « en ligne » ou « neuronales » de Microsoft, Google, Apple…).
const NATURAL_VOICE = /natural|neural|online|premium|enhanced|am[ée]lior|wavenet|studio/i;
// Voix féminines françaises connues (Windows, macOS, iPhone, Android : fr-fr-x-fra, frc, vlf).
const FEMALE_VOICE = /am[ée]lie|audrey|aur[ée]lie|marie|julie|denise|hortense|c[ée]line|\bl[ée]a\b|virginie|chantal|fran[çc]oise|sylvie|elsa|[ée]lo[ïi]se|vivienne|brigitte|jos[ée]phine|charline|coralie|jacqueline|yvette|ariane|oc[ée]ane|female|femme|fr-fr-x-(fra|frc|vlf)/i;
const MALE_VOICE = /thomas|daniel|nicolas|\bpaul\b|henri|claude|mathieu|jacques|antoine|\bjean\b|guillaume|r[ée]mi|alain|g[ée]rard|fabrice|s[ée]bastien|olivier|\byves\b|\bluc\b|\bmale\b|homme|fr-fr-x-(frb|frd)/i;

/**
 * Note d'une voix pour Ama : française (fr-FR avant les autres), naturelle, féminine de préférence.
 * Une voix non française vaut -1 (jamais choisie).
 */
export function scoreVoice(voice) {
    const lang = String(voice?.lang ?? '');

    if (!/^fr([-_]|$)/i.test(lang)) {
        return -1;
    }

    const name = `${voice?.name ?? ''} ${voice?.voiceURI ?? ''}`;
    let score = /^fr[-_]FR$/i.test(lang) ? 40 : 20;
    score += NATURAL_VOICE.test(name) ? 30 : 0;
    score += /google/i.test(name) ? 15 : 0;
    score += FEMALE_VOICE.test(name) ? 20 : MALE_VOICE.test(name) ? -25 : 0;
    score += voice?.default ? 2 : 0;
    score += voice?.localService ? 1 : 0;

    return score;
}

/**
 * Meilleure voix française du téléphone ou de l'ordinateur (voir scoreVoice), sinon aucune
 * (le navigateur choisit alors lui-même avec la langue « fr-FR »).
 */
export function pickFrenchVoice(voices) {
    const list = Array.isArray(voices) ? voices : [];
    let best = null;
    let bestScore = -1;

    for (const voice of list) {
        const score = scoreVoice(voice);

        if (score > bestScore) {
            best = voice;
            bestScore = score;
        }
    }

    return best;
}

/**
 * Texte d'une réponse préparé pour la lecture à voix haute (puces et symboles retirés).
 */
export function speakable(text) {
    return String(text ?? '')
        .replace(/[•*#_`]/gu, ' ')
        .replace(/\s+/gu, ' ')
        .trim();
}

export const CONVERSATION_KEY = 'ama-conversation';

export const MAX_STORED_MESSAGES = 40;

/**
 * Conversation en cours gardée dans l'onglet (sessionStorage) : elle se retrouve en changeant de page.
 * Stockage refusé (navigation privée) : conversation vide, sans erreur.
 */
export function loadConversation(storage) {
    try {
        const data = JSON.parse(storage?.getItem(CONVERSATION_KEY) ?? 'null');

        if (!data || !Array.isArray(data.messages)) {
            return { id: null, messages: [] };
        }

        const messages = data.messages
            .filter((message) => message && ['ama', 'visitor'].includes(message.from) && typeof message.text === 'string')
            .map((message) => ({ from: message.from, text: message.text, fallback: message.fallback === true }))
            .slice(-MAX_STORED_MESSAGES);

        return { id: typeof data.id === 'string' ? data.id : null, messages };
    } catch {
        return { id: null, messages: [] };
    }
}

export function saveConversation(storage, id, messages) {
    try {
        storage?.setItem(CONVERSATION_KEY, JSON.stringify({ id, messages: messages.slice(-MAX_STORED_MESSAGES).map(({ from, text, fallback }) => ({ from, text, fallback: fallback === true })) }));
    } catch {
        // Stockage refusé : la conversation reste seulement sur cette page.
    }
}

/**
 * Découpe une réponse reçue en continu en phrases à lire une par une : la première phrase est lue dès qu'elle
 * est complète, sans attendre la fin de la réponse. push(morceau) renvoie les phrases terminées ; flush() le reste.
 */
export function createSentenceSplitter({ maxLength = 220 } = {}) {
    let buffer = '';
    const boundary = /[.!?…:;]+[»")\]]*(?=\s)|\n+/u;

    const take = () => {
        const sentences = [];

        for (;;) {
            const match = boundary.exec(buffer);

            if (match) {
                const end = match.index + match[0].length;
                const sentence = buffer.slice(0, end).trim();
                buffer = buffer.slice(end).replace(/^\s+/u, '');

                if (sentence.replace(/[\s.!?…:;•-]/gu, '') !== '') {
                    sentences.push(sentence);
                }

                continue;
            }

            if (buffer.length > maxLength) {
                // Phrase très longue sans ponctuation : coupée après une virgule ou un espace.
                const cut = Math.max(buffer.lastIndexOf(', ', maxLength), buffer.lastIndexOf(' ', maxLength));
                const end = cut > 20 ? cut + 1 : maxLength;
                sentences.push(buffer.slice(0, end).trim());
                buffer = buffer.slice(end).replace(/^\s+/u, '');

                continue;
            }

            return sentences;
        }
    };

    return {
        push(text) {
            buffer += String(text ?? '');

            return take();
        },
        flush() {
            const rest = buffer.trim();
            buffer = '';

            return rest.replace(/[\s.!?…:;•-]/gu, '') !== '' ? [rest] : [];
        },
    };
}

/**
 * Lecture d'une réponse du serveur envoyée au format « text/event-stream » (une ligne « data: {JSON} » par événement :
 * « text » puis « done » ; une simple ligne JSON est aussi acceptée). Les lignes coupées entre deux morceaux sont
 * recollées ; une ligne illisible ou de commentaire est ignorée.
 */
export function createLineReader(onEvent) {
    let buffer = '';

    const emit = (raw) => {
        // Format « text/event-stream » : « data: {…} » ; les lignes de commentaire (« : … ») sont ignorées.
        const line = raw.trim().replace(/^data:\s?/u, '');
        if (line === '' || line.startsWith(':')) {
            return;
        }

        try {
            onEvent(JSON.parse(line));
        } catch {
            // Ligne illisible (coupure du réseau) : ignorée.
        }
    };

    return {
        push(chunk) {
            buffer += chunk;
            const lines = buffer.split('\n');
            buffer = lines.pop() ?? '';
            lines.forEach(emit);
        },
        end() {
            emit(buffer);
            buffer = '';
        },
    };
}

/**
 * « Transmettre au fondateur » : fiche de la carte de confirmation (prénom, moyen de réponse, coordonnée, question).
 * Le serveur vérifie toujours le format ; ces vérifications évitent seulement un aller-retour inutile.
 */
/**
 * « Bonjour » avant midi, « Bonsoir » à partir de midi, selon l'heure du téléphone du visiteur (0 à 23).
 */
export function greetingWord(hour) {
    return Number(hour) < 12 ? 'Bonjour' : 'Bonsoir';
}

/**
 * Prénom affichable : premier mot du nom du compte, lettres, trait d'union et apostrophe seulement (30 au plus).
 */
export function cleanFirstName(name) {
    const first = String(name ?? '').trim().split(/\s+/u)[0] ?? '';
    const letters = first.replace(/[^\p{L}'’-]/gu, '').slice(0, 30);

    return letters === '' ? '' : letters.charAt(0).toLocaleUpperCase('fr') + letters.slice(1);
}

/**
 * Message d'Ama (bulle de présentation, premier message d'une conversation) qui commence par « Bonjour » ou
 * « Bonsoir » selon l'heure du téléphone, suivi du prénom si la personne est connectée : « Bonsoir Kossi, je
 * suis Ama… ». Un « Bonjour » déjà écrit en tête du texte est remplacé ; sinon la salutation est ajoutée devant.
 * Calculé dans le navigateur, sans aucun appel à l'IA ; le prénom n'est jamais envoyé au serveur ni à Google.
 */
export function personalGreeting(text, { hour = 0, firstName = '' } = {}) {
    const name = cleanFirstName(firstName);
    const salutation = name === '' ? greetingWord(hour) : `${greetingWord(hour)} ${name}`;
    const source = String(text ?? '').trim();
    const match = source.match(/^(bonjour|bonsoir|salut|hello)\b\s*([,!.]?)\s*/iu);

    if (match) {
        const rest = source.slice(match[0].length);

        if (rest === '') {
            return `${salutation} !`;
        }

        return match[2] === ',' || match[2] === '' ? `${salutation}, ${rest}` : `${salutation} ! ${rest}`;
    }

    return source === '' ? `${salutation} !` : `${salutation} ! ${source}`;
}

export const HANDOFF_CHANNELS = ['email', 'whatsapp'];

export const HANDOFF_MAX_QUESTION = 1000;

const text = (value) => (typeof value === 'string' ? value.trim() : '');

export function handoffFromDraft(draft = {}, { source = 'chat', question = '' } = {}) {
    const channel = HANDOFF_CHANNELS.includes(draft?.channel) ? draft.channel : '';

    return {
        first_name: text(draft?.first_name).slice(0, 60),
        channel,
        contact: text(draft?.contact),
        question: (text(draft?.question) || text(question)).slice(0, HANDOFF_MAX_QUESTION),
        source: ['chat', 'call', 'form', 'link'].includes(source) ? source : 'form',
    };
}

// Dernière question écrite ou dite par le visiteur (pour préremplir le formulaire de relève).
export function lastVisitorQuestion(messages = []) {
    for (let index = messages.length - 1; index >= 0; index -= 1) {
        if (messages[index]?.from === 'visitor' && text(messages[index].text) !== '') {
            return text(messages[index].text);
        }
    }

    return '';
}

export function handoffErrors(handoff) {
    const errors = {};
    const contact = text(handoff?.contact).replace(/[ ​-‍⁠﻿]/gu, ' ');

    if (text(handoff?.first_name) === '' || /\d|@/u.test(handoff.first_name)) {
        errors.first_name = 'Écrivez votre prénom, par exemple : Awa.';
    }

    if (!HANDOFF_CHANNELS.includes(handoff?.channel)) {
        errors.channel = 'Choisissez comment recevoir la réponse : par e-mail ou sur WhatsApp.';
    } else if (handoff.channel === 'email' && !/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/iu.test(contact.replace(/\s/gu, ''))) {
        errors.contact = contact === ''
            ? 'Écrivez votre adresse e-mail, par exemple : prenom@gmail.com.'
            : 'Cette adresse e-mail n\'est pas complète. Exemple : prenom@gmail.com.';
    } else if (handoff.channel === 'whatsapp' && !(/^\s*\(?(\+|00)/u.test(contact) && contact.replace(/\D/gu, '').length >= 8 && !/[^\d\s+().-]/u.test(contact))) {
        errors.contact = contact === ''
            ? 'Écrivez votre numéro WhatsApp avec l\'indicatif du pays, par exemple : +228 91 23 45 67.'
            : 'Ce numéro WhatsApp n\'est pas complet : ajoutez l\'indicatif du pays. Exemple : +228 91 23 45 67.';
    }

    if (text(handoff?.question).length < 3) {
        errors.question = 'Écrivez la question à transmettre à Coach Joseph.';
    } else if (text(handoff.question).length > HANDOFF_MAX_QUESTION) {
        errors.question = `Votre question est trop longue : ${HANDOFF_MAX_QUESTION} caractères au maximum.`;
    }

    return errors;
}

// Carte prête à être envoyée telle quelle (sinon elle s'ouvre directement en mode « Corriger »).
export function handoffIsReady(handoff) {
    return Object.keys(handoffErrors(handoff)).length === 0;
}
