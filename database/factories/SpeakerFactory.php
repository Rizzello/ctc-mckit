<?php

namespace Database\Factories;

use App\Models\Speaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Speaker>
 */
class SpeakerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sessionize_id' => fake()->unique()->uuid(),
            'name' => fake()->name(),
            'tagline' => fake()->optional()->jobTitle(),
            'bio' => fake()->optional()->paragraph(),
            'photo_url' => fake()->optional()->url(),
            'links' => [['title' => 'Website', 'url' => fake()->url()]],
        ];
    }
}
