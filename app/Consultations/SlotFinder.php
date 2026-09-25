<?php

namespace App\Consultations;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\ConsultationBlock;
use App\Models\ConsultationHour;
use App\Models\ConsultationSettings;
use App\Models\LiveSession;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Créneaux de consultation disponibles, à l'heure de Lomé.
 *
 * - Grille régulière dans chaque plage horaire du jour : un créneau toutes les « durée + pause » minutes,
 *   qui doit se terminer avant la fin de la plage.
 * - Exclus : trop proches (délai minimum), trop lointains (jours à l'avance), jours ou plages bloqués,
 *   rendez-vous déjà réservés (pause comprise), sessions en direct ou en présentiel (pause comprise),
 *   jours où le nombre maximum de consultations est atteint.
 */
class SlotFinder
{
    private ConsultationSettings $settings;

    public function __construct(?ConsultationSettings $settings = null)
    {
        $this->settings = $settings ?? ConsultationSettings::current();
    }

    /**
     * Créneaux libres, regroupés par jour (« 2026-10-05 » => [débuts]).
     *
     * @return array<string, list<CarbonImmutable>>
     */
    public function available(?int $ignoreConsultationId = null): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $lastDay = $today->addDays($this->settings->max_days_ahead);
        $busy = $this->busyPeriods($today, $lastDay->endOfDay(), $ignoreConsultationId);
        $perDay = $this->bookedPerDay($today, $lastDay->endOfDay(), $ignoreConsultationId);
        $earliest = CarbonImmutable::now()->addHours($this->settings->min_notice_hours);
        $hours = ConsultationHour::query()->orderBy('starts_at')->get()->groupBy('weekday');
        $days = [];

        for ($day = $today; $day->lte($lastDay); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');

            if ($this->settings->max_per_day !== null && ($perDay[$key] ?? 0) >= $this->settings->max_per_day) {
                continue;
            }

            $slots = [];

            foreach ($hours->get($day->dayOfWeekIso, collect()) as $range) {
                foreach ($this->gridFor($day, $range) as $start) {
                    if ($start->lt($earliest) || $this->overlaps($start, $busy['padded'], $this->settings->buffer_minutes) || $this->overlaps($start, $busy['blocks'], 0)) {
                        continue;
                    }

                    $slots[] = $start;
                }
            }

            if ($slots !== []) {
                $days[$key] = $slots;
            }
        }

        return $days;
    }

    /**
     * Ce début de créneau est-il réservable maintenant ?
     */
    public function isAvailable(CarbonInterface $start, ?int $ignoreConsultationId = null): bool
    {
        $key = $start->copy()->format('Y-m-d');
        $slots = $this->available($ignoreConsultationId)[$key] ?? [];

        foreach ($slots as $slot) {
            if ($slot->equalTo($start)) {
                return true;
            }
        }

        return false;
    }

    public function duration(): int
    {
        return $this->settings->duration_minutes;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function gridFor(CarbonImmutable $day, ConsultationHour $range): array
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $range->starts_at));
        [$endHour, $endMinute] = array_map('intval', explode(':', $range->ends_at));
        $start = $day->setTime($startHour, $startMinute);
        $end = $day->setTime($endHour, $endMinute);
        $step = max(5, $this->settings->duration_minutes + $this->settings->buffer_minutes);
        $grid = [];

        for ($slot = $start; $slot->addMinutes($this->settings->duration_minutes)->lte($end); $slot = $slot->addMinutes($step)) {
            $grid[] = $slot;
        }

        return $grid;
    }

    /**
     * Le créneau (élargi de la pause avant et après) chevauche-t-il une période occupée ?
     *
     * @param  Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $busy
     */
    private function overlaps(CarbonImmutable $start, Collection $busy, int $buffer): bool
    {
        $from = $start->subMinutes($buffer);
        $to = $start->addMinutes($this->settings->duration_minutes + $buffer);

        return $busy->contains(fn (array $period): bool => $period[0]->lt($to) && $period[1]->gt($from));
    }

    /**
     * Périodes occupées : rendez-vous réservés et sessions non annulées (pause respectée autour),
     * plages bloquées (sans pause : on peut consulter jusqu'à leur début).
     *
     * @return array{padded: Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>, blocks: Collection<int, array{0: CarbonImmutable, 1: CarbonImmutable}>}
     */
    private function busyPeriods(CarbonImmutable $from, CarbonImmutable $to, ?int $ignoreConsultationId): array
    {
        $consultations = Consultation::query()
            ->where('status', ConsultationStatus::Booked)
            ->where('ends_at', '>', $from)
            ->where('starts_at', '<', $to)
            ->when($ignoreConsultationId, fn ($query) => $query->whereKeyNot($ignoreConsultationId))
            ->get(['starts_at', 'ends_at'])
            ->map(fn (Consultation $consultation): array => [CarbonImmutable::parse($consultation->starts_at), CarbonImmutable::parse($consultation->ends_at)]);

        $sessions = LiveSession::query()
            ->whereNull('cancelled_at')
            ->where('ends_at', '>', $from)
            ->where('starts_at', '<', $to)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (LiveSession $session): array => [CarbonImmutable::parse($session->starts_at), CarbonImmutable::parse($session->ends_at)]);

        $blocks = ConsultationBlock::query()
            ->where('ends_at', '>', $from)
            ->where('starts_at', '<', $to)
            ->get(['starts_at', 'ends_at'])
            ->map(fn (ConsultationBlock $block): array => [CarbonImmutable::parse($block->starts_at), CarbonImmutable::parse($block->ends_at)]);

        return ['padded' => $consultations->concat($sessions)->values(), 'blocks' => $blocks->values()];
    }

    /**
     * @return array<string, int>
     */
    private function bookedPerDay(CarbonImmutable $from, CarbonImmutable $to, ?int $ignoreConsultationId): array
    {
        return Consultation::query()
            ->where('status', ConsultationStatus::Booked)
            ->whereBetween('starts_at', [$from, $to])
            ->when($ignoreConsultationId, fn ($query) => $query->whereKeyNot($ignoreConsultationId))
            ->pluck('starts_at')
            ->countBy(fn ($start): string => CarbonImmutable::parse($start)->format('Y-m-d'))
            ->all();
    }
}
