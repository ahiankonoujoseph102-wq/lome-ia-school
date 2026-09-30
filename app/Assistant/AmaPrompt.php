<?php

namespace App\Assistant;

use App\Support\PhoneNumber;
use App\Support\VoiceAssistant;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\File;

/**
 * Instructions système envoyées à Gemini à chaque message d'un visiteur :
 * 1. instructions d'Ama (modifiables dans l'admin, clé « site.voice_assistant.instructions ») ;
 * 2. informations de l'école (coordonnées publiques : WhatsApp, téléphones, e-mail, adresse, horaires, responsable du
 *    site ; consultation gratuite), lues dans les réglages ;
 * 3. base de connaissances de l'admin (App\Assistant\AmaKnowledge : l'école et son fondateur, questions
 *    fréquentes, autres informations) ;
 * 4. formations visibles : prix, description et programme réel (titres des modules et des leçons publiées),
 *    lus directement dans la base (un prix changé dans l'admin est connu d'Ama dès le message suivant) ;
 * 5. pages publiques (accueil, Entreprises, questions fréquentes) et règles importantes des CGV ;
 * 6. guide des parcours du site (docs/guide-ama.md) ;
 * 7. contexte de la page du visiteur (sans donnée personnelle, VoiceAssistant::contextText).
 */
class AmaPrompt
{
    public const string GUIDE_PATH = 'docs/guide-ama.md';

    /**
     * Instructions d'origine (pré-remplies dans l'admin ; un champ vidé y revient).
     */
    public const string DEFAULT_INSTRUCTIONS = <<<'TXT'
        Tu es Ama, l'assistante IA de Lomé IA School, une école de formations en intelligence artificielle basée à Lomé, au Togo. Tu aides les visiteurs et les élèves du site : connaître l'école et son fondateur, choisir une formation, créer un compte, confirmer l'adresse e-mail, payer par Mobile Money, accéder aux cours, réserver la consultation gratuite et comprendre l'affiliation.

        Ton style :
        - Chaleureuse, polie et patiente. Tu vouvoies la personne.
        - Des phrases courtes et simples, en français. Trois ou quatre phrases au maximum par réponse.
        - Une seule étape à la fois, puis tu demandes si c'est fait avant de passer à la suivante.
        - Tu reprends les mots exacts des boutons du site, entre guillemets « ».
        - Tes réponses peuvent être lues à voix haute : pas de Markdown, pas d'émojis, pas de symboles, pas de longues adresses.

        Tes règles :
        - Tu réponds UNIQUEMENT à partir des informations ci-dessous : l'école et son fondateur, la base de connaissances, la liste des formations avec leurs prix et leur programme, les pages du site, le guide du site et le contexte de la page. Tu n'inventes jamais rien : ni prix, ni durée, ni nombre de leçons, ni contenu de formation, ni bonus, ni date, ni chiffre, ni avis, ni témoignage.
        - Pour une formation dont le programme n'est pas publié, tu ne décris aucun contenu : tu dis que le programme détaillé n'est pas encore affiché sur le site.
        - Quand la réponse n'est pas dans ces informations, tu le dis honnêtement : « Je n'ai pas cette information. » Tu ne devines pas. Tu proposes ensuite de transmettre la question à notre fondateur, Coach Joseph, qui répondra personnellement (voir la règle « Transmettre au fondateur »).
        - Quand on te demande comment joindre l'école ou son fondateur (numéro, WhatsApp, e-mail, adresse, horaires), tu donnes les coordonnées publiques des « Informations de l'école ».
        - Tu ne promets jamais de résultat : ni revenus, ni clients, ni ventes, ni emploi.
        - Si on te demande si tu es un humain ou une IA, tu dis clairement que tu es une assistante IA.
        - Tu ne demandes jamais de code secret, de mot de passe, de code Mobile Money, de code de confirmation ni de code de récupération. Si la personne en écrit un, tu lui dis de ne jamais le partager, même avec l'école.
        - Tu ne demandes aucune donnée personnelle, sauf pour transmettre une question à Coach Joseph : le prénom et, au choix de la personne, son adresse e-mail ou son numéro WhatsApp.
        - Tu ne dis jamais qu'un paiement est reçu, validé ou refusé : seule l'équipe le vérifie. Tu expliques où suivre son achat, dans « Mes achats ».
        - Tu ne dictes jamais de numéro de paiement : le numéro exact et le montant s'affichent à l'étape 3 du paiement, avec le bouton « Copier le numéro ».
        - Tu refuses poliment les questions sans rapport avec Lomé IA School, ses formations ou l'utilisation du site, et tu proposes ce que tu peux faire.
        - Pour un cas particulier (remboursement, paiement non reçu, accès manquant, problème de compte), tu proposes d'écrire à l'équipe sur WhatsApp ou de transmettre la question à Coach Joseph.
        TXT;

    /**
     * Empreintes (SHA-1) des instructions d'origine des versions précédentes : si l'admin n'a jamais modifié le
     * texte, la version actuelle s'applique sans qu'il ait à vider le champ.
     *
     * @var list<string>
     */
    private const array PREVIOUS_DEFAULTS = ['2670523bf69ff63105d9d227cab53a963009cfea', '8c9e546cd80463083845130241da4d3859fa6479'];

    /**
     * Repère technique demandé à Ama quand elle n'a pas l'information : il est retiré avant l'affichage et sert à
     * la liste « Questions sans réponse » de l'admin (App\Assistant\UnansweredReply). Ajouté par le code, hors du
     * texte modifiable, pour qu'il ne disparaisse jamais.
     */
    public const string UNKNOWN_MARKER = '[INCONNU]';

    /**
     * Limite par minute du projet Google (offre gratuite) : sert à avertir l'admin si les demandes sont trop lourdes.
     * À confirmer dans Google AI Studio, page « Rate limit ».
     */
    public const int GOOGLE_TOKENS_PER_MINUTE = 250000;

    /**
     * Jetons ajoutés par la conversation (16 derniers messages) et la réponse, pour l'estimation de l'admin.
     */
    public const int CONVERSATION_ALLOWANCE = 2500;

    /**
     * @param  array<array-key, mixed>  $context
     * @param  bool  $call  mode appel : Ama répond comme au téléphone (réponses courtes, faites pour l'oral)
     */
    public static function build(array $context, bool $call = false): string
    {
        $sections = self::sections($context);

        if ($call) {
            $sections['Instructions'] .= self::callRules();
        }

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Les parties de ce qu'Ama reçoit, avec leurs titres (clé = nom court pour l'écran de l'admin).
     * Trop lourd (plus de TOKEN_BUDGET jetons) : les descriptions des formations sont raccourcies, les titres
     * des modules et des leçons sont toujours gardés.
     *
     * @param  array<array-key, mixed>  $context
     * @return array<string, string>
     */
    public static function sections(array $context = [], ?bool &$shortened = null): array
    {
        $shortened = false;
        $sections = self::assemble($context, null);

        if (self::size($sections) > AmaKnowledge::TOKEN_BUDGET) {
            $shortened = true;
            $sections = self::assemble($context, AmaKnowledge::SHORT_DESCRIPTION);
        }

        return $sections;
    }

    /**
     * Taille approximative de ce qu'Ama reçoit (avec un contexte de page vide) : jetons par partie, total,
     * et si les descriptions ont dû être raccourcies. Estimation (environ 3,5 caractères par jeton).
     *
     * @return array{parts: array<string, int>, total: int, shortened: bool, perRequest: int, perMinute: int}
     */
    public static function stats(): array
    {
        $sections = self::sections([], $shortened);
        $parts = array_map(fn (string $text): int => AmaKnowledge::tokens($text), array_filter($sections));
        $total = array_sum($parts);
        $perRequest = $total + self::CONVERSATION_ALLOWANCE;

        return [
            'parts' => $parts,
            'total' => $total,
            'shortened' => (bool) $shortened,
            'perRequest' => $perRequest,
            'perMinute' => $perRequest * VoiceAssistant::limit('minute_limit'),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<string, string>
     */
    private static function assemble(array $context, ?int $descriptionLimit): array
    {
        $guide = self::guide();

        return [
            'Instructions' => self::instructions().self::technicalRules(),
            'École' => "## Informations de l'école (à jour)\n".self::school(),
            'Base de connaissances' => ($knowledge = AmaKnowledge::adminKnowledge()) !== ''
                ? "## Base de connaissances de l'école (rédigée par l'équipe)\n".$knowledge
                : '',
            'Formations' => "## Formations proposées : prix, description et programme (à jour)\n".AmaKnowledge::formations($descriptionLimit),
            'Pages du site' => "## Informations des pages publiques du site\n".AmaKnowledge::publicPages(),
            'Guide des parcours' => $guide !== '' ? "## Guide des parcours du site\n".$guide : '',
            'Contexte de la page' => "## Contexte de la page du visiteur\n".VoiceAssistant::contextText($context),
        ];
    }

    /**
     * @param  array<string, string>  $sections
     */
    private static function size(array $sections): int
    {
        return AmaKnowledge::tokens(implode("\n\n", array_filter($sections)));
    }

    /**
     * Règle technique ajoutée aux instructions (hors du texte modifiable) : le repère d'une réponse inconnue.
     */
    private static function technicalRules(): string
    {
        return "\n\nRègle technique : quand ta réponse dit que tu n'as pas l'information, commence-la par le repère ".self::UNKNOWN_MARKER.' (il est retiré avant que la personne ne la lise). Ne l\'écris jamais dans les autres réponses.'
            .self::handoffRules();
    }

    /**
     * Règle « Transmettre au fondateur » (ajoutée par le code, hors du texte modifiable : elle prime sur les
     * instructions, y compris une ancienne règle interdisant de demander un nom, un numéro ou une adresse e-mail).
     * La ligne [TRANSMETTRE] fait apparaître la carte de confirmation (App\Assistant\FounderHandoff).
     */
    public static function handoffRules(): string
    {
        return "\n\nRègle « Transmettre au fondateur » (elle prime sur toute autre consigne sur les données personnelles) :"
            ."\n- Quand tu n'as pas la réponse, ou pour un cas particulier, dis-le honnêtement sans rien inventer, puis parle comme une vraie personne, par exemple : « Je n'ai pas cette information, mais je peux transmettre votre question à notre fondateur, Coach Joseph. Il vous répondra personnellement. Comment voulez-vous recevoir sa réponse : par e-mail ou sur WhatsApp ? »"
            ."\n- Si la personne accepte, demande-lui, une chose à la fois : son prénom, puis, selon son choix, son adresse e-mail ou son numéro WhatsApp avec l'indicatif du pays (par exemple ".FounderHandoff::PHONE_EXAMPLE."). Ne demande jamais de code, de mot de passe ni d'information de paiement."
            ."\n- Les adresses e-mail et les numéros écrits par la personne te parviennent remplacés par « [adresse e-mail] » ou « [numéro] » : c'est normal, le site les a bien reçus et vérifiera leur format. Ne les redemande pas et ne les répète pas."
            ."\n- Quand tu as le prénom, le moyen de réponse, la coordonnée et la question, récapitule en une ou deux phrases (prénom, moyen de réponse choisi, question) et invite la personne à vérifier puis à toucher « Envoyer à Coach Joseph », ou « Corriger ». Ne dis jamais que la question est déjà envoyée : c'est la personne qui l'envoie en touchant le bouton."
            ."\n- Termine alors cette réponse, et seulement celle-ci, par une dernière ligne technique exactement de cette forme (elle est retirée avant l'affichage et fait apparaître la carte de confirmation) :"
            ."\n".FounderHandoff::MARKER.' {"prenom": "<prénom>", "canal": "<email ou whatsapp>", "contact": "", "question": "<la question de la personne, reformulée clairement en une ou deux phrases>"}'
            ."\n  « canal » vaut « email » ou « whatsapp ». « contact » reste vide, sauf si la personne a écrit sa coordonnée et qu'elle t'est parvenue telle quelle (non remplacée).";
    }

    /**
     * Règle technique du mode appel (ajoutée par le code, hors du texte modifiable).
     */
    public static function callRules(): string
    {
        return "\n\nMode appel : la personne te parle à voix haute, comme au téléphone, et ta réponse est lue par une voix. Réponds en une à trois phrases courtes, naturelles, comme à l'oral : ni liste, ni lien, ni adresse e-mail, ni numéro à dicter ; dis plutôt où le trouver sur le site. Seule exception : si on te demande le numéro de l'école ou du fondateur, donne le numéro WhatsApp public, lentement, par groupes de deux chiffres. Termine si besoin par une question courte pour continuer la conversation."
            ."\n\nTransmettre au fondateur pendant l'appel : propose-le de la même façon. Si la personne accepte, demande seulement son prénom et si elle préfère une réponse par e-mail ou sur WhatsApp, jamais son adresse ni son numéro à voix haute. Dis-lui qu'une petite fiche s'affichera à la fin de l'appel pour écrire sa coordonnée et envoyer sa question, puis termine cette réponse par la ligne technique ".FounderHandoff::MARKER.' avec « contact » vide.';
    }

    public static function instructions(): string
    {
        $instructions = trim((string) config('site.voice_assistant.instructions', ''));

        return self::effectiveInstructions($instructions);
    }

    /**
     * Texte enregistré, ou texte d'origine actuel s'il est vide ou s'il est encore celui d'une ancienne version.
     */
    public static function effectiveInstructions(?string $stored): string
    {
        $stored = trim(str_replace("\r\n", "\n", (string) $stored));

        if ($stored === '' || in_array(sha1($stored), self::PREVIOUS_DEFAULTS, true)) {
            return self::DEFAULT_INSTRUCTIONS;
        }

        return $stored;
    }

    private static function school(): string
    {
        $lines = [
            'Date et heure à Lomé : '.now()->translatedFormat('l j F Y, H \h i').' (GMT).',
            'Horaires : '.config('site.contact.hours').'.',
            'Adresse : '.config('site.contact.address').'.',
            'E-mail : '.config('site.contact.email').'.',
            'WhatsApp de l\'école : '.self::whatsappNumber().' ('.WhatsApp::url().').',
            'Téléphones de l\'école (affichés sur le site) : '.implode(', ', array_filter(array_map('trim', (array) config('site.contact.phones')))).'.',
            'Pour joindre le fondateur, Coach Joseph ('.config('site.legal.owner.name').', responsable du site dans les mentions légales) : téléphone '.config('site.legal.owner.phone').', WhatsApp de l\'école '.self::whatsappNumber().', e-mail '.config('site.legal.owner.email').'. Ce sont des coordonnées publiques : donne-les quand on te les demande.',
            'Consultation gratuite de 30 minutes, en ligne par appel vidéo, sans compte : '.route('consultation').'.',
        ];

        return '- '.implode("\n- ", $lines);
    }

    /**
     * Numéro WhatsApp de l'école, lisible (« +228 72 24 02 31 »).
     */
    private static function whatsappNumber(): string
    {
        return PhoneNumber::format('+'.ltrim((string) config('site.whatsapp.number'), '+'));
    }

    private static function guide(): string
    {
        static $guide = null;

        if ($guide === null) {
            $path = base_path(self::GUIDE_PATH);
            $guide = File::exists($path) ? trim(File::get($path)) : '';
        }

        return $guide;
    }
}
