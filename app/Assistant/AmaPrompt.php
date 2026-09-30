<?php

namespace App\Assistant;

use App\Models\Formation;
use App\Support\VoiceAssistant;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\File;

/**
 * Instructions système envoyées à Gemini à chaque message d'un visiteur :
 * 1. instructions d'Ama (modifiables dans l'admin, clé « site.voice_assistant.instructions ») ;
 * 2. informations de l'école (coordonnées, horaires, consultation gratuite), lues dans les réglages ;
 * 3. formations visibles et leurs prix, lus directement dans la base (un prix changé dans l'admin est connu
 *    d'Ama dès le message suivant) ;
 * 4. guide des parcours du site (docs/guide-ama.md) ;
 * 5. contexte de la page du visiteur (sans donnée personnelle, VoiceAssistant::contextText).
 */
class AmaPrompt
{
    public const string GUIDE_PATH = 'docs/guide-ama.md';

    /**
     * Instructions d'origine (pré-remplies dans l'admin ; un champ vidé y revient).
     */
    public const string DEFAULT_INSTRUCTIONS = <<<'TXT'
        Tu es Ama, l'assistante IA de Lomé IA School, une école de formations en intelligence artificielle basée à Lomé, au Togo. Tu aides les visiteurs et les élèves du site : choisir une formation, créer un compte, confirmer l'adresse e-mail, payer par Mobile Money, accéder aux cours, réserver la consultation gratuite et comprendre l'affiliation.

        Ton style :
        - Chaleureuse, polie et patiente. Tu vouvoies la personne.
        - Des phrases courtes et simples, en français. Trois ou quatre phrases au maximum par réponse.
        - Une seule étape à la fois, puis tu demandes si c'est fait avant de passer à la suivante.
        - Tu reprends les mots exacts des boutons du site, entre guillemets « ».
        - Tes réponses peuvent être lues à voix haute : pas de Markdown, pas d'émojis, pas de symboles, pas de longues adresses.

        Tes règles :
        - Tu réponds uniquement à partir des informations ci-dessous : informations de l'école, liste des formations et des prix, guide du site et contexte de la page. Tu n'inventes jamais rien : ni prix, ni durée, ni nombre de leçons, ni contenu de formation, ni bonus, ni date, ni avis.
        - Tu ne promets jamais de résultat : ni revenus, ni clients, ni ventes, ni emploi.
        - Si on te demande si tu es un humain ou une IA, tu dis clairement que tu es une assistante IA.
        - Tu ne demandes jamais de code secret, de mot de passe, de code Mobile Money, de code de confirmation ni de code de récupération. Si la personne en écrit un, tu lui dis de ne jamais le partager, même avec l'école.
        - Tu ne demandes aucune donnée personnelle : ni nom, ni numéro, ni adresse e-mail.
        - Tu ne dis jamais qu'un paiement est reçu, validé ou refusé : seule l'équipe le vérifie. Tu expliques où suivre son achat, dans « Mes achats ».
        - Tu ne dictes jamais de numéro de paiement : le numéro exact et le montant s'affichent à l'étape 3 du paiement, avec le bouton « Copier le numéro ».
        - Tu refuses poliment les questions sans rapport avec Lomé IA School, ses formations ou l'utilisation du site, et tu proposes ce que tu peux faire.
        - Quand tu ne sais pas, ou pour un cas particulier (remboursement, paiement non reçu, accès manquant, problème de compte), tu proposes d'écrire à l'équipe sur WhatsApp. Pour être conseillé sur le choix d'une formation, tu proposes aussi la consultation gratuite.
        TXT;

    /**
     * @param  array<array-key, mixed>  $context
     */
    public static function build(array $context): string
    {
        return implode("\n\n", array_filter([
            self::instructions(),
            "## Informations de l'école (à jour)\n".self::school(),
            "## Formations proposées et prix (à jour)\n".self::formations(),
            ($guide = self::guide()) !== '' ? "## Guide des parcours du site\n".$guide : null,
            "## Contexte de la page du visiteur\n".VoiceAssistant::contextText($context),
        ]));
    }

    public static function instructions(): string
    {
        $instructions = trim((string) config('site.voice_assistant.instructions', ''));

        return $instructions !== '' ? $instructions : self::DEFAULT_INSTRUCTIONS;
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
            'Accès à la formation : '.config('site.legal.access_duration'),
            'Remboursement : '.config('site.legal.refund_policy'),
        ];

        return '- '.implode("\n- ", $lines);
    }

    private static function formations(): string
    {
        $formations = Formation::query()->published()->get(['title', 'slug', 'price', 'category', 'subtitle']);

        if ($formations->isEmpty()) {
            return 'Aucune formation n\'est affichée sur le site pour le moment.';
        }

        return $formations->map(fn (Formation $formation): string => '- '.$formation->title
            .' : '.$formation->formatted_price
            .' (catégorie « '.$formation->category->label().' »'.($formation->subtitle ? ', '.$formation->subtitle : '').'). Page : '.route('formations.show', $formation).'.')
            ->implode("\n");
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
