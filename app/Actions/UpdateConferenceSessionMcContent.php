<?php

namespace App\Actions;

use App\Models\ConferenceSession;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateConferenceSessionMcContent
{
    public function handle(User $actor, ConferenceSession $conferenceSession, ?string $mcDescription, ?string $mcScript): ConferenceSession
    {
        Gate::forUser($actor)->authorize('updateMcContent', $conferenceSession);

        $attributes = Validator::validate(
            ['mc_description' => $mcDescription, 'mc_script' => $mcScript],
            [
                'mc_description' => ['nullable', 'string', 'max:10000'],
                'mc_script' => ['nullable', 'string', 'max:50000'],
            ],
        );

        $conferenceSession->update($attributes);

        return $conferenceSession;
    }
}
