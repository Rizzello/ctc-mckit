<?php

namespace App\Livewire\Admin;

use App\Actions\QueueSessionizeSync;
use App\Enums\SyncRunStatus;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SessionizeStatus extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function queueSync(QueueSessionizeSync $queueSessionizeSync): void
    {
        try {
            $queueSessionizeSync->handle($this->currentUser());
            $this->dispatch('toast', type: 'success', message: 'Sessionize synchronization queued.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['sessionize'][0];
            $this->dispatch('toast', type: str_starts_with($message, 'A synchronization is') ? 'info' : 'error', message: $message);
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

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
