<?php

namespace App\Assistant;

use App\Models\CourseModule;
use App\Models\Formation;
use App\Support\LiveOffer;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Connaissances envoyées à Ama (Gemini) avec chaque message, en plus des instructions :
 * - « Base de connaissances » modifiable dans l'admin (clé « site.assistant_knowledge » : l'école et son
 *   fondateur, questions fréquentes, autres informations) ;
 * - formations visibles (nom, prix, description) et leur programme réel (titres des modules et des leçons
 *   publiées), lus dans la base : une formation sans contenu est signalée comme telle ;
 * - informations utiles des pages publiques (accueil, Entreprises, questions fréquentes) et règles
 *   importantes des CGV.
 * Rien n'est inventé : chaque ligne vient des réglages, de la base ou d'un texte saisi par l'admin.
 */
class AmaKnowledge
{
    /**
     * Texte de départ de « L'école et son fondateur » (faits déjà présents dans le projet). {adresse} et
     * {horaires} sont remplacés à l'envoi par les coordonnées des Réglages du site.
     */
    public const string DEFAULT_SCHOOL = <<<'TXT'
        Lomé IA School est une école de formations en intelligence artificielle basée à Lomé, au Togo. C'est une école récente.
        Fondateur et formateur principal : Joseph Ahiankonou, surnommé « Coach Joseph ». Expert et consultant en intelligence artificielle, il forme depuis 2023 et a formé plus de 1 000 personnes.
        Joseph Academy est une autre activité de Joseph (écriture de livres pour des clients), distincte de Lomé IA School : ce n'est pas l'ancien nom de l'école.
        Accroche du site : « Maîtrisez l'IA et transformez vos compétences en opportunités ».
        Adresse : {adresse}.
        Horaires : {horaires}.
        Les formations sont enregistrées : l'élève les suit à son rythme, avec un accès immédiat après le paiement. L'accès à vie et l'accompagnement à vie sont inclus (dans le groupe privé de chaque formation, réservé aux élèves inscrits).
        De temps en temps, l'école organise des sessions ponctuelles en direct (Google Meet) ou en présentiel à Lomé pour une formation. Elles sont annoncées sur le site au moment où elles sont organisées.
        Une page « Entreprises et centres de formation » présente les services de Joseph aux entreprises et aux centres de formation, avec un appel gratuit à réserver.
        La consultation gratuite dure 30 minutes, se réserve sur le site sans compte et se déroule en ligne par appel vidéo.
        TXT;

    /**
     * Budget indicatif (en jetons) de ce qu'Ama reçoit : au-delà, les descriptions des formations sont raccourcies
     * (les titres des modules et des leçons sont toujours gardés).
     */
    public const int TOKEN_BUDGET = 20000;

    /**
     * Longueur des descriptions de formation quand le budget est dépassé.
     */
    public const int SHORT_DESCRIPTION = 220;

    /**
     * Limites des champs de l'admin.
     */
    public const int MAX_SCHOOL = 8000;

    public const int MAX_OTHER = 10000;

    public const int MAX_QUESTIONS = 60;

    public const int MAX_ANSWER = 1500;

    /**
     * Estimation du nombre de jetons d'un texte français (environ 3,5 caractères par jeton).
     */
    public static function tokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 3.5);
    }

    /**
     * « L'école et son fondateur » (texte de départ si l'admin n'a rien enregistré).
     */
    public static function school(): string
    {
        $stored = config('site.assistant_knowledge.school');
        $text = is_string($stored) ? trim($stored) : self::DEFAULT_SCHOOL;

        return strtr($text, [
            '{adresse}' => (string) config('site.contact.address'),
            '{horaires}' => (string) config('site.contact.hours'),
        ]);
    }

    /**
     * « Questions fréquentes » saisies par l'admin.
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function faq(): array
    {
        $items = [];

        foreach ((array) config('site.assistant_knowledge.faq', []) as $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items;
    }

    public static function other(): string
    {
        return trim((string) config('site.assistant_knowledge.other', ''));
    }

    /**
     * Base de connaissances de l'admin, en une seule partie (vide si rien n'est saisi).
     */
    public static function adminKnowledge(): string
    {
        $parts = [];

        if (($school = self::school()) !== '') {
            $parts[] = "### L'école et son fondateur\n".$school;
        }

        if (($faq = self::faq()) !== []) {
            $parts[] = "### Questions fréquentes\n".collect($faq)
                ->map(fn (array $item): string => 'Question : '.$item['question']."\nRéponse : ".$item['answer'])
                ->implode("\n\n");
        }

        if (($other = self::other()) !== '') {
            $parts[] = "### Autres informations\n".$other;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Formations visibles avec prix, description et programme réel (titres des modules et des leçons publiées).
     * Une formation sans module ni leçon publiés est indiquée comme telle.
     *
     * @param  int|null  $descriptionLimit  longueur maximale des descriptions (null = texte entier)
     */
    public static function formations(?int $descriptionLimit = null): string
    {
        $formations = Formation::query()
            ->published()
            ->with(['modules' => fn ($query) => $query->with(['lessons' => fn ($lessons) => $lessons->where('is_published', true)])])
            ->get();

        if ($formations->isEmpty()) {
            return 'Aucune formation n\'est affichée sur le site pour le moment.';
        }

        return $formations->map(function (Formation $formation) use ($descriptionLimit): string {
            $description = trim(preg_replace('/\s+/u', ' ', (string) $formation->description) ?? '');
            $description = $descriptionLimit !== null ? Str::limit($description, $descriptionLimit, '…') : $description;

            $lines = [
                '### '.$formation->title,
                'Prix : '.$formation->formatted_price.' (catégorie « '.$formation->category->label().' »).'
                    .($formation->subtitle ? ' '.$formation->subtitle : '')
                    .' Page : '.route('formations.show', $formation).'.',
                'Description : '.$description,
            ];

            $modules = $formation->modules
                ->filter(fn (CourseModule $module): bool => $module->lessons->isNotEmpty())
                ->values();

            // Formation proposée en direct (config/live_offers.php) : dates, programme des séances et questions fréquentes.
            $liveOffer = LiveOffer::for($formation);

            if ($liveOffer !== null) {
                $lines[] = $liveOffer->assistantText();
            }

            if ($modules->isEmpty() && $liveOffer !== null) {
                $lines[] = 'Leçons enregistrées : aucune n\'est encore publiée sur le site ; le programme à présenter est celui des séances en direct ci-dessus.';
            } elseif ($modules->isEmpty()) {
                $lines[] = 'Programme : aucun module ni aucune leçon n\'est encore publié pour cette formation. Ne décris aucun programme, aucun module ni aucune leçon : dis simplement que le contenu détaillé n\'est pas encore affiché sur le site.';
            } else {
                $lines[] = 'Programme (titres des modules et des leçons publiées) :';

                foreach ($modules as $module) {
                    $lines[] = '- '.$module->title.' : '.$module->lessons->pluck('title')->implode(' ; ');
                }
            }

            return implode("\n", $lines);
        })->implode("\n\n");
    }

    /**
     * Informations utiles des pages publiques et règles importantes des CGV.
     */
    public static function publicPages(): string
    {
        $hero = (array) config('site.hero');
        $lifetime = (array) config('site.lifetime');
        $business = (array) config('site.business');
        $stats = collect((array) config('site.stats'))
            ->map(fn (array $stat): string => ($stat['value'] ?? '').' '.($stat['label'] ?? ''))
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->implode(' ; ');

        $parts = [];

        $home = array_filter([
            $hero['title'] ?? null ? 'Titre : '.$hero['title'] : null,
            $hero['subtitle'] ?? null ? 'Présentation : '.$hero['subtitle'] : null,
            ! empty($hero['points']) ? 'Points mis en avant : '.implode(', ', (array) $hero['points']).'.' : null,
            $stats !== '' ? 'Chiffres officiels : '.$stats.'.' : null,
            $lifetime !== [] ? ($lifetime['title'] ?? '').' : '.($lifetime['text'] ?? '').' '.($lifetime['note'] ?? '') : null,
        ]);
        $parts[] = "### Page d'accueil (".route('home').")\n".implode("\n", $home);

        $services = collect((array) ($business['services'] ?? []))->map(fn (string $service): string => '- '.$service)->implode("\n");
        $intro = collect((array) ($business['intro'] ?? []))->implode(' ');
        $parts[] = '### Page « Entreprises et centres de formation » ('.route('business').")\n"
            .($business['title'] ?? '')."\n".$intro
            ."\nServices proposés :\n".$services
            ."\nLe bouton « Réserver un appel gratuit » mène à la consultation gratuite (profil « entreprise » présélectionné).";

        $faq = collect((array) config('site.faq.home'))
            ->map(fn (array $item): string => 'Question : '.($item['question'] ?? '')."\nRéponse : ".($item['answer'] ?? ''))
            ->implode("\n\n");

        if ($faq !== '') {
            $parts[] = "### Questions fréquentes du site\n".$faq;
        }

        $rules = array_filter([
            'Accès : '.config('site.legal.access_duration'),
            'Remboursement : '.config('site.legal.refund_policy'),
            'Frais de transfert : '.config('site.payments.fees_notice'),
            'Accès immédiat sous '.Money::fcfa((int) config('site.payments.verification_threshold')).' (accès provisoire, puis vérification par l\'équipe) ; à partir de ce montant, accès après vérification du paiement par l\'équipe.',
        ], fn (?string $line): bool => trim((string) $line) !== '');
        $parts[] = "### Règles importantes (conditions générales de vente)\n".'- '.implode("\n- ", $rules);

        return implode("\n\n", $parts);
    }
}
