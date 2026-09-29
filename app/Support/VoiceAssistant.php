<?php

namespace App\Support;

/**
 * Assistante vocale IA « Ama » (agent ElevenLabs), proposée par un bouton flottant sur les pages publiques.
 *
 * Réglages (admin > Réglages du site > Assistante vocale, clé « site.voice_assistant ») : interrupteur,
 * désactivé par défaut, et identifiant public de l'agent (« agent_… », pas un secret).
 *
 * Intégration officielle d'ElevenLabs : l'élément <elevenlabs-convai agent-id="…"> et le script
 * « @elevenlabs/convai-widget-embed ». Le script n'est chargé qu'au clic sur notre bouton (rien n'est
 * chargé tant que le visiteur ne le demande pas, et jamais si l'interrupteur est désactivé).
 * Libellés : attribut « text-contents » (JSON), seul moyen lu par la version actuelle du widget.
 */
class VoiceAssistant
{
    /**
     * Script officiel du widget (adresse donnée par ElevenLabs).
     */
    public const string SCRIPT_URL = 'https://unpkg.com/@elevenlabs/convai-widget-embed';

    /**
     * Pages publiques où le bouton apparaît (noms de routes). Jamais l'admin, l'espace élève ni le lecteur de cours.
     *
     * @var list<string>
     */
    public const array PAGES = ['home', 'formations.index', 'formations.show', 'business', 'contact'];

    public const string EXAMPLE_ID = 'agent_9901m3phrpdwedt88vsdqmnwk09z';

    /**
     * Libellés du widget en français (clés de « text-contents » lues par le widget ElevenLabs).
     *
     * @var array<string, string>
     */
    public const array TEXTS = [
        'main_label' => 'Parler avec notre assistante',
        'start_call' => 'Démarrer l\'appel',
        'start_chat' => 'Écrire',
        'send_message' => 'Envoyer',
        'new_call' => 'Nouvel appel',
        'end_call' => 'Raccrocher',
        'end_chat' => 'Terminer',
        'mute_microphone' => 'Couper le micro',
        'text_mode' => 'Passer à l\'écrit',
        'voice_mode' => 'Passer à la voix',
        'switched_to_text_mode' => 'Conversation écrite',
        'switched_to_voice_mode' => 'Conversation vocale',
        'change_language' => 'Changer de langue',
        'collapse' => 'Réduire',
        'expand' => 'Agrandir',
        'copied' => 'Copié !',
        'accept_terms' => 'Accepter',
        'dismiss_terms' => 'Annuler',
        'listening_status' => 'Je vous écoute',
        'speaking_status' => 'Parlez pour l\'interrompre',
        'connecting_status' => 'Connexion…',
        'chatting_status' => 'Conversation avec l\'assistante IA',
        'queue_waiting_status' => 'L\'assistante est occupée. Vous serez mis en relation dès qu\'elle sera disponible.',
        'queue_waiting_status_short' => 'En attente de l\'assistante',
        'input_label' => 'Votre message',
        'input_placeholder' => 'Écrivez votre message…',
        'input_placeholder_text_only' => 'Écrivez votre message…',
        'input_placeholder_new_conversation' => 'Commencer une nouvelle conversation',
        'user_ended_conversation' => 'Vous avez terminé la conversation',
        'agent_ended_conversation' => 'L\'assistante a terminé la conversation',
        'conversation_id' => 'Identifiant',
        'error_occurred' => 'Une erreur est survenue',
        'queue_timed_out' => 'L\'assistante est toujours occupée. Réessayez un peu plus tard.',
        'copy_id' => 'Copier l\'identifiant',
        'initiate_feedback' => 'Comment s\'est passée cette conversation ?',
        'request_follow_up_feedback' => 'Dites-nous en plus',
        'thanks_for_feedback' => 'Merci pour votre avis !',
        'thanks_for_feedback_details' => 'Votre avis nous aide à améliorer notre accompagnement.',
        'follow_up_feedback_placeholder' => 'Racontez-nous votre expérience…',
        'submit' => 'Envoyer',
        'go_back' => 'Retour',
        'copy' => 'Copier',
        'download' => 'Télécharger',
        'wrap' => 'Retour à la ligne',
        'agent_working' => 'En cours…',
        'agent_done' => 'Terminé',
        'agent_error' => 'Une erreur est survenue',
        'attach_file' => 'Joindre un fichier',
        'remove_file' => 'Retirer le fichier',
        'file_upload_error' => 'Le fichier n\'a pas pu être envoyé.',
        'file_type_unsupported' => 'Type de fichier non accepté. Types acceptés :',
        'file_too_large' => 'Le fichier est trop volumineux.',
        'file_limit_reached' => 'Nombre maximal de fichiers atteint pour cette conversation.',
        'typing_indicator' => 'L\'assistante écrit…',
        'rich_content_unavailable' => 'Ce contenu ne peut pas être affiché',
    ];

    public static function enabled(): bool
    {
        return (bool) config('site.voice_assistant.enabled', false) && self::isValidAgentId(self::agentId());
    }

    public static function agentId(): string
    {
        return (string) config('site.voice_assistant.agent_id', '');
    }

    /**
     * Le bouton est-il affiché sur la page de cette route ?
     */
    public static function shownOn(?string $routeName): bool
    {
        return $routeName !== null && in_array($routeName, self::PAGES, true) && self::enabled();
    }

    /**
     * Nettoie un identifiant collé depuis un téléphone : caractères invisibles et espaces retirés ;
     * l'identifiant est extrait d'un texte ou d'un lien plus long (« …/agents/agent_… »).
     */
    public static function cleanAgentId(?string $value): string
    {
        $value = (string) $value;

        if (! mb_check_encoding($value, 'UTF-8')) {
            return trim($value);
        }

        $value = (string) preg_replace('/[\x{00AD}\x{180E}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value);
        $value = (string) preg_replace('/[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u', ' ', $value);
        $value = trim($value);

        if (preg_match('/(?<![A-Za-z0-9])agent_[A-Za-z0-9]+/i', $value, $match)) {
            return 'agent_'.substr($match[0], 6);
        }

        return str_replace(' ', '', $value);
    }

    public static function isValidAgentId(string $value): bool
    {
        return preg_match('/^agent_[A-Za-z0-9]{8,64}$/', $value) === 1;
    }

    /**
     * Message d'erreur précis pour un identifiant refusé (déjà nettoyé).
     */
    public static function invalidMessage(string $value): string
    {
        $example = 'Exemple : '.self::EXAMPLE_ID.'.';

        if (! str_starts_with($value, 'agent_')) {
            return 'L\'identifiant de l\'agent doit commencer par « agent_ ». '.$example;
        }

        return 'L\'identifiant de l\'agent ne doit contenir, après « agent_ », que des lettres et des chiffres. '.$example;
    }
}
