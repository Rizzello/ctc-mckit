<?php

namespace App\Actions;

use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyLoginOtp
{
    public function __construct(private CompleteLoginChallenge $completeLoginChallenge) {}

    public function handle(int $challengeId, string $otp): ?User
    {
        return DB::transaction(function () use ($challengeId, $otp): ?User {
            $challenge = LoginChallenge::query()->lockForUpdate()->find($challengeId);

            if (! $challenge instanceof LoginChallenge || $this->isUnavailable($challenge)) {
                return null;
            }

            if (! Hash::check($otp, $challenge->otp_hash)) {
                $attempts = $challenge->attempts + 1;
                $attributes = ['attempts' => $attempts];

                if ($attempts >= (int) config('auth.login.max_otp_attempts')) {
                    $attributes['consumed_at'] = now();
                }

                $challenge->forceFill($attributes);
                $challenge->save();

                return null;
            }

            return $this->completeLoginChallenge->handle($challenge);
        });
    }

    private function isUnavailable(LoginChallenge $challenge): bool
    {
        return $challenge->consumed_at !== null
            || $challenge->expires_at->isPast()
            || $challenge->attempts >= (int) config('auth.login.max_otp_attempts');
    }
}
