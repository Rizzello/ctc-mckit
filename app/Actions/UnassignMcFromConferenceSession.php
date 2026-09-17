<?php

namespace App\Actions;

use App\Models\ConferenceSession;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UnassignMcFromConferenceSession
{
    public function handle(User $actor, ConferenceSession $conferenceSession, User $mc): void
    {
        Gate::forUser($actor)->authorize('unassignMc', [$conferenceSession, $mc]);

        $conferenceSession->mcs()->detach($mc);
    }
}
