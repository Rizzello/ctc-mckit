<?php

namespace App\Http\Resources;

use App\Models\ConferenceSession;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConferenceSession
 *
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 */
class ConferenceSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sessionize_id' => $this->sessionize_id,
            'title' => $this->title,
            'description' => $this->description,
            'room_id' => $this->room_id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'is_confirmed' => $this->is_confirmed,
            'is_service_session' => $this->is_service_session,
            'is_plenum_session' => $this->is_plenum_session,
            'categories' => $this->categories ?? [],
            'mc_description' => $this->mc_description,
            'mc_script' => $this->mc_script,
            'room' => new RoomResource($this->whenLoaded('room')),
            'speakers' => SpeakerResource::collection($this->whenLoaded('speakers')),
            'mcs' => AssignedMcResource::collection($this->whenLoaded('mcs')),
            'notes' => SessionNoteResource::collection($this->whenLoaded('notes')),
        ];
    }
}
