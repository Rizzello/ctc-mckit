<?php

namespace App\Http\Resources;

use App\Models\SessionNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SessionNote */
class SessionNoteResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
