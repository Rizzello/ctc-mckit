<?php

namespace App\Auth;

use App\Models\LoginChallenge;
use Carbon\CarbonInterface;

final readonly class IssuedLoginChallenge
{
    public function __construct(
        public LoginChallenge $challenge,
        public string $otp,
        public string $magicToken,
        public CarbonInterface $expiresAt,
    ) {}
}
