<?php

namespace Database\Factories;

use App\Models\ConferenceSession;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionNote>
 */
class SessionNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conference_session_id' => ConferenceSession::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
