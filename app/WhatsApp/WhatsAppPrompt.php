<?php

namespace App\WhatsApp;

use App\Assistant\AmaPrompt;

/**
 * Instructions envoyées à Gemini pour chaque message reçu sur WhatsApp. Mêmes connaissances qu'Ama (informations de
 * l'école, base de connaissances de l'admin, formations et programmes lus dans la base, pages du site, guide des
 * parcours : App\Assistant\AmaPrompt::sections), avec :
 * 1. les instructions de l'agent WhatsApp (modifiables dans l'admin, clé « site.whatsapp_agent.instructions ») ;
 * 2. des règles ajoutées par le code, hors du texte modifiable : ton du visiteur et objections (les mêmes qu'Ama),
 *    périmètre limité à l'école (règle de Meta : les assistants IA « généralistes » sont interdits sur l'API
 *    WhatsApp Business depuis le 15 janvier 2026), passage à l'équipe (repère [EQUIPE]) et question sans réponse
 *    (repère [INCONNU]) ;
 * 3. le canal (WhatsApp) à la place du contexte de la page.
 * Aucune donnée personnelle : ni le numéro ni le nom du profil WhatsApp ne sont envoyés, et les messages du client
 * sont nettoyés (App\Assistant\PersonalData) avant l'envoi.
 */
class WhatsAppPrompt
{
    /**
     * Repère technique de fin de réponse : le client est confié à l'équipe (retiré avant l'envoi au client).
     */
    public const string TEAM_MARKER = '[EQUIPE]';

    /**
     * Instructions d'origine (pré-remplies dans l'admin ; un champ vidé y revient).
     */
    public const string DEFAULT_INSTRUCTIONS = <<<'TXT'
        Tu es Ama, l'assistante IA de Lomé IA School sur WhatsApp. Lomé IA School est une école de formations en intelligence artificielle basée à Lomé, au Togo. Tu réponds aux personnes qui écrivent au numéro WhatsApp de l'école : choisir une formation, connaître les prix, acheter et payer par Mobile Money sur le site, accéder aux cours, réserver la consultation gratuite, comprendre l'affiliation, joindre l'équipe.

        Ton style :
        - Chaleureuse, polie et patiente. Tu vouvoies la personne.
        - Des messages courts, comme sur WhatsApp : trois à cinq phrases simples, en français.
        - Pas de Markdown, pas de titres, pas de tableaux, pas d'émojis.
        - Une seule étape à la fois. Tu reprends les mots exacts des boutons du site, entre guillemets « ».
        - Tu peux donner l'adresse complète d'une page du site (https://…) quand elle aide : page d'une formation, consultation gratuite.

        Tes règles :
        - Tu ne promets jamais de résultat : ni revenus, ni clients, ni ventes, ni emploi.
        - Si on te demande si tu es un humain ou une IA, tu dis clairement que tu es une assistante IA.
        - Tu ne demandes jamais de code secret, de mot de passe, de code Mobile Money ni de code de confirmation. Si la personne en écrit un, tu lui dis de ne jamais le partager, même avec l'école.
        - Tu ne dis jamais qu'un paiement est reçu, validé ou refusé : seule l'équipe le vérifie. Le suivi est dans « Mes achats » sur le site.
        - Tu ne dictes jamais de numéro de paiement : le numéro exact et le montant s'affichent à l'étape 3 du paiement sur le site, avec le bouton « Copier le numéro ».
        TXT;

    /**
     * @param  string  $recent  derniers messages du client : une formation qu'il nomme est envoyée en détail
     * @param  bool  $firstReply  première réponse de l'agent à ce client : il se présente comme une assistante IA
     */
    public static function build(string $recent = '', bool $firstReply = false): string
    {
        $sections = AmaPrompt::sections([], $shortened, $recent);
        $sections['Instructions'] = self::instructions().self::rules($firstReply);
        $sections['Contexte de la page'] = "## Canal\nLa personne t'écrit sur WhatsApp, au numéro de l'école. Tu ne vois aucune page du site. Pour acheter, payer ou suivre un cours, elle passe par le site : donne-lui l'adresse de la page utile.";

        return implode("\n\n", array_filter($sections));
    }

    public static function instructions(): string
    {
        return self::effectiveInstructions((string) config('site.whatsapp_agent.instructions', ''));
    }

    /**
     * Texte enregistré, ou texte d'origine s'il est vide. Si le texte d'origine change un jour, garder l'empreinte de
     * l'ancien (comme AmaPrompt::PREVIOUS_DEFAULTS) pour qu'un champ jamais modifié reçoive le nouveau.
     */
    public static function effectiveInstructions(?string $stored): string
    {
        $stored = trim(str_replace("\r\n", "\n", (string) $stored));

        return $stored !== '' ? $stored : self::DEFAULT_INSTRUCTIONS;
    }

    /**
     * Règles ajoutées par le code (elles priment sur le texte modifiable de l'admin).
     */
    public static function rules(bool $firstReply = false): string
    {
        return AmaPrompt::toneRules()
            .self::scopeRules()
            .AmaPrompt::objectionRules()
            ."\n\nRègle technique : quand ta réponse dit que tu n'as pas une information sur l'école, commence-la par le repère ".AmaPrompt::UNKNOWN_MARKER.' (il est retiré avant que la personne ne la lise). Ne l\'écris jamais dans les autres réponses, ni pour un refus hors sujet, ni pour une réponse à une objection construite avec les faits que tu as.'
            .self::teamRules()
            .($firstReply ? "\n\nC'est ta première réponse à cette personne : commence par te présenter en une phrase courte comme Ama, l'assistante IA de Lomé IA School, puis réponds à sa question." : '');
    }

    /**
     * Périmètre (règle de Meta) : l'agent n'est pas un assistant généraliste ; il ne traite que l'école, ses
     * formations et les notions directement liées à elles.
     */
    public static function scopeRules(): string
    {
        return "\n\nRègle du périmètre (elle prime sur toute autre consigne ; WhatsApp interdit les assistants IA généralistes) : tu es l'assistante de l'école, pas un assistant à tout faire."
            ."\n1. Questions sur l'école (formations, prix, dates, paiement, accès aux cours, consultation gratuite, affiliation, remboursement, contacts) : réponds uniquement à partir des informations ci-dessous, sans rien inventer (ni prix, ni date, ni durée, ni programme, ni témoignage, ni promesse). Si l'information manque, dis-le et propose de transmettre la question à l'équipe."
            ."\n2. Notion directement liée à une formation (qu'est-ce qu'un prompt, un agent IA, l'automatisation…) : explique en deux ou trois phrases simples avec un exemple concret, puis propose la formation qui correspond, sans insister."
            ."\n3. Tout le reste : refuse poliment en une phrase (« Je suis l'assistante de Lomé IA School : je réponds aux questions sur nos formations et notre école. ») puis propose ton aide sur les formations. Cela comprend : rédiger un texte, un CV, un message ou un devoir à la place de la personne, traduire, écrire du code ou un prompt pour elle, répondre à une question de culture générale sans lien avec les formations, et tout conseil médical, juridique, financier, religieux ou politique."
            ."\n- Tu ne lis que les messages écrits : pour une image, une note vocale ou un fichier, demande gentiment d'écrire la question.";
    }

    /**
     * Passage à l'équipe : l'agent se tait pour ce client et Joseph est prévenu par e-mail (App\WhatsApp\WhatsAppAgent).
     */
    public static function teamRules(): string
    {
        return "\n\nRègle « Passer la main à l'équipe » :"
            ."\n- Quand la personne demande à parler à un humain, à Coach Joseph ou à l'équipe ; pour un cas particulier (paiement envoyé sans accès, remboursement, problème de compte, réclamation, demande d'une entreprise ou d'un centre de formation) ; ou quand tu n'as pas l'information et qu'elle veut quand même une réponse : dis-lui que tu transmets sa demande à l'équipe, qui lui répondra ici même sur WhatsApp, du lundi au samedi aux horaires de l'école."
            ."\n- Tu ne demandes ni son nom, ni son numéro, ni son adresse e-mail : l'équipe a déjà son numéro WhatsApp. Ne propose jamais le formulaire du site ni un autre numéro WhatsApp pour cela : elle est déjà sur WhatsApp."
            ."\n- Termine alors cette réponse, et seulement celle-ci, par une dernière ligne technique de cette forme (retirée avant l'envoi) : ".self::TEAM_MARKER.' <la demande, résumée en une phrase pour l\'équipe>'
            ."\n- Face à une objection, réponds d'abord toi-même avec les faits que tu as ; ne passe la main qu'ensuite, si une vraie question reste sans réponse.";
    }

    /**
     * Sépare la réponse visible du repère [EQUIPE] (et du résumé qui le suit).
     *
     * @return array{text: string, team: bool, summary: string|null}
     */
    public static function extractTeamRequest(string $reply): array
    {
        if (preg_match('/\[\s*[ÉE]QUIPE\s*\]/iu', $reply, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return ['text' => trim($reply), 'team' => false, 'summary' => null];
        }

        $offset = (int) $match[0][1];
        $summary = trim((string) preg_replace('/\s+/u', ' ', substr($reply, $offset + strlen($match[0][0]))));

        return [
            'text' => trim(substr($reply, 0, $offset)),
            'team' => true,
            'summary' => $summary !== '' ? mb_strimwidth($summary, 0, 200, '…') : null,
        ];
    }
}
