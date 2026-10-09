/**
 * Notes vocales avec pause (interrupteur « Pause des notes vocales ») : logique pure, sans navigateur,
 * testée avec Node.js (tests/Feature/Chat/VoicePauseTest.php).
 */

/**
 * Le navigateur sait-il mettre en pause un enregistrement ? Sinon, le bouton « Pause » est caché
 * et l'enregistrement reste comme avant (envoi direct).
 */
export function canPauseRecording(Recorder) {
    return (
        typeof Recorder === 'function' &&
        typeof Recorder.prototype?.pause === 'function' &&
        typeof Recorder.prototype?.resume === 'function'
    );
}

/**
 * Chronomètre de l'enregistrement : il s'arrête pendant la pause. Les heures sont passées en paramètre
 * (millisecondes) pour pouvoir le tester sans attendre.
 */
export function createRecordingClock(startedAt) {
    let elapsed = 0;
    let segmentStart = startedAt;
    let paused = false;

    return {
        get paused() {
            return paused;
        },

        pause(now) {
            if (!paused) {
                elapsed += Math.max(0, now - segmentStart);
                paused = true;
            }
        },

        resume(now) {
            if (paused) {
                segmentStart = now;
                paused = false;
            }
        },

        /**
         * Durée enregistrée, pauses non comprises (millisecondes).
         */
        elapsed(now) {
            return elapsed + (paused ? 0 : Math.max(0, now - segmentStart));
        },
    };
}
