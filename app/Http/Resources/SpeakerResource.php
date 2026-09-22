<?php

namespace App\Http\Resources;

use App\Models\Speaker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Speaker */
class SpeakerResource extends JsonResource
{
    /** @return array<string, array<mixed>|int|string|null> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sessionize_id' => $this->sessionize_id,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'bio' => $this->bio,
            'photo_url' => $this->photo_url,
            'links' => $this->links ?? [],
        ];
    }
}
