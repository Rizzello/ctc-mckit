<?php

namespace App\Policies;

use App\Models\SessionNote;
use App\Models\User;

class SessionNotePolicy
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
    public function view(User $user, SessionNote $sessionNote): bool
    {
        return $user->enabled;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->enabled && $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SessionNote $sessionNote): bool
    {
        return $user->enabled && $user->is_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SessionNote $sessionNote): bool
    {
        return $user->enabled && $user->is_admin;
    }
}
