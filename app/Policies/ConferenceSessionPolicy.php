<?php

namespace App\Policies;

use App\Enums\SessionizePresenceStatus;
use App\Models\ConferenceSession;
use App\Models\User;

class ConferenceSessionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->enabled;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ConferenceSession $conferenceSession): bool
    {
        return $user->enabled
            && ($conferenceSession->getRawOriginal('sessionize_status') !== SessionizePresenceStatus::Removed->value || $user->is_admin);
    }

    /**
     * Determine whether the user can create models.
     */
    public function updateMcContent(User $user, ConferenceSession $conferenceSession): bool
    {
        return $user->enabled;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function assignMc(User $user, ConferenceSession $conferenceSession, User $mc): bool
    {
        return $user->enabled && $user->is_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function unassignMc(User $user, ConferenceSession $conferenceSession, User $mc): bool
    {
        return $user->enabled && $user->is_admin;
    }
}
