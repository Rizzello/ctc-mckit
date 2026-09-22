<?php

namespace App\Actions;

use App\Models\ConferenceSession;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AddSessionNote
{
    public function handle(User $actor, ConferenceSession $conferenceSession, string $body): SessionNote
    {
        Gate::forUser($actor)->authorize('create', SessionNote::class);

        $attributes = Validator::validate(['body' => $body], [
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $sessionNote = new SessionNote($attributes);
        $sessionNote->conferenceSession()->associate($conferenceSession);
        $sessionNote->author()->associate($actor);
        $sessionNote->save();
        $conferenceSession->touch();

        return $sessionNote;
    }
}
