<?php

namespace App\Actions;

use App\Models\LoginChallenge;
use App\Models\User;

class CompleteLoginChallenge
{
    public function handle(LoginChallenge $challenge): ?User
    {
        $user = User::query()->lockForUpdate()->find($challenge->user_id);

        if (! $user instanceof User || ! $user->enabled) {
            return null;
        }

        $now = now();

        $challenge->forceFill(['consumed_at' => $now]);
        $challenge->save();

        LoginChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('id', '!=', $challenge->id)
            ->update(['consumed_at' => $now]);

        $user->forceFill(['last_login_at' => $now]);
        $user->save();

        return $user;
    }
}
