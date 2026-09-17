<?php

namespace Database\Factories;

use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginChallenge>
 */
class LoginChallengeFactory extends Factory
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
            'otp_hash' => hash('sha256', fake()->uuid()),
            'magic_token_hash' => hash('sha256', fake()->uuid()),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => null,
        ];
    }
}
