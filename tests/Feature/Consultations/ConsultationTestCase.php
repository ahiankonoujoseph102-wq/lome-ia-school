<?php

namespace Tests\Feature\Consultations;

use App\Consultations\SlotFinder;
use App\Enums\UserRole;
use App\Models\Consultation;
use App\Models\ConsultationSettings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class ConsultationTestCase extends TestCase
{
    use RefreshDatabase;

    protected const MEET = 'https://meet.google.com/abc-defg-hij';

    private int $visitors = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // Limites de fréquence désactivées, sauf dans le test anti-spam qui les réactive.
        $this->withoutMiddleware(ThrottleRequests::class);
        // Lundi 5 octobre 2026, 6 h 00 à Lomé.
        $this->travelTo(CarbonImmutable::parse('2026-10-05 06:00'));
        ConsultationSettings::current()->update(['meet_url' => self::MEET]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function form(string $slot, array $overrides = []): array
    {
        $this->visitors++;

        return array_merge([
            'name' => 'Afi Dossou',
            'phone_country' => '+228',
            'phone' => '90 12 34 '.str_pad((string) (10 + $this->visitors), 2, '0', STR_PAD_LEFT),
            'email' => 'afi'.$this->visitors.'@example.com',
            'topic' => 'Je veux apprendre à créer des visuels pour mon commerce.',
            'formation_id' => '',
            'starts_at' => $slot,
            'website' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function book(string $slot, array $overrides = []): TestResponse
    {
        return $this->post(route('consultation.store'), $this->form($slot, $overrides));
    }

    /**
     * Réserve un créneau et renvoie le rendez-vous et son jeton (lien de gestion).
     *
     * @param  array<string, mixed>  $overrides
     * @return array{0: Consultation, 1: string}
     */
    protected function booked(string $slot, array $overrides = []): array
    {
        $response = $this->book($slot, $overrides)->assertSessionHasNoErrors();
        $token = basename((string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));

        return [Consultation::query()->latest('id')->firstOrFail(), $token];
    }

    /**
     * Créneaux libres d'un jour, au format « H:i ».
     *
     * @return list<string>
     */
    protected function slotsOn(string $date, ?int $ignore = null): array
    {
        $days = (new SlotFinder)->available($ignore);

        return array_map(fn (CarbonImmutable $slot): string => $slot->format('H:i'), $days[$date] ?? []);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }
}
