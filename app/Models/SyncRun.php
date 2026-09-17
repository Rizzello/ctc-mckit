<?php

namespace App\Models;

use App\Enums\SyncRunStatus;
use Database\Factories\SyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['status', 'started_at', 'finished_at', 'error_message', 'stats'])]
class SyncRun extends Model
{
    /** @use HasFactory<SyncRunFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SyncRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'stats' => 'array',
        ];
    }
}
