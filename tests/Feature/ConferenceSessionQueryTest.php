<?php

namespace Tests\Feature;

use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConferenceSessionQueryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_schedule_excludes_removed_sessions_and_is_chronological(): void
    {
        $late = ConferenceSession::factory()->create(['starts_at' => CarbonImmutable::parse('2027-10-14 11:00:00 UTC')]);
        $early = ConferenceSession::factory()->create(['starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC')]);
        ConferenceSession::factory()->removed()->create(['starts_at' => CarbonImmutable::parse('2027-10-14 08:00:00 UTC')]);

        $this->assertSame([$early->id, $late->id], ConferenceSession::activeSchedule()->pluck('id')->all());
    }

    public function test_session_timestamps_are_read_as_utc_before_local_display_conversion(): void
    {
        $session = ConferenceSession::factory()->create([
            'starts_at' => CarbonImmutable::parse('2027-10-14 07:00:00 UTC'),
        ]);

        $freshSession = $session->fresh();

        self::assertSame('2027-10-14T07:00:00+00:00', $freshSession?->starts_at?->toIso8601String());
    }

    public function test_room_schedule_returns_active_sessions_for_the_requested_room(): void
    {
        $room = Room::factory()->create();
        $otherRoom = Room::factory()->create();
        $roomSession = ConferenceSession::factory()->create(['room_id' => $room, 'starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC')]);
        ConferenceSession::factory()->removed()->create(['room_id' => $room]);
        ConferenceSession::factory()->create(['room_id' => $otherRoom]);

        $this->assertSame([$roomSession->id], ConferenceSession::roomSchedule($room->id)->pluck('id')->all());
    }

    public function test_assigned_sessions_returns_sessions_for_the_requested_user(): void
    {
        $user = User::factory()->create();
        $assignedSession = ConferenceSession::factory()->create();
        $unassignedSession = ConferenceSession::factory()->create();
        $assignedSession->mcs()->attach($user);

        $this->assertSame([$assignedSession->id], ConferenceSession::assignedTo($user)->pluck('id')->all());
        $this->assertNotSame($unassignedSession->id, ConferenceSession::assignedTo($user)->sole()->id);
    }

    public function test_removed_sessions_are_available_through_a_dedicated_query(): void
    {
        $removedSession = ConferenceSession::factory()->removed()->create();
        ConferenceSession::factory()->create();

        $this->assertSame([$removedSession->id], ConferenceSession::removed()->pluck('id')->all());
    }

    public function test_search_matches_session_titles_and_speaker_names(): void
    {
        $titleMatch = ConferenceSession::factory()->create(['title' => 'Reliable PHP systems']);
        $speakerMatch = ConferenceSession::factory()->create(['title' => 'A different session']);
        $speaker = Speaker::factory()->create(['name' => 'Amina Khan']);
        $speakerMatch->speakers()->attach($speaker, ['sort_order' => 0]);

        $this->assertSame([$titleMatch->id], ConferenceSession::search('Reliable PHP')->pluck('id')->all());
        $this->assertSame([$speakerMatch->id], ConferenceSession::search('Amina')->pluck('id')->all());
    }

    public function test_current_session_includes_its_start_and_excludes_its_end(): void
    {
        $conferenceSession = ConferenceSession::factory()->create([
            'starts_at' => CarbonImmutable::parse('2027-10-14 09:00:00 UTC'),
            'ends_at' => CarbonImmutable::parse('2027-10-14 10:00:00 UTC'),
        ]);
        ConferenceSession::factory()->create(['starts_at' => null, 'ends_at' => null]);

        $this->assertSame([$conferenceSession->id], ConferenceSession::currentAt(CarbonImmutable::parse('2027-10-14 09:00:00 UTC'))->pluck('id')->all());
        $this->assertCount(0, ConferenceSession::currentAt(CarbonImmutable::parse('2027-10-14 10:00:00 UTC'))->get());
    }
}
