/**
 * Sondages des groupes : nouveau vote après le toucher d'un choix. Logique pure, testée avec Node.js
 * (tests/Feature/Chat/PollsTest.php).
 *
 * - une seule réponse : toucher un autre choix le remplace, toucher son choix retire le vote ;
 * - plusieurs réponses : toucher un choix l'ajoute ou le retire.
 */
export function nextPollChoice(mine, optionId, multiple) {
    const id = Number(optionId);
    const current = (mine || []).map(Number).filter((value) => Number.isInteger(value) && value > 0);
    const chosen = current.includes(id);

    if (multiple) {
        return chosen ? current.filter((value) => value !== id) : [...current, id];
    }

    return chosen ? [] : [id];
}

/**
 * Choix du membre lus dans l'attribut data-poll-mine (« 3,5 »).
 */
export function parsePollMine(value) {
    return String(value || '')
        .split(',')
        .map((part) => Number(part.trim()))
        .filter((part) => Number.isInteger(part) && part > 0);
}
