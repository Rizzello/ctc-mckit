<?php

namespace Database\Factories;

use App\Enums\SessionizePresenceStatus;
use App\Models\ConferenceSession;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConferenceSession>
 */
class ConferenceSessionFactory extends Factory
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
            'room_id' => Room::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'starts_at' => fake()->dateTimeBetween('+1 day', '+2 days'),
            'ends_at' => fake()->dateTimeBetween('+2 days', '+3 days'),
            'status' => 'Accepted',
            'is_confirmed' => true,
            'is_service_session' => false,
            'is_plenum_session' => false,
            'categories' => ['General'],
            'sessionize_status' => SessionizePresenceStatus::Active,
            'mc_description' => null,
            'mc_script' => null,
        ];
    }

    public function serviceSession(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_service_session' => true,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sessionize_status' => SessionizePresenceStatus::Removed,
        ]);
    }
}
