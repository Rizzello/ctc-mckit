<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function handle(User $actor, User $user, string $name, string $email, bool $isAdmin, bool $enabled): User
    {
        Gate::forUser($actor)->authorize('update', $user);

        $attributes = Validator::validate(
            [
                'name' => $name,
                'email' => Str::lower(trim($email)),
                'is_admin' => $isAdmin,
                'enabled' => $enabled,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
                'is_admin' => ['required', 'boolean'],
                'enabled' => ['required', 'boolean'],
            ],
        );

        return DB::transaction(function () use ($user, $attributes): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->enabled && $lockedUser->is_admin && (! $attributes['enabled'] || ! $attributes['is_admin'])) {
                $enabledAdminCount = User::query()
                    ->where('enabled', true)
                    ->where('is_admin', true)
                    ->lockForUpdate()
                    ->count();

                if ($enabledAdminCount <= 1) {
                    throw ValidationException::withMessages([
                        'user' => 'The final enabled administrator cannot be disabled or demoted.',
                    ]);
                }
            }

            $lockedUser->update($attributes);

            return $lockedUser;
        });
    }
}
