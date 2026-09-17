<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'is_admin', 'enabled'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** @return BelongsToMany<ConferenceSession, $this> */
    public function assignedConferenceSessions(): BelongsToMany
    {
        return $this->belongsToMany(ConferenceSession::class, 'conference_session_mc')
            ->withTimestamps();
    }

    /** @return HasMany<SessionNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(SessionNote::class);
    }

    /** @return HasMany<LoginChallenge, $this> */
    public function loginChallenges(): HasMany
    {
        return $this->hasMany(LoginChallenge::class);
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
