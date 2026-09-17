<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DisableUser
{
    public function handle(User $actor, User $user): User
    {
        Gate::forUser($actor)->authorize('disable', $user);

        return DB::transaction(function () use ($user): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->enabled && $lockedUser->is_admin) {
                $enabledAdminCount = User::query()
                    ->where('enabled', true)
                    ->where('is_admin', true)
                    ->lockForUpdate()
                    ->count();

                if ($enabledAdminCount <= 1) {
                    throw ValidationException::withMessages([
                        'user' => 'The final enabled administrator cannot be disabled.',
                    ]);
                }
            }

            $lockedUser->update(['enabled' => false]);

            return $lockedUser;
        });
    }
}
