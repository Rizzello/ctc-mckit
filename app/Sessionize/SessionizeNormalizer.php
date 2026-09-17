<?php

namespace App\Sessionize;

use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

class SessionizeNormalizer
{
    /** @param array<mixed> $payload */
    public function normalize(array $payload): SessionizeImportData
    {
        $all = $payload['all'] ?? null;
        $grid = $payload['grid'] ?? null;

        if (! is_array($all) || array_is_list($all) || ! is_array($grid) || ! array_is_list($grid)
            || ! $this->isRecordList($all['rooms'] ?? null)
            || ! $this->isRecordList($all['speakers'] ?? null)
            || ! $this->isRecordList($all['sessions'] ?? null)
            || ! $this->isRecordList($grid)) {
            throw new RuntimeException('Sessionize returned an invalid response.');
        }

        $rooms = $this->rooms($this->records($all['rooms'] ?? null));
        $speakers = $this->speakers($this->records($all['speakers'] ?? null));
        $allSessions = $this->sessions($this->records($all['sessions'] ?? null));
        [$gridSessions, $gridRooms] = $this->grid($grid);

        foreach ($gridRooms as $room) {
            $rooms[$room['sessionize_id']] = $room;
        }

        foreach ($gridSessions as $sessionizeId => $gridSession) {
            $allSessions[$sessionizeId] = isset($allSessions[$sessionizeId])
                ? $this->mergePlanning($allSessions[$sessionizeId], $gridSession)
                : $gridSession;
        }

        return new SessionizeImportData(
            array_values($rooms),
            array_values($speakers),
            array_values($allSessions),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, array{sessionize_id: string, name: string}>
     */
    private function rooms(array $records): array
    {
        $rooms = [];

        foreach ($records as $record) {
            $id = $this->externalId($record['id'] ?? null, 'room');
            $name = $this->nullableString($record['name'] ?? null) ?? "Room {$id}";
            $room = ['sessionize_id' => $id, 'name' => $name];

            if (isset($rooms[$id]) && $rooms[$id] !== $room) {
                throw new RuntimeException('Sessionize returned duplicate room data.');
            }

            $rooms[$id] = $room;
        }

        return $rooms;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, array{sessionize_id: string, full_name: string, tagline: ?string, bio: ?string, photo: ?string, links: array<mixed>}>
     */
    private function speakers(array $records): array
    {
        $speakers = [];

        foreach ($records as $record) {
            $id = $this->externalId($record['id'] ?? null, 'speaker');
            $speaker = [
                'sessionize_id' => $id,
                'full_name' => $this->nullableString($record['fullName'] ?? null) ?? "Speaker {$id}",
                'tagline' => $this->nullableString($record['tagLine'] ?? null),
                'bio' => $this->nullableString($record['bio'] ?? null),
                'photo' => $this->nullableString($record['profilePicture'] ?? null),
                'links' => is_array($record['links'] ?? null) ? $record['links'] : [],
            ];

            if (isset($speakers[$id]) && $speakers[$id] !== $speaker) {
                throw new RuntimeException('Sessionize returned duplicate speaker data.');
            }

            $speakers[$id] = $speaker;
        }

        return $speakers;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, array{sessionize_id: string, title: string, description: ?string, room_sessionize_id: ?string, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, status: ?string, is_confirmed: bool, is_service_session: bool, is_plenum_session: bool, categories: array<mixed>, speaker_ids: list<string>}>
     */
    private function sessions(array $records): array
    {
        $sessions = [];

        foreach ($records as $record) {
            $session = $this->session($record);
            $id = $session['sessionize_id'];

            if (isset($sessions[$id]) && $sessions[$id] !== $session) {
                throw new RuntimeException('Sessionize returned duplicate session data.');
            }

            $sessions[$id] = $session;
        }

        return $sessions;
    }

    /**
     * @param  list<array<string, mixed>>  $grid
     * @return array{0: array<string, array{sessionize_id: string, title: string, description: ?string, room_sessionize_id: ?string, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, status: ?string, is_confirmed: bool, is_service_session: bool, is_plenum_session: bool, categories: array<mixed>, speaker_ids: list<string>}>, 1: array<string, array{sessionize_id: string, name: string}>}
     */
    private function grid(array $grid): array
    {
        $sessions = [];
        $rooms = [];

        foreach ($grid as $day) {
            if (! $this->isRecordList($day['rooms'] ?? null)) {
                throw new RuntimeException('Sessionize returned an invalid response.');
            }

            foreach ($this->records($day['rooms'] ?? null) as $room) {
                $roomId = $this->externalId($room['id'] ?? null, 'room');
                $normalizedRoom = [
                    'sessionize_id' => $roomId,
                    'name' => $this->nullableString($room['name'] ?? null) ?? "Room {$roomId}",
                ];

                if (isset($rooms[$roomId]) && $rooms[$roomId] !== $normalizedRoom) {
                    throw new RuntimeException('Sessionize returned duplicate room data.');
                }

                $rooms[$roomId] = $normalizedRoom;

                if (! $this->isRecordList($room['sessions'] ?? null)) {
                    throw new RuntimeException('Sessionize returned an invalid response.');
                }

                foreach ($this->records($room['sessions'] ?? null) as $record) {
                    if (! isset($record['roomId'])) {
                        $record['roomId'] = $roomId;
                    }

                    $session = $this->session($record);
                    $id = $session['sessionize_id'];

                    if (isset($sessions[$id]) && $sessions[$id] !== $session) {
                        throw new RuntimeException('Sessionize returned duplicate schedule data.');
                    }

                    $sessions[$id] = $session;
                }
            }
        }

        return [$sessions, $rooms];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{sessionize_id: string, title: string, description: ?string, room_sessionize_id: ?string, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, status: ?string, is_confirmed: bool, is_service_session: bool, is_plenum_session: bool, categories: array<mixed>, speaker_ids: list<string>}
     */
    private function session(array $record): array
    {
        return [
            'sessionize_id' => $this->externalId($record['id'] ?? null, 'session'),
            'title' => $this->nullableString($record['title'] ?? null) ?? 'Untitled session',
            'description' => $this->nullableString($record['description'] ?? null),
            'room_sessionize_id' => isset($record['roomId']) ? $this->externalId($record['roomId'], 'room') : null,
            'starts_at' => $this->timestamp($record['startsAt'] ?? null),
            'ends_at' => $this->timestamp($record['endsAt'] ?? null),
            'status' => $this->nullableString($record['status'] ?? null),
            'is_confirmed' => $this->boolean($record['isConfirmed'] ?? false),
            'is_service_session' => $this->boolean($record['isServiceSession'] ?? false),
            'is_plenum_session' => $this->boolean($record['isPlenumSession'] ?? false),
            'categories' => $this->categories($record),
            'speaker_ids' => $this->speakerIds($record['speakers'] ?? []),
        ];
    }

    /**
     * @param  array{sessionize_id: string, title: string, description: ?string, room_sessionize_id: ?string, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, status: ?string, is_confirmed: bool, is_service_session: bool, is_plenum_session: bool, categories: array<mixed>, speaker_ids: list<string>}  $base
     * @param  array{sessionize_id: string, title: string, description: ?string, room_sessionize_id: ?string, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, status: ?string, is_confirmed: bool, is_service_session: bool, is_plenum_session: bool, categories: array<mixed>, speaker_ids: list<string>}  $planning
     * @return array{sessionize_id: string, title: string, description: ?string, room_sessionize_id: ?string, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, status: ?string, is_confirmed: bool, is_service_session: bool, is_plenum_session: bool, categories: array<mixed>, speaker_ids: list<string>}
     */
    private function mergePlanning(array $base, array $planning): array
    {
        $base['room_sessionize_id'] = $planning['room_sessionize_id'];
        $base['starts_at'] = $planning['starts_at'];
        $base['ends_at'] = $planning['ends_at'];
        $base['is_service_session'] = $planning['is_service_session'];
        $base['is_plenum_session'] = $planning['is_plenum_session'];

        return $base;
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $records = [];

        foreach ($value as $record) {
            if (! is_array($record) || array_is_list($record)) {
                throw new RuntimeException('Sessionize returned an invalid response.');
            }

            /** @var array<string, mixed> $record */
            $records[] = $record;
        }

        return $records;
    }

    private function isRecordList(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $record) {
            if (! is_array($record) || array_is_list($record)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<mixed>
     */
    private function categories(array $record): array
    {
        $categories = $record['categoryItems'] ?? $record['categories'] ?? [];

        return is_array($categories) ? $categories : [];
    }

    /** @return list<string> */
    private function speakerIds(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $speaker) {
            $value = is_array($speaker) ? $speaker['id'] ?? null : $speaker;

            if ($value === null) {
                continue;
            }

            $ids[] = $this->externalId($value, 'speaker');
        }

        return array_values(array_unique($ids));
    }

    private function externalId(mixed $value, string $resource): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new RuntimeException("Sessionize returned an invalid {$resource} identifier.");
        }

        $id = trim((string) $value);

        if ($id === '') {
            throw new RuntimeException("Sessionize returned an invalid {$resource} identifier.");
        }

        return $id;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new RuntimeException('Sessionize returned an invalid schedule timestamp.');
        }

        try {
            $timezone = config('app.timezone', 'UTC');
            $timestamp = preg_match('/(?:Z|[+-]\\d{2}:?\\d{2})$/', $value) === 1
                ? CarbonImmutable::parse($value)
                : CarbonImmutable::parse($value, is_string($timezone) ? $timezone : 'UTC');

            return $timestamp->utc();
        } catch (Throwable) {
            throw new RuntimeException('Sessionize returned an invalid schedule timestamp.');
        }
    }
}
