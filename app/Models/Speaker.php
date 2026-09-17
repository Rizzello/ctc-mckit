<?php

namespace App\Models;

use Database\Factories\SpeakerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['sessionize_id', 'name', 'tagline', 'bio', 'photo_url', 'links'])]
class Speaker extends Model
{
    /** @use HasFactory<SpeakerFactory> */
    use HasFactory;

    /** @return BelongsToMany<ConferenceSession, $this> */
    public function conferenceSessions(): BelongsToMany
    {
        return $this->belongsToMany(ConferenceSession::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'links' => 'array',
        ];
    }
}
