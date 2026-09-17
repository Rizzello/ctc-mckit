<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /** @return HasMany<ConferenceSession, $this> */
    public function conferenceSessions(): HasMany
    {
        return $this->hasMany(ConferenceSession::class);
    }
}
