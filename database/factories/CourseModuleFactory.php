<?php

namespace Database\Factories;

use App\Models\CourseModule;
use App\Models\Formation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseModule>
 */
class CourseModuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'formation_id' => Formation::factory(),
            'title' => 'Module '.fake()->unique()->numberBetween(1, 9999),
            'position' => 0,
        ];
    }
}
