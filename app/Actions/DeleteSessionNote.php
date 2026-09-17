<?php

namespace App\Actions;

use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteSessionNote
{
    public function handle(User $actor, SessionNote $sessionNote): void
    {
        Gate::forUser($actor)->authorize('delete', $sessionNote);

        $sessionNote->delete();
    }
}
