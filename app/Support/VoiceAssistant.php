<?php

namespace App\Support;

use App\Models\Formation;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

/**
 * Assistante vocale IA « Ama » (agent ElevenLabs), proposée par un bouton flottant.
 *
 * Réglages (admin > Réglages du site > Assistante vocale, clé « site.voice_assistant ») : interrupteur,
 * désactivé par défaut, identifiant public de l'agent (« agent_… », pas un secret), affichage dans le
 * lecteur de cours (désactivé par défaut), aide proactive (activée par défaut) et son délai.
 *
 * Intégration officielle d'ElevenLabs : l'élément <elevenlabs-convai agent-id="…"> et le script
 * « @elevenlabs/convai-widget-embed ». Le script n'est chargé qu'au clic sur notre bouton (rien n'est
 * chargé tant que le visiteur ne le demande pas, et jamais si l'interrupteur est désactivé).
 * Libellés : attribut « text-contents » (JSON), seul moyen lu par la version actuelle du widget.
 *
 * Contexte transmis à Ama (voir context()) : la page et son rôle, la formation et son prix, le montant à
 * payer, l'étape du paiement, le pays et l'opérateur, connecté ou non, e-mail confirmé ou non, la dernière
 * erreur affichée et la difficulté repérée. Jamais de donnée personnelle (nom, e-mail, téléphone, mot de
 * passe, code, capture) : la liste des champs est fermée et les textes d'erreur sont nettoyés (clean()).
 */
class VoiceAssistant
{
    /**
     * Version du widget testée (libellés français par « text-contents », placement, ouverture directe,
     * variables dynamiques, événements « elevenlabs-convai:call » et « elevenlabs-agent:contextual-update »).
     * Fixée pour qu'une mise à jour d'ElevenLabs ne casse rien sans vérification : voir CLAUDE.md pour la changer.
     */
    public const string WIDGET_VERSION = '0.18.3';

    /**
     * Script officiel du widget (adresse donnée par ElevenLabs), à la version testée.
     */
    public const string SCRIPT_URL = 'https://unpkg.com/@elevenlabs/convai-widget-embed@'.self::WIDGET_VERSION;

    /**
     * Pages où le bouton apparaît (noms de routes), avec leur nom et leur rôle expliqués à Ama.
     * Jamais l'admin ni la modération ; le lecteur de cours seulement si l'admin l'a choisi (LESSON_PAGES) ;
     * jamais la conversation d'un groupe (le bouton cacherait la zone de saisie).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array PAGES = [
        'home' => ['page d\'accueil', 'présente l\'école et les formations en intelligence artificielle'],
        'formations.index' => ['catalogue des formations', 'liste toutes les formations, avec un filtre par catégorie et une recherche'],
        'formations.show' => ['page d\'une formation', 'présente la formation, son prix et le bouton « Acheter maintenant »'],
        'checkout.show' => ['page de commande et de paiement', 'récapitulatif de la commande, code promo, puis paiement Mobile Money en 4 étapes'],
        'about' => ['page À propos', 'présente l\'école et le formateur'],
        'business' => ['page Entreprises et centres de formation', 'présente les services aux entreprises et le bouton « Réserver un appel gratuit »'],
        'contact' => ['page Contact', 'donne les coordonnées de l\'école et les horaires'],
        'affiliate.program' => ['page Devenez affilié', 'explique le programme d\'affiliation'],
        'consultation' => ['page de la consultation gratuite', 'permet de réserver un appel vidéo gratuit de 30 minutes, sans compte'],
        'consultation.manage' => ['page de gestion d\'un rendez-vous de consultation', 'permet de voir, déplacer ou annuler son rendez-vous'],
        'legal.terms' => ['conditions générales de vente', 'règles de vente, de paiement et de remboursement'],
        'legal.notice' => ['mentions légales', 'informations légales sur le site'],
        'legal.privacy' => ['politique de confidentialité', 'explique l\'usage des données personnelles'],
        'register' => ['page d\'inscription', 'création d\'un compte : nom, numéro WhatsApp, e-mail et mot de passe'],
        'login' => ['page de connexion', 'connexion avec l\'e-mail et le mot de passe'],
        'two-factor.login' => ['page de double authentification', 'saisie du code de l\'application d\'authentification ou d\'un code de récupération'],
        'password.request' => ['page Mot de passe oublié', 'demande d\'un lien par e-mail pour choisir un nouveau mot de passe'],
        'password.reset' => ['page de nouveau mot de passe', 'choix d\'un nouveau mot de passe après avoir cliqué sur le lien reçu par e-mail'],
        'password.confirm' => ['page de confirmation du mot de passe', 'le site redemande le mot de passe avant une action sensible'],
        'email-confirmation.show' => ['page de confirmation de l\'adresse e-mail', 'saisie du code à 6 chiffres reçu par e-mail, renvoi du code ou correction de l\'adresse'],
        'dashboard' => ['accueil de l\'espace élève', 'résumé des formations, des sessions et de la consultation'],
        'student.formations.index' => ['page Mes formations', 'liste des formations achetées'],
        'student.formations.show' => ['page d\'une formation achetée', 'progression, bouton « Reprendre » et programme des leçons'],
        'student.purchases.index' => ['page Mes achats', 'liste des achats et de leur statut'],
        'student.purchases.show' => ['page de suivi d\'un achat', 'statut du paiement : ouvert, en attente de validation, validé ou refusé'],
        'student.affiliate.index' => ['page Affiliation', 'activation de l\'affiliation, liens à partager et statistiques'],
        'student.affiliate.commissions' => ['page des commissions d\'affiliation', 'liste des commissions'],
        'student.affiliate.withdrawals' => ['page des retraits d\'affiliation', 'coordonnées Mobile Money et demandes de retrait'],
        'student.consultation' => ['page de la consultation de l\'élève', 'accès à la gestion de son rendez-vous'],
        'student.sessions.index' => ['page Sessions', 'sessions en direct ou à Lomé annoncées pour ses formations'],
        'student.sessions.show' => ['page d\'une session', 'détails d\'une session, inscription et bouton « Rejoindre »'],
        'student.sessions.payment' => ['page de paiement du supplément d\'une session', 'paiement Mobile Money en 4 étapes'],
        'student.notifications' => ['page Notifications', 'réglages des notifications sur téléphone et par e-mail'],
        'student.profile' => ['page Profil', 'modification du nom, du numéro, de l\'e-mail et de la photo'],
        'security.edit' => ['page Sécurité du compte', 'mot de passe, double authentification et passkeys'],
        'chat.index' => ['page Groupes', 'liste des groupes de discussion des formations achetées'],
    ];

    /**
     * Lecteur de cours : bouton affiché seulement si l'admin l'a choisi (réglage « lessons »).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array LESSON_PAGES = [
        'lessons.show' => ['lecteur de cours', 'vidéo d\'une leçon, fichiers à télécharger et bouton « Marquer comme terminée »'],
    ];

    /**
     * Difficultés repérées par le site (sans IA) : explication pour Ama et premier message d'Ama.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const array DIFFICULTIES = [
        'promo_refuse' => ['Le code promo saisi a été refusé.', 'Je vois que votre code promo a été refusé. Je peux vous expliquer pourquoi et vous aider à continuer votre commande.'],
        'capture_echec' => ['L\'envoi de la capture du paiement a échoué.', 'Je vois que l\'envoi de votre capture n\'a pas marché. Je vais vous aider à la renvoyer.'],
        'code_email_faux' => ['Le code de confirmation de l\'adresse e-mail a été refusé au moins deux fois.', 'Je vois que le code de confirmation ne passe pas. Je vais vous aider à confirmer votre adresse e-mail.'],
        'meme_erreur' => ['La même erreur de formulaire s\'est affichée au moins deux fois.', 'Je vois que le formulaire affiche la même erreur. Je vais vous aider à la corriger.'],
        'etape_longue' => ['Le client est resté longtemps sur la même étape du paiement sans avancer.', 'Je vois que vous êtes sur cette étape du paiement depuis un moment. Voulez-vous que je vous guide ?'],
        'allers_retours' => ['Le client fait des allers-retours entre les étapes du paiement.', 'Je vois que vous hésitez entre les étapes du paiement. Je peux vous guider pas à pas.'],
    ];

    /**
     * Premier message d'Ama sans difficulté repérée.
     */
    public const string DEFAULT_GREETING = 'Bonjour, je suis Ama, l\'assistante de Lomé IA School. Comment puis-je vous aider ?';

    /**
     * Délai par défaut (secondes) sur une étape du paiement avant de proposer l'aide, et ses limites.
     */
    public const int DEFAULT_HELP_DELAY = 90;

    public const int MIN_HELP_DELAY = 30;

    public const int MAX_HELP_DELAY = 600;

    /**
     * Clé de la requête où un contrôleur ajoute le contexte de sa page (montant à payer, erreur…).
     */
    public const string CONTEXT_ATTRIBUTE = 'voice_assistant.context';

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

    public static function showInLessons(): bool
    {
        return (bool) config('site.voice_assistant.lessons', false);
    }

    public static function proactiveHelp(): bool
    {
        return (bool) config('site.voice_assistant.proactive', true);
    }

    public static function helpDelay(): int
    {
        $delay = (int) config('site.voice_assistant.help_delay', self::DEFAULT_HELP_DELAY);

        return max(self::MIN_HELP_DELAY, min(self::MAX_HELP_DELAY, $delay));
    }

    /**
     * Le bouton est-il affiché sur la page de cette route ? Jamais sur l'admin, la modération ni la
     * conversation d'un groupe ; le lecteur de cours seulement si l'admin l'a choisi.
     */
    public static function shownOn(?string $routeName, string $variant = 'marketing'): bool
    {
        if ($routeName === null || $variant === 'chat' || ! self::enabled()) {
            return false;
        }

        if (isset(self::LESSON_PAGES[$routeName])) {
            return self::showInLessons();
        }

        return isset(self::PAGES[$routeName]);
    }

    /**
     * Nom et rôle de la page, pour Ama.
     *
     * @return array{0: string, 1: string}
     */
    public static function page(string $routeName): array
    {
        return self::PAGES[$routeName] ?? self::LESSON_PAGES[$routeName] ?? ['page du site', ''];
    }

    /**
     * Contexte de la page transmis à Ama. Liste fermée de champs, sans aucune donnée personnelle.
     * Le navigateur y ajoute l'étape du paiement, le pays, l'opérateur et les erreurs affichées ensuite.
     *
     * @return array{page: string, role_page: string, formation: ?string, prix_formation: ?string, montant_a_payer: ?string, connecte: bool, email_confirme: ?bool, erreur: ?array{cle: string, texte: string}, difficulte: ?string}
     */
    public static function context(Request $request): array
    {
        $route = $request->route();
        $routeName = $route?->getName() ?? '';
        [$page, $role] = self::page($routeName);
        $user = $request->user();

        $formation = $route?->parameter('formation');
        $formation = $formation instanceof Formation ? $formation : null;

        if ($formation === null && $route?->parameter('session') instanceof LiveSession) {
            $formation = $route->parameter('session')->formation;
        }

        /** @var array<string, mixed> $extra */
        $extra = (array) $request->attributes->get(self::CONTEXT_ATTRIBUTE, []);
        $errors = $request->session()->get('errors');
        $error = null;

        if ($errors instanceof ViewErrorBag && $errors->getBag('default')->isNotEmpty()) {
            $bag = $errors->getBag('default');
            $key = (string) ($bag->keys()[0] ?? '');
            $error = ['cle' => $key, 'texte' => self::clean((string) $bag->first($key))];
        }

        if (isset($extra['erreur']) && is_array($extra['erreur'])) {
            $error = ['cle' => (string) ($extra['erreur']['cle'] ?? ''), 'texte' => self::clean((string) ($extra['erreur']['texte'] ?? ''))];
        }

        $difficulty = isset($extra['difficulte']) && isset(self::DIFFICULTIES[$extra['difficulte']]) ? (string) $extra['difficulte'] : null;

        // Erreurs qui signalent d'emblée une difficulté (sans attendre une deuxième fois).
        $difficulty ??= match ($error['cle'] ?? null) {
            'promo_code' => 'promo_refuse',
            'proof' => 'capture_echec',
            default => null,
        };

        return [
            'page' => $page,
            'role_page' => $role,
            'formation' => $formation?->title,
            'prix_formation' => $formation !== null ? Money::fcfa((int) $formation->price) : null,
            'montant_a_payer' => isset($extra['montant_a_payer']) ? (string) $extra['montant_a_payer'] : null,
            'connecte' => $user !== null,
            'email_confirme' => $user instanceof User ? $user->hasConfirmedEmail() : null,
            'erreur' => $error !== null && $error['texte'] !== '' ? $error : null,
            'difficulte' => $difficulty,
        ];
    }

    /**
     * Ajoute au contexte de la page en cours (appelé par un contrôleur).
     *
     * @param  array<string, mixed>  $values
     */
    public static function addContext(Request $request, array $values): void
    {
        $request->attributes->set(self::CONTEXT_ATTRIBUTE, [...(array) $request->attributes->get(self::CONTEXT_ATTRIBUTE, []), ...$values]);
    }

    /**
     * Texte d'erreur sans donnée personnelle : adresses e-mail, liens, numéros et codes (3 chiffres ou plus
     * à la suite, espaces compris) remplacés, 200 caractères au plus. Même règle dans le navigateur (ama.js).
     */
    public static function clean(string $text): string
    {
        $text = (string) preg_replace('/[^\s@]+@[^\s@]+/u', '[adresse e-mail]', $text);
        $text = (string) preg_replace('/https?:\/\/\S+/iu', '[lien]', $text);
        $text = (string) preg_replace('/\+?\d[\d .\-]{1,}\d/u', '[nombre]', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > 200 ? rtrim(mb_substr($text, 0, 199)).'…' : $text;
    }

    /**
     * Réglages envoyés au composant du navigateur (partiel voice-assistant).
     *
     * @return array<string, mixed>
     */
    public static function browserConfig(Request $request): array
    {
        return [
            'agentId' => self::agentId(),
            'scriptUrl' => self::SCRIPT_URL,
            'texts' => self::TEXTS,
            'context' => self::context($request),
            'pageKey' => $request->route()?->getName() ?? '',
            'proactive' => self::proactiveHelp(),
            'helpDelay' => self::helpDelay(),
            'difficulties' => self::DIFFICULTIES,
            'greeting' => self::DEFAULT_GREETING,
        ];
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
