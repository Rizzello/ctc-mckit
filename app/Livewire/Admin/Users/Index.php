<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Index extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function render(): View
    {
        return view('livewire.admin.users.index', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }
}
