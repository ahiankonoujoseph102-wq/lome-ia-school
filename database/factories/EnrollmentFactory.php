<?php

namespace Database\Factories;

use App\Enums\EnrollmentSource;
use App\Models\Enrollment;
use App\Models\Formation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'formation_id' => Formation::factory(),
            'source' => EnrollmentSource::Purchase,
            'granted_at' => now(),
        ];
    }
}
