<?php

namespace App\Assistant;

use App\Models\CourseModule;
use App\Models\Formation;
use App\Models\FreeRegistrationOption;
use App\Models\Lesson;
use App\Support\LiveOffer;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
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
    public const int TOKEN_BUDGET = 10000;

    /**
     * Catalogue entier détaillé s'il pèse moins que ce nombre de jetons (très petit catalogue).
     */
    public const int DETAIL_ALL_BUDGET = 1500;

    /**
     * Mots trop courants pour reconnaître une formation dans un message (déjà sans accents, au singulier).
     *
     * @var list<string>
     */
    private const array COMMON_WORDS = ['formation', 'creation', 'creer', 'avec', 'pour', 'les', 'des', 'une', 'sur', 'dans', 'and', 'site', 'pro', 'professionnel', 'competence', 'apprendre', 'quel', 'quelle', 'combien', 'prix', 'cout', 'coute', 'est', 'que', 'qui', 'vous', 'nous', 'votre', 'vos', 'mon', 'mes', 'cette', 'faire', 'savoir', 'veux', 'voudrai', 'bonjour', 'merci', 'oui', 'non', 'aussi', 'plus', 'tout', 'tous', 'leur', 'par', 'pas', 'cest', 'quoi'];

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
     * Formations visibles : liste compacte de toutes (titre, prix exact, catégorie, une phrase, adresse de la page,
     * dates d'une formation en direct, programme publié ou non), puis le détail complet (description, programme
     * réel = titres des modules et des leçons publiées, fiche de la formation en direct) seulement pour les
     * formations « en vue » : celle de la page du visiteur et celle(s) qu'il vient de nommer. Un catalogue très
     * petit (détail complet sous DETAIL_ALL_BUDGET jetons) est envoyé en entier.
     *
     * @param  int|null  $descriptionLimit  longueur maximale des descriptions détaillées (null = texte entier)
     * @param  list<string>  $focus  titres des formations à détailler (voir focus())
     */
    public static function formations(?int $descriptionLimit = null, array $focus = []): string
    {
        $formations = self::published();

        if ($formations->isEmpty()) {
            return 'Aucune formation n\'est affichée sur le site pour le moment.';
        }

        $base = rtrim(route('formations.index'), '/').'/';
        // Groupées par catégorie (le nom de la catégorie n'est écrit qu'une fois).
        $list = $formations->groupBy(fn (Formation $formation): string => $formation->category->label())
            ->map(fn (Collection $group, string $category): string => 'Catégorie « '.$category.' » :'."\n".$group->map(function (Formation $formation): string {
                $lessons = $formation->modules->sum(fn (CourseModule $module): int => $module->lessons->count());
                $previews = $formation->modules->sum(fn (CourseModule $module): int => $module->lessons->where('is_preview', true)->count());
                $liveOffer = LiveOffer::for($formation);

                return '- '.$formation->title.' : '.$formation->formatted_price.'.'
                    .($formation->subtitle ? ' '.trim((string) $formation->subtitle) : '')
                    .' ('.$formation->slug
                    .($lessons > 0 ? ' ; programme publié : '.$lessons.' leçon'.($lessons > 1 ? 's' : '') : '')
                    .($previews > 0 ? ', dont '.$previews.' en aperçu gratuit' : '').')'
                    .($liveOffer !== null ? ' EN DIRECT sur Google Meet : '.Str::lcfirst($liveOffer->period()).', '.Str::lcfirst($liveOffer->schedule()).'.' : '')
                    .($formation->isFree() ? ' '.self::freeText($formation) : '');
            })->implode("\n"))
            ->implode("\n");

        $details = $formations->mapWithKeys(fn (Formation $formation): array => [$formation->title => self::detail($formation, $descriptionLimit)]);
        $all = self::tokens($details->implode("\n\n")) <= self::DETAIL_ALL_BUDGET;
        $shown = $details->filter(fn (string $detail, string $title): bool => $all || in_array($title, $focus, true));

        $text = 'Liste complète, prix exacts. Entre parenthèses : l\'adresse de la page ('.$base.'<adresse>) et, s\'il existe, le programme publié ; sans cette mention, le programme n\'est pas encore publié.'."\n".$list;

        if ($shown->count() < $details->count()) {
            $text .= "\n".'Seules les formations détaillées ci-dessous ont leur description et leur programme ici. Pour une autre formation, donne son prix et sa phrase de la liste, propose d\'ouvrir sa page, et ne décris aucun module ni aucune leçon.';
        }

        return $shown->isEmpty() ? $text : $text."\n\n".$shown->implode("\n\n");
    }

    /**
     * Formations « en vue » : celle de la page du visiteur (contexte) et celles que ses derniers messages nomment
     * (mots distinctifs du titre ou de l'adresse ; au plus deux, et aucune si trop de formations correspondent).
     *
     * @return list<string> titres
     */
    public static function focus(?string $pageFormation, string $recentText = ''): array
    {
        $formations = self::published();
        $titles = [];

        if ($pageFormation !== null && $pageFormation !== '' && $formations->contains('title', $pageFormation)) {
            $titles[] = $pageFormation;
        }

        $words = array_flip(self::words($recentText));

        if ($words !== []) {
            $scores = $formations->mapWithKeys(fn (Formation $formation): array => [
                $formation->title => count(array_filter(self::keywords($formation), fn (string $word): bool => isset($words[$word]))),
            ])->filter()->sortDesc();

            $best = $scores->first();
            $top = $scores->filter(fn (int $score): bool => $score === $best)->keys();

            if ($best !== null && $top->count() <= 2) {
                $titles = [...$titles, ...$top->all()];
            }
        }

        return array_values(array_unique($titles));
    }

    /**
     * Formation gratuite : date, adresse, options et places restantes (lues dans la base), sans aucun paiement.
     */
    public static function freeText(Formation $formation): string
    {
        if ($formation->isFreeEnded()) {
            // Formation terminée : plus aucune inscription ni annulation ; Ama ne propose jamais de s'y inscrire.
            return 'FORMATION GRATUITE TERMINÉE'
                .($formation->eventDateLabel() ? ' (elle a eu lieu le '.Str::lcfirst($formation->eventDateLabel()).')' : '')
                .' : les inscriptions et les annulations sont fermées, ne propose jamais de s\'y inscrire.'
                .($formation->endedButton() !== null ? ' Sur sa page, le bouton « '.$formation->endedButtonLabel().' » mène à '.$formation->endedButtonPath().'.' : '')
                .' Les inscrits gardent l\'accès au groupe des inscrits.';
        }

        $options = $formation->freeOptions()->withTakenCount()->get()->map(fn (FreeRegistrationOption $option): string => $option->label
            .($option->schedule ? ' ('.$option->schedule.')' : '')
            .', '.($option->isOnsite() ? 'présentiel' : 'en ligne sur Google Meet')
            .', '.($option->isFull() ? 'complet' : $option->seatsLeftLabel()));

        return 'FORMATION GRATUITE, sans aucun paiement'
            .($formation->eventDateLabel() ? ', le '.Str::lcfirst($formation->eventDateLabel()) : '')
            .'. Inscription sur sa page : d\'abord créer son compte (« Créer mon compte »), confirmer son e-mail, puis choisir son option ; une seule option par personne'
            .($options->isNotEmpty() ? ' : '.$options->implode(' ; ') : '')
            .'.'
            .($formation->event_location ? ' Adresse du présentiel : '.$formation->event_location.'.' : '')
            .' Le lien Google Meet est publié dans le groupe des inscrits.';
    }

    /**
     * Fiche complète d'une formation : prix, description, programme réel ou absence de programme, fiche en direct.
     */
    private static function detail(Formation $formation, ?int $descriptionLimit): string
    {
        $description = trim(preg_replace('/\s+/u', ' ', (string) $formation->description) ?? '');
        $description = $descriptionLimit !== null ? Str::limit($description, $descriptionLimit, '…') : $description;

        $lines = [
            '### '.$formation->title,
            'Prix : '.$formation->formatted_price.' (catégorie « '.$formation->category->label().' »).'
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
                $lines[] = '- '.$module->title.' : '.$module->lessons
                    ->map(fn (Lesson $lesson): string => $lesson->title.($lesson->is_preview ? ' (aperçu gratuit)' : ''))
                    ->implode(' ; ');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return Collection<int, Formation>
     */
    private static function published(): Collection
    {
        return Formation::query()
            ->published()
            ->with(['modules' => fn ($query) => $query->with(['lessons' => fn ($lessons) => $lessons->where('is_published', true)])])
            ->get();
    }

    /**
     * Mots distinctifs d'une formation (titre et adresse), sans les mots communs à toutes.
     *
     * @return list<string>
     */
    private static function keywords(Formation $formation): array
    {
        return array_values(array_unique(self::words($formation->title.' '.str_replace('-', ' ', $formation->slug))));
    }

    /**
     * Mots d'un texte, sans accents ni majuscules, au singulier approximatif, sans les mots trop courants.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text))) ?: [];

        return array_values(array_filter(array_map(
            fn (string $word): string => strlen($word) > 4 ? rtrim($word, 'sx') : $word,
            $words,
        ), fn (string $word): bool => strlen($word) >= 3 && ! in_array($word, self::COMMON_WORDS, true)));
    }

    /**
     * Informations utiles des pages publiques et règles importantes des CGV.
     */
    public static function publicPages(): string
    {
        $hero = (array) config('site.hero');
        $business = (array) config('site.business');
        $stats = collect((array) config('site.stats'))
            ->map(fn (array $stat): string => ($stat['value'] ?? '').' '.($stat['label'] ?? ''))
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->implode(' ; ');

        $parts = [];

        $home = array_filter([
            $hero['subtitle'] ?? null ? 'Présentation : '.$hero['subtitle'] : null,
            ! empty($hero['points']) ? 'Points mis en avant : '.implode(', ', (array) $hero['points']).'.' : null,
            $stats !== '' ? 'Chiffres officiels : '.$stats.'.' : null,
        ]);
        $parts[] = "### Page d'accueil (".route('home').")\n".implode("\n", $home);

        $services = collect((array) ($business['services'] ?? []))->implode(' ; ');
        $intro = collect((array) ($business['intro'] ?? []))->implode(' ');
        $parts[] = '### Page « Entreprises et réalisations sur mesure » ('.route('business').")\n"
            .($business['title'] ?? '')."\n".$intro
            ."\nServices proposés : ".$services.'.'
            ."\nLe bouton « Réserver un appel gratuit » mène à la consultation gratuite (profil « entreprise » présélectionné).";

        $faq = collect((array) config('site.faq.home'))
            ->map(fn (array $item): string => '- '.($item['question'] ?? '').' '.($item['answer'] ?? ''))
            ->implode("\n");

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
