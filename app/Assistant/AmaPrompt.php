<?php

namespace App\Assistant;

use App\Support\VoiceAssistant;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\File;

/**
 * Instructions système envoyées à Gemini à chaque message d'un visiteur :
 * 1. instructions d'Ama (modifiables dans l'admin, clé « site.voice_assistant.instructions ») ;
 * 2. informations de l'école (coordonnées, horaires, consultation gratuite), lues dans les réglages ;
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
        - Quand la réponse n'est pas dans ces informations, tu le dis simplement : « Je n'ai pas cette information. » Tu ne devines pas. Tu proposes ensuite la consultation gratuite (pour être conseillé) ou d'écrire à l'équipe sur WhatsApp.
        - Tu ne promets jamais de résultat : ni revenus, ni clients, ni ventes, ni emploi.
        - Si on te demande si tu es un humain ou une IA, tu dis clairement que tu es une assistante IA.
        - Tu ne demandes jamais de code secret, de mot de passe, de code Mobile Money, de code de confirmation ni de code de récupération. Si la personne en écrit un, tu lui dis de ne jamais le partager, même avec l'école.
        - Tu ne demandes aucune donnée personnelle : ni nom, ni numéro, ni adresse e-mail.
        - Tu ne dis jamais qu'un paiement est reçu, validé ou refusé : seule l'équipe le vérifie. Tu expliques où suivre son achat, dans « Mes achats ».
        - Tu ne dictes jamais de numéro de paiement : le numéro exact et le montant s'affichent à l'étape 3 du paiement, avec le bouton « Copier le numéro ».
        - Tu refuses poliment les questions sans rapport avec Lomé IA School, ses formations ou l'utilisation du site, et tu proposes ce que tu peux faire.
        - Pour un cas particulier (remboursement, paiement non reçu, accès manquant, problème de compte), tu proposes d'écrire à l'équipe sur WhatsApp.
        TXT;

    /**
     * Empreintes (SHA-1) des instructions d'origine des versions précédentes : si l'admin n'a jamais modifié le
     * texte, la version actuelle s'applique sans qu'il ait à vider le champ.
     *
     * @var list<string>
     */
    private const array PREVIOUS_DEFAULTS = ['2670523bf69ff63105d9d227cab53a963009cfea'];

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
     */
    public static function build(array $context): string
    {
        return implode("\n\n", array_filter(self::sections($context)));
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
        return "\n\nRègle technique : quand ta réponse dit que tu n'as pas l'information, commence-la par le repère ".self::UNKNOWN_MARKER.' (il est retiré avant que la personne ne la lise). Ne l\'écris jamais dans les autres réponses.';
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
            'WhatsApp de l\'école : +'.config('site.whatsapp.number').' ('.WhatsApp::url().').',
            'Consultation gratuite de 30 minutes, en ligne par appel vidéo, sans compte : '.route('consultation').'.',
        ];

        return '- '.implode("\n- ", $lines);
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
