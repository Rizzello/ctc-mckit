<?php

namespace Database\Factories;

use App\Enums\SyncRunStatus;
use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => SyncRunStatus::Queued,
            'started_at' => null,
            'finished_at' => null,
            'error_message' => null,
            'stats' => null,
        ];
    }
}
