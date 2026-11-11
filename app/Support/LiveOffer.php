<?php

namespace App\Support;

use App\Models\Formation;
use App\Payments\PaymentMethods;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Formation proposée en direct pendant une période limitée (config/live_offers.php) :
 * encadré de la page de la formation, bandeau de l'accueil, questions fréquentes, image de partage et Ama.
 *
 * Tout disparaît automatiquement quand la dernière séance est terminée (« ends_at », heure de Lomé).
 * Les séances réelles (lien Google Meet, rappels) sont les sessions créées par l'admin dans « Sessions ».
 */
final class LiveOffer
{
    private const string TIMEZONE = 'Africa/Lome';

    /**
     * @param  array<string, mixed>  $config
     */
    private function __construct(
        public readonly Formation $formation,
        private readonly array $config,
    ) {}

    /**
     * Offre en cours (pas encore terminée) pour cette formation visible, sinon null.
     */
    public static function for(?Formation $formation): ?self
    {
        $offer = self::configured($formation);

        return $offer !== null && $formation?->is_active && ! $offer->isOver() ? $offer : null;
    }

    /**
     * Offre de cette formation, même terminée (espace des élèves qui l'ont achetée).
     */
    public static function configured(?Formation $formation): ?self
    {
        if ($formation === null) {
            return null;
        }

        $config = config('live_offers.'.$formation->slug);

        return is_array($config) && ! empty($config['sessions']) ? new self($formation, $config) : null;
    }

    /**
     * Offres en cours, dans l'ordre de leur première séance (bandeau de l'accueil, Ama).
     *
     * @return list<self>
     */
    public static function current(): array
    {
        $slugs = array_keys((array) config('live_offers', []));

        if ($slugs === []) {
            return [];
        }

        return array_values(Formation::query()->published()->whereIn('slug', $slugs)->get()
            ->map(fn (Formation $formation): ?self => self::for($formation))
            ->filter()
            ->sortBy(fn (self $offer): int => $offer->startsAt()->getTimestamp())
            ->all());
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->time((string) $this->config['starts_at']);
    }

    public function endsAt(): CarbonImmutable
    {
        return $this->time((string) $this->config['ends_at']);
    }

    public function isOver(): bool
    {
        return ! now()->lessThan($this->endsAt());
    }

    public function hasStarted(): bool
    {
        return ! now()->lessThan($this->startsAt());
    }

    /**
     * Séances : date de début et de fin (heure de Lomé), jour en toutes lettres, thème et état.
     *
     * @return list<array{number: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable, day: string, weekday: string, title: string, state: string}>
     */
    public function sessions(): array
    {
        $sessions = [];

        foreach (array_values((array) $this->config['sessions']) as $index => $session) {
            $starts = $this->time($session['date'].' '.$this->config['start_time']);
            $ends = $this->time($session['date'].' '.$this->config['end_time']);

            if ($ends->lessThanOrEqualTo($starts)) {
                $ends = $ends->addDay();
            }

            $sessions[] = [
                'number' => $index + 1,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'day' => self::dayLabel($starts),
                'weekday' => Str::ucfirst($starts->translatedFormat('l')),
                'title' => (string) $session['title'],
                'state' => match (true) {
                    ! now()->lessThan($ends) => 'past',
                    ! now()->lessThan($starts) => 'live',
                    default => 'upcoming',
                },
            ];
        }

        return $sessions;
    }

    /**
     * Séance en cours ou prochaine séance (null quand tout est terminé).
     *
     * @return array{number: int, starts_at: CarbonImmutable, ends_at: CarbonImmutable, day: string, weekday: string, title: string, state: string}|null
     */
    public function nextSession(): ?array
    {
        foreach ($this->sessions() as $session) {
            if ($session['state'] !== 'past') {
                return $session;
            }
        }

        return null;
    }

    public function sessionCount(): int
    {
        return count($this->sessions());
    }

    /**
     * « Du jeudi 1er octobre au mercredi 7 octobre 2026 ».
     */
    public function period(): string
    {
        $sessions = $this->sessions();
        $first = $sessions[0]['starts_at'];
        $last = $sessions[count($sessions) - 1]['starts_at'];

        return 'Du '.Str::lcfirst(self::dayLabel($first)).($first->year === $last->year ? '' : ' '.$first->year)
            .' au '.Str::lcfirst(self::dayLabel($last)).' '.$last->year;
    }

    /**
     * « Du 1er au 7 octobre 2026 ».
     */
    public function shortPeriod(): string
    {
        $sessions = $this->sessions();
        $first = $sessions[0]['starts_at'];
        $last = $sessions[count($sessions) - 1]['starts_at'];
        $firstLabel = self::dayNumber($first).($first->month === $last->month ? '' : ' '.$first->translatedFormat('F'));

        return 'Du '.$firstLabel.' au '.self::dayNumber($last).' '.$last->translatedFormat('F Y');
    }

    /**
     * « de 20 h à 23 h ».
     */
    public function hours(): string
    {
        $session = $this->sessions()[0];

        return 'de '.self::hour($session['starts_at']).' à '.self::hour($session['ends_at']);
    }

    /**
     * « Chaque soir de 20 h à 23 h (heure de Lomé, GMT) ».
     */
    public function schedule(): string
    {
        return 'Chaque soir '.$this->hours().' (heure de Lomé, GMT)';
    }

    /**
     * « Même heure au Burkina Faso · de 21 h à minuit au Bénin (GMT+1) ».
     */
    public function neighbours(): string
    {
        $session = $this->sessions()[0];
        $lines = ['Même heure au Burkina Faso'];

        foreach ((array) config('live_sessions.other_timezones') as $country => $timezone) {
            $starts = $session['starts_at']->setTimezone((string) $timezone);
            $ends = $session['ends_at']->setTimezone((string) $timezone);
            $offset = $starts->offsetHours;

            $lines[] = 'de '.self::hour($starts).' à '.self::hour($ends).' au '.$country.' (GMT'.($offset >= 0 ? '+' : '').$offset.')';
        }

        return implode(' · ', $lines);
    }

    /**
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function details(): array
    {
        return array_values((array) ($this->config['details'] ?? []));
    }

    /**
     * @return list<string>
     */
    public function included(): array
    {
        return array_values((array) ($this->config['included'] ?? []));
    }

    /**
     * Description de la page pour Google, WhatsApp et Facebook.
     */
    public function shareDescription(): string
    {
        return 'Formation en direct sur Google Meet, '.Str::lcfirst($this->shortPeriod()).', chaque soir '.$this->hours()
            .' (heure de Lomé). '.$this->sessionCount().' séances enregistrées, accompagnement à vie.';
    }

    /**
     * Bandeau court de l'accueil.
     */
    public function bannerText(): string
    {
        return $this->formation->title.' en direct sur Google Meet · '.$this->shortPeriod().', '.$this->hours().' (heure de Lomé)';
    }

    /**
     * Questions fréquentes de l'offre, fondées sur les informations de Joseph et le fonctionnement réel du paiement.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faq(): array
    {
        $formation = $this->formation;
        $verified = $formation->needsPaymentVerification();
        $countries = collect(PaymentMethods::countries())->map(fn (array $country, string $code): string => PaymentMethods::countryWithArticle($code, $country['name']))->values()->all();
        $countryList = match (count($countries)) {
            0 => '',
            1 => ' depuis '.$countries[0],
            default => ' depuis '.implode(', ', array_slice($countries, 0, -1)).' ou '.$countries[count($countries) - 1],
        };
        $joinMinutes = (int) config('live_sessions.join_window_minutes', 15);

        return [
            [
                'question' => 'Comment je reçois le lien ?',
                'answer' => ($verified ? 'Dès que votre paiement a été vérifié' : 'Dès l\'envoi de la capture de votre paiement')
                    .', la formation s\'ouvre dans « Mon espace » et vous êtes ajouté(e) automatiquement au groupe d\'accompagnement de la formation. '
                    .'Le lien Google Meet de chaque séance se trouve dans « Mon espace », rubrique « Sessions », et dans l\'annonce épinglée du groupe. '
                    .'Le bouton « Rejoindre la session » s\'active '.$joinMinutes.' minutes avant le début, et vous recevez un rappel par e-mail avant chaque séance.',
            ],
            [
                'question' => 'J\'ai raté une séance ?',
                'answer' => 'Chaque séance est enregistrée. L\'enregistrement et le document de la séance sont disponibles à vie dans le groupe d\'accompagnement, sur le site. '
                    .'Vous pouvez vous inscrire pendant toute la semaine et rattraper les séances manquées grâce aux enregistrements.',
            ],
            [
                'question' => 'Je n\'ai qu\'un téléphone ?',
                'answer' => 'Téléphone ou ordinateur : l\'un ou l\'autre suffit pour suivre les séances en direct sur Google Meet, revoir les enregistrements et échanger dans le groupe d\'accompagnement.',
            ],
            [
                'question' => 'Comment payer ?',
                'answer' => 'Par Mobile Money'.$countryList.'. Touchez « Acheter maintenant », créez votre compte (ou connectez-vous) et confirmez votre adresse e-mail avec le code reçu. '
                    .'Choisissez ensuite votre pays et votre opérateur, envoyez le montant exact ('.$formation->formatted_price.') au numéro affiché, puis envoyez la capture d\'écran de votre paiement. '
                    .'Les frais de transfert sont à votre charge. À chaque étape, le bouton « Besoin d\'aide pour payer ? » vous permet de nous écrire sur WhatsApp.',
            ],
            [
                'question' => 'J\'ai payé, et après ?',
                'answer' => $verified
                    ? 'Notre équipe vérifie votre paiement ('.config('site.payments.verification_hours').'). Dès sa validation, la formation s\'ouvre dans « Mon espace », vous êtes ajouté(e) au groupe d\'accompagnement et vous êtes prévenu(e) par e-mail. Vous y trouvez le lien Google Meet des séances, puis les enregistrements et les documents.'
                    : 'Dès l\'envoi de votre capture, la formation s\'ouvre dans « Mon espace » (accès provisoire, le temps que notre équipe vérifie votre paiement) et vous êtes ajouté(e) au groupe d\'accompagnement. Vous y trouvez le lien Google Meet des séances, puis les enregistrements et les documents. Vous êtes prévenu(e) par e-mail quand le paiement est validé.',
            ],
            [
                'question' => 'Vais-je recevoir une attestation ?',
                'answer' => 'Oui : une attestation de participation délivrée par Lomé IA School vous est remise à la fin de la formation, lors de la dernière séance ('.Str::lcfirst($this->sessions()[$this->sessionCount() - 1]['day']).').',
            ],
        ];
    }

    /**
     * Texte envoyé à Ama avec la fiche de la formation.
     */
    public function assistantText(): string
    {
        $lines = [
            'Formation EN DIRECT sur Google Meet : '.$this->period().', '.$this->sessionCount().' séances, '.Str::lcfirst($this->schedule()).' ; '.$this->neighbours().'.',
            'Déroulement : '.collect($this->details())->map(fn (array $detail): string => $detail['title'].' : '.$detail['text'])->implode(' '),
            'Inclus : '.implode(' ; ', $this->included()).'.',
            'Programme des séances (en direct) :',
        ];

        foreach ($this->sessions() as $session) {
            $lines[] = '- Séance '.$session['number'].', '.Str::lcfirst($session['day']).' '.$this->hours().' : '.$session['title'];
        }

        $lines[] = 'Questions fréquentes de cette formation :';

        foreach ($this->faq() as $item) {
            $lines[] = '- '.$item['question'].' '.$item['answer'];
        }

        $lines[] = 'Ne parle jamais de « certificat », de « diplôme » ni de formation « certifiée » : c\'est une attestation de participation délivrée par Lomé IA School.';

        return implode("\n", $lines);
    }

    /**
     * Empreinte des informations de l'offre (version de l'image de partage).
     */
    public function fingerprint(): string
    {
        return substr(md5((string) json_encode($this->config)), 0, 8);
    }

    /**
     * « Jeudi 1er octobre ».
     */
    public static function dayLabel(CarbonImmutable $date): string
    {
        return Str::ucfirst($date->translatedFormat('l')).' '.self::dayNumber($date).' '.$date->translatedFormat('F');
    }

    private static function dayNumber(CarbonImmutable $date): string
    {
        return $date->day === 1 ? '1er' : (string) $date->day;
    }

    /**
     * « 20 h », « 20 h 30 », « minuit » (espaces insécables).
     */
    private static function hour(CarbonImmutable $time): string
    {
        if ($time->hour === 0 && $time->minute === 0) {
            return 'minuit';
        }

        return str_replace(' ', "\u{00A0}", $time->minute === 0 ? $time->format('G \h') : $time->format('G \h i'));
    }

    private function time(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, self::TIMEZONE);
    }
}
