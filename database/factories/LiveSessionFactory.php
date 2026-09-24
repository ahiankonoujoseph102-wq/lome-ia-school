<?php

namespace Database\Factories;

use App\Enums\SessionFormat;
use App\Enums\SessionPriceType;
use App\Models\Formation;
use App\Models\LiveSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiveSession>
 */
class LiveSessionFactory extends Factory
{
    /**
     * Session en direct dans 3 jours (à 10 h, heure de Lomé).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'formation_id' => Formation::factory(),
            'format' => SessionFormat::Online,
            'title' => null,
            'description' => null,
            'starts_at' => now()->addDays(3)->setTime(10, 0),
            'duration_minutes' => 90,
            'meet_url' => 'https://meet.google.com/abc-defg-hij',
            'location' => null,
            'capacity' => null,
            'price_type' => SessionPriceType::Included,
            'supplement_amount' => null,
        ];
    }

    /**
     * Session en présentiel à Lomé.
     */
    public function onsite(int $capacity = 20, ?int $supplement = null): static
    {
        return $this->state(fn (): array => [
            'format' => SessionFormat::Onsite,
            'meet_url' => null,
            'location' => 'Adidogomé, Lycée moderne 2, Lomé',
            'capacity' => $capacity,
            'price_type' => $supplement ? SessionPriceType::Supplement : SessionPriceType::Included,
            'supplement_amount' => $supplement,
        ]);
    }
}
