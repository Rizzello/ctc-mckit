<?php

namespace App\Actions;

use App\Enums\SyncRunStatus;
use App\Jobs\SyncSessionize;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class QueueSessionizeSync
{
    public function handle(User $actor): SyncRun
    {
        Gate::forUser($actor)->authorize('sync-sessionize');

        if (! filled(config('sessionize.endpoint_url'))) {
            throw ValidationException::withMessages(['sessionize' => 'Sessionize is not configured.']);
        }

        $lock = Cache::lock('sessionize-sync-dispatch', 10);

        if (! $lock->get()) {
            throw ValidationException::withMessages(['sessionize' => 'A synchronization is already being prepared.']);
        }

        try {
            if (SyncRun::query()->whereIn('status', [SyncRunStatus::Queued->value, SyncRunStatus::Running->value])->exists()) {
                throw ValidationException::withMessages(['sessionize' => 'A synchronization is already in progress.']);
            }

            $syncRun = SyncRun::query()->create(['status' => SyncRunStatus::Queued]);
            SyncSessionize::dispatch($syncRun->id);

            return $syncRun;
        } finally {
            $lock->release();
        }
    }
}
