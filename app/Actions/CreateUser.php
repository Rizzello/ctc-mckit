<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateUser
{
    public function handle(User $actor, string $name, string $email, bool $isAdmin, bool $enabled): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        $attributes = Validator::validate(
            [
                'name' => $name,
                'email' => Str::lower(trim($email)),
                'is_admin' => $isAdmin,
                'enabled' => $enabled,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
                'is_admin' => ['required', 'boolean'],
                'enabled' => ['required', 'boolean'],
            ],
        );

        return User::query()->create($attributes);
    }
}
