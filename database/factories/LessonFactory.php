<?php

namespace Database\Factories;

use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_module_id' => CourseModule::factory(),
            'title' => 'Leçon '.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'video_provider' => 'youtube',
            'video_id' => Str::random(11),
            'duration_seconds' => null,
            'is_preview' => false,
            'is_published' => true,
            'position' => 0,
        ];
    }

    public function preview(): static
    {
        return $this->state(fn (array $attributes) => ['is_preview' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['is_published' => false]);
    }
}
