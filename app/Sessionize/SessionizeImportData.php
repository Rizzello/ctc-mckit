<?php

namespace App\Sessionize;

use Carbon\CarbonImmutable;

/**
 * @phpstan-type RoomData array{sessionize_id: string, name: string}
 * @phpstan-type SpeakerData array{sessionize_id: string, full_name: string, tagline: ?string, bio: ?string, photo: ?string, links: array<mixed>}
 * @phpstan-type SessionData array{sessionize_id: string, title: string, description: ?string, room_sessionize_id: ?string, starts_at: ?CarbonImmutable, ends_at: ?CarbonImmutable, status: ?string, is_confirmed: bool, is_service_session: bool, is_plenum_session: bool, categories: list<string>, speaker_ids: list<string>}
 */
final readonly class SessionizeImportData
{
    /**
     * @param  list<RoomData>  $rooms
     * @param  list<SpeakerData>  $speakers
     * @param  list<SessionData>  $sessions
     */
    public function __construct(
        public array $rooms,
        public array $speakers,
        public array $sessions,
    ) {}
}
