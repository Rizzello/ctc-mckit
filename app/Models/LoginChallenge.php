<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LoginChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $consumed_at
 */
#[Hidden(['otp_hash', 'magic_token_hash'])]
class LoginChallenge extends Model
{
    /** @use HasFactory<LoginChallengeFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
