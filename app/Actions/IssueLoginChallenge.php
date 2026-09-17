<?php

namespace App\Actions;

use App\Auth\IssuedLoginChallenge;
use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IssueLoginChallenge
{
    public function handle(User $user): ?IssuedLoginChallenge
    {
        return DB::transaction(function () use ($user): ?IssuedLoginChallenge {
            $lockedUser = User::query()->lockForUpdate()->find($user->id);

            if (! $lockedUser instanceof User || ! $lockedUser->enabled) {
                return null;
            }

            $now = now();
            $expiresAt = $now->copy()->addMinutes((int) config('auth.login.challenge_lifetime'));
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $magicToken = bin2hex(random_bytes(32));

            LoginChallenge::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => $now]);

            $challenge = new LoginChallenge;
            $challenge->forceFill([
                'user_id' => $lockedUser->id,
                'otp_hash' => Hash::make($otp),
                'magic_token_hash' => hash('sha256', $magicToken),
                'attempts' => 0,
                'expires_at' => $expiresAt,
            ]);
            $challenge->save();

            return new IssuedLoginChallenge($challenge, $otp, $magicToken, $expiresAt);
        });
    }
}
