<?php

namespace App\Services;

use App\Enums\SessionizePresenceStatus;
use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\Speaker;
use App\Sessionize\SessionizeImportData;
use Illuminate\Support\Facades\DB;

class UpdateConferenceSchedule
{
    /**
     * @return array{rooms: int, speakers: int, sessions: int, removed: int}
     */
    public function execute(SessionizeImportData $data): array
    {
        return DB::transaction(function () use ($data): array {
            $rooms = $this->syncRooms($data);
            $speakers = $this->syncSpeakers($data);
            $this->syncSessions($data, $rooms, $speakers);

            $sessionizeIds = array_map(
                fn (array $session): string => $session['sessionize_id'],
                $data->sessions,
            );

            $removed = ConferenceSession::query()
                ->whereNotIn('sessionize_id', $sessionizeIds)
                ->where('sessionize_status', SessionizePresenceStatus::Active->value)
                ->update(['sessionize_status' => SessionizePresenceStatus::Removed->value]);

            return [
                'rooms' => count($rooms),
                'speakers' => count($speakers),
                'sessions' => count($data->sessions),
                'removed' => $removed,
            ];
        });
    }

    /**
     * @return array<string, Room>
     */
    private function syncRooms(SessionizeImportData $data): array
    {
        $rooms = [];

        foreach ($data->rooms as $room) {
            $rooms[$room['sessionize_id']] = Room::query()->updateOrCreate(
                ['sessionize_id' => $room['sessionize_id']],
                ['name' => $room['name']],
            );
        }

        return $rooms;
    }

    /**
     * @return array<string, Speaker>
     */
    private function syncSpeakers(SessionizeImportData $data): array
    {
        $speakers = [];

        foreach ($data->speakers as $speaker) {
            $speakers[$speaker['sessionize_id']] = Speaker::query()->updateOrCreate(
                ['sessionize_id' => $speaker['sessionize_id']],
                [
                    'name' => $speaker['full_name'],
                    'tagline' => $speaker['tagline'],
                    'bio' => $speaker['bio'],
                    'photo_url' => $speaker['photo'],
                    'links' => $speaker['links'],
                ],
            );
        }

        return $speakers;
    }

    /**
     * @param  array<string, Room>  $rooms
     * @param  array<string, Speaker>  $speakers
     */
    private function syncSessions(SessionizeImportData $data, array $rooms, array $speakers): void
    {
        foreach ($data->sessions as $session) {
            $conferenceSession = ConferenceSession::query()->firstOrNew([
                'sessionize_id' => $session['sessionize_id'],
            ]);
            $room = $session['room_sessionize_id'] === null ? null : ($rooms[$session['room_sessionize_id']] ?? null);

            $conferenceSession->forceFill([
                'room_id' => $room?->id,
                'title' => $session['title'],
                'description' => $session['description'],
                'starts_at' => $session['starts_at'],
                'ends_at' => $session['ends_at'],
                'status' => $session['status'],
                'is_confirmed' => $session['is_confirmed'],
                'is_service_session' => $session['is_service_session'],
                'is_plenum_session' => $session['is_plenum_session'],
                'categories' => $session['categories'],
                'sessionize_status' => SessionizePresenceStatus::Active,
            ]);
            $conferenceSession->save();

            $speakerPivots = [];

            foreach ($session['speaker_ids'] as $sortOrder => $speakerId) {
                if (isset($speakers[$speakerId])) {
                    $speakerPivots[$speakers[$speakerId]->id] = ['sort_order' => $sortOrder];
                }
            }

            $conferenceSession->speakers()->sync($speakerPivots);
        }
    }
}
