<?php

namespace Tests\Feature\Consultations;

use App\Models\ConsultationBlock;
use App\Models\ConsultationHour;
use App\Models\ConsultationSettings;
use App\Models\Formation;
use App\Models\LiveSession;
use Carbon\CarbonImmutable;

class SlotsTest extends ConsultationTestCase
{
    public function test_slots_follow_weekly_hours_duration_and_pause(): void
    {
        // Mardi 6 octobre : 8 h – 20 h, 30 min + 15 min de pause → un créneau toutes les 45 minutes.
        $slots = $this->slotsOn('2026-10-06');

        $this->assertSame('08:00', $slots[0]);
        $this->assertSame('08:45', $slots[1]);
        $this->assertSame('19:15', end($slots));
        $this->assertCount(16, $slots);

        // Dimanche : fermé par défaut.
        $this->assertSame([], $this->slotsOn('2026-10-11'));

        // Horaires modifiés : dimanche 10 h – 12 h.
        ConsultationHour::query()->create(['weekday' => 7, 'starts_at' => '10:00', 'ends_at' => '12:00']);
        $this->assertSame(['10:00', '10:45', '11:30'], $this->slotsOn('2026-10-11')); // 11 h 30 se termine à 12 h 00
    }

    public function test_minimum_notice_before_a_booking(): void
    {
        // Il est 6 h 00 : délai de 3 h, premier créneau à partir de 9 h 00.
        $this->assertSame('09:30', $this->slotsOn('2026-10-05')[0]);

        ConsultationSettings::current()->update(['min_notice_hours' => 0]);
        $this->assertSame('08:00', $this->slotsOn('2026-10-05')[0]);
    }

    public function test_booking_is_possible_up_to_thirty_days_ahead(): void
    {
        $this->assertNotSame([], $this->slotsOn('2026-11-04')); // J+30 (mercredi)
        $this->assertSame([], $this->slotsOn('2026-11-05'));   // J+31

        ConsultationSettings::current()->update(['max_days_ahead' => 7]);
        $this->assertSame([], $this->slotsOn('2026-10-13'));
    }

    public function test_blocked_days_and_ranges(): void
    {
        ConsultationBlock::query()->create(['starts_at' => '2026-10-07 00:00', 'ends_at' => '2026-10-08 00:00', 'reason' => 'Congés']);
        ConsultationBlock::query()->create(['starts_at' => '2026-10-08 10:00', 'ends_at' => '2026-10-08 12:00']);

        $this->assertSame([], $this->slotsOn('2026-10-07'));

        $thursday = $this->slotsOn('2026-10-08');
        $this->assertContains('09:30', $thursday); // se termine à 10 h 00, au début du blocage
        $this->assertNotContains('10:15', $thursday);
        $this->assertNotContains('11:00', $thursday);
        $this->assertNotContains('11:45', $thursday);
        $this->assertContains('12:30', $thursday);
    }

    public function test_slots_overlapping_a_live_session_are_blocked_with_the_pause(): void
    {
        LiveSession::factory()->for(Formation::factory())->create(['starts_at' => CarbonImmutable::parse('2026-10-06 10:00'), 'duration_minutes' => 60]);

        $slots = $this->slotsOn('2026-10-06');

        $this->assertNotContains('09:30', $slots); // la pause de 15 min après toucherait la session
        $this->assertNotContains('10:15', $slots);
        $this->assertNotContains('11:00', $slots); // la pause avant toucherait la fin de la session
        $this->assertContains('11:45', $slots);
        $this->assertContains('08:45', $slots);

        // Une session annulée ne bloque plus rien.
        LiveSession::query()->update(['cancelled_at' => now()]);
        $this->assertContains('10:15', $this->slotsOn('2026-10-06'));
    }

    public function test_a_booked_slot_disappears_and_the_daily_maximum_applies(): void
    {
        $this->booked('2026-10-06 10:15');

        $this->assertNotContains('10:15', $this->slotsOn('2026-10-06'));
        $this->assertContains('09:30', $this->slotsOn('2026-10-06'));

        ConsultationSettings::current()->update(['max_per_day' => 1]);
        $this->assertSame([], $this->slotsOn('2026-10-06'));
        $this->assertNotSame([], $this->slotsOn('2026-10-07'));
    }

    public function test_public_page_lists_the_slots_with_benin_time(): void
    {
        $this->get(route('consultation'))
            ->assertOk()
            ->assertSee('Réservez votre créneau')
            ->assertSee('2026-10-06 08:00')
            ->assertSee('9 h 00 au Bénin (GMT+1)', false)
            ->assertSee('Heure de Lomé (GMT)');
    }
}
