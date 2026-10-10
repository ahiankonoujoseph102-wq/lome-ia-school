<?php

namespace Database\Factories;

use App\Enums\FormationCategory;
use App\Enums\FormationFormat;
use App\Enums\FormationKind;
use App\Models\Formation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Formation>
 */
class FormationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Formation '.rtrim(fake()->unique()->sentence(3), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'subtitle' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(5, 300) * 500,
            'category' => fake()->randomElement(FormationCategory::cases()),
            'formats' => [FormationFormat::Recorded],
            'sort_order' => 0,
            'is_featured' => false,
            'is_active' => true,
            'outcomes' => ['Premier objectif', 'Deuxième objectif'],
            'audience' => ['Débutants'],
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Offre d'accompagnement (pack) : ses formations incluses s'ajoutent avec includedFormations()->attach().
     */
    public function pack(): static
    {
        return $this->state(fn (): array => ['kind' => FormationKind::Pack]);
    }

    /**
     * Groupe de discussion seul (comme le Groupe VIP) : jamais visible ni vendu.
     */
    public function groupOnly(): static
    {
        return $this->state(fn (): array => ['kind' => FormationKind::Group, 'price' => 0, 'is_active' => false]);
    }
}
