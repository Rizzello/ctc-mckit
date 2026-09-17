<?php

namespace App\Models;

use Database\Factories\SessionNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['body'])]
class SessionNote extends Model
{
    /** @use HasFactory<SessionNoteFactory> */
    use HasFactory;

    /** @return BelongsTo<ConferenceSession, $this> */
    public function conferenceSession(): BelongsTo
    {
        return $this->belongsTo(ConferenceSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
