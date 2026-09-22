<?php

namespace App\Actions;

use App\Models\ConferenceSession;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignMcToConferenceSession
{
    public function handle(User $actor, ConferenceSession $conferenceSession, User $mc): void
    {
        Gate::forUser($actor)->authorize('assignMc', [$conferenceSession, $mc]);

        if (! $mc->enabled) {
            throw ValidationException::withMessages([
                'user' => 'Disabled users cannot be assigned to conference sessions.',
            ]);
        }

        $conferenceSession->mcs()->syncWithoutDetaching([$mc->id]);
        $conferenceSession->touch();
    }
}
