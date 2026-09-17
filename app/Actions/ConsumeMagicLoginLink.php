<?php

namespace App\Actions;

use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConsumeMagicLoginLink
{
    public function __construct(private CompleteLoginChallenge $completeLoginChallenge) {}

    public function handle(string $challengeId, string $token): ?User
    {
        if (! ctype_digit($challengeId)) {
            return null;
        }

        return DB::transaction(function () use ($challengeId, $token): ?User {
            $challenge = LoginChallenge::query()->lockForUpdate()->find((int) $challengeId);

            if (! $challenge instanceof LoginChallenge
                || $challenge->consumed_at !== null
                || $challenge->expires_at->isPast()
                || ! hash_equals($challenge->magic_token_hash, hash('sha256', $token))) {
                return null;
            }

            return $this->completeLoginChallenge->handle($challenge);
        });
    }
}
