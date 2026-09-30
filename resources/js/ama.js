/**
 * Assistante vocale « Ama » : contexte transmis à l'agent ElevenLabs et repérage des difficultés, sans IA.
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
