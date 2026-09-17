<?php

namespace App\Livewire\Admin;

use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SessionizeStatus extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function render(): View
    {
        return view('livewire.admin.sessionize-status', [
            'configured' => filled(config('services.sessionize.endpoint')),
            'lastSyncRun' => SyncRun::query()->latest('created_at')->first(),
        ]);
    }
}
