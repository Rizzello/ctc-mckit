<?php

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Room */
class RoomResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'sessionize_id' => $this->sessionize_id, 'name' => $this->name];
    }
}
