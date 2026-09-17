<?php

namespace App\Livewire\Admin;

use App\Enums\SyncRunStatus;
use App\Jobs\SyncSessionize;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class SessionizeStatus extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function queueSync(): void
    {
        Gate::authorize('sync-sessionize');

        if (! filled(config('sessionize.endpoint_url'))) {
            $this->dispatch('toast', type: 'error', message: 'Sessionize is not configured.');

            return;
        }

        $lock = Cache::lock('sessionize-sync-dispatch', 10);

        if (! $lock->get()) {
            $this->dispatch('toast', type: 'info', message: 'A synchronization is already being prepared.');

            return;
        }

        try {
            $isRunning = SyncRun::query()
                ->whereIn('status', [SyncRunStatus::Queued->value, SyncRunStatus::Running->value])
                ->exists();

            if ($isRunning) {
                $this->dispatch('toast', type: 'info', message: 'A synchronization is already in progress.');

                return;
            }

            $syncRun = SyncRun::query()->create(['status' => SyncRunStatus::Queued]);
            SyncSessionize::dispatch($syncRun->id);

            $this->dispatch('toast', type: 'success', message: 'Sessionize synchronization queued.');
        } finally {
            $lock->release();
        }
    }

    public function render(): View
    {
        $lastSyncRun = SyncRun::query()->latest('created_at')->first();

        return view('livewire.admin.sessionize-status', [
            'configured' => filled(config('sessionize.endpoint_url')),
            'lastSyncRun' => $lastSyncRun,
            'lastSuccessfulSync' => SyncRun::query()
                ->where('status', SyncRunStatus::Completed->value)
                ->latest('finished_at')
                ->first(),
            'syncInProgress' => $lastSyncRun instanceof SyncRun
                && in_array($lastSyncRun->status->value, [SyncRunStatus::Queued->value, SyncRunStatus::Running->value], true),
        ]);
    }
}
