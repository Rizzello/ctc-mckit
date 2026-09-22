<?php

namespace App\Actions;

use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateSessionNote
{
    public function handle(User $actor, SessionNote $sessionNote, string $body): SessionNote
    {
        Gate::forUser($actor)->authorize('update', $sessionNote);

        $attributes = Validator::validate(['body' => $body], [
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $sessionNote->update($attributes);
        $sessionNote->conferenceSession()->touch();

        return $sessionNote;
    }
}
