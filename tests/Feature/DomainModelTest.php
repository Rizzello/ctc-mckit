<?php

namespace Tests\Feature;

use App\Enums\SessionizePresenceStatus;
use App\Enums\SyncRunStatus;
use App\Models\ConferenceSession;
use App\Models\LoginChallenge;
use App\Models\Room;
use App\Models\SessionNote;
use App\Models\Speaker;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sessionize_identifiers_are_unique(): void
    {
        Room::factory()->create(['sessionize_id' => 'room-1']);

        $this->expectException(QueryException::class);

        Room::factory()->create(['sessionize_id' => 'room-1']);
    }

    public function test_conference_session_and_speaker_sessionize_identifiers_are_unique(): void
    {
        ConferenceSession::factory()->create(['sessionize_id' => 'session-1']);

        try {
            ConferenceSession::factory()->create(['sessionize_id' => 'session-1']);
            $this->fail('Duplicate conference session identifiers must be rejected.');
        } catch (QueryException) {
        }

        Speaker::factory()->create(['sessionize_id' => 'speaker-1']);

        $this->expectException(QueryException::class);

        Speaker::factory()->create(['sessionize_id' => 'speaker-1']);
    }

    public function test_conference_sessions_keep_speakers_in_pivot_order(): void
    {
        $conferenceSession = ConferenceSession::factory()->create();
        $firstSpeaker = Speaker::factory()->create();
        $secondSpeaker = Speaker::factory()->create();

        $conferenceSession->speakers()->attach([
            $firstSpeaker->id => ['sort_order' => 2],
            $secondSpeaker->id => ['sort_order' => 1],
        ]);

        $this->assertSame(
            [$secondSpeaker->id, $firstSpeaker->id],
            $conferenceSession->fresh()->speakers->pluck('id')->all(),
        );
    }

    public function test_conference_sessions_can_have_multiple_mcs_and_reject_duplicate_assignments(): void
    {
        $conferenceSession = ConferenceSession::factory()->create();
        $firstMc = User::factory()->create();
        $secondMc = User::factory()->create();

        $conferenceSession->mcs()->attach([$firstMc->id, $secondMc->id]);

        $this->assertCount(2, $conferenceSession->fresh()->mcs);

        $this->expectException(QueryException::class);

        $conferenceSession->mcs()->attach($firstMc);
    }

    public function test_notes_belong_to_their_session_and_author_and_prevent_author_deletion(): void
    {
        $note = SessionNote::factory()->create();

        $this->assertSame($note->conference_session_id, $note->conferenceSession->id);
        $this->assertSame($note->user_id, $note->author->id);

        $this->expectException(QueryException::class);

        $note->author->delete();
    }

    public function test_imported_and_local_session_fields_coexist_with_casts_and_nullable_rooms(): void
    {
        $activeConferenceSession = ConferenceSession::factory()->create();
        $conferenceSession = ConferenceSession::factory()->removed()->create([
            'room_id' => null,
            'categories' => ['Track A', 'Security'],
            'mc_description' => 'Welcome the audience.',
            'mc_script' => 'Introduce the speaker.',
        ]);

        $this->assertNull($conferenceSession->room);
        $this->assertSame(SessionizePresenceStatus::Active, $activeConferenceSession->sessionize_status);
        $this->assertSame(SessionizePresenceStatus::Removed, $conferenceSession->sessionize_status);
        $this->assertSame(['Track A', 'Security'], $conferenceSession->categories);
        $this->assertSame('Welcome the audience.', $conferenceSession->mc_description);
        $this->assertSame('Introduce the speaker.', $conferenceSession->mc_script);
        $this->assertSame([$activeConferenceSession->id], ConferenceSession::active()->pluck('id')->all());
    }

    public function test_speaker_links_and_sync_statistics_round_trip_as_arrays_and_statuses_cast_to_enums(): void
    {
        $links = [['title' => 'Website', 'url' => 'https://example.test']];
        $stats = ['sessions' => 12, 'speakers' => 28];
        $speaker = Speaker::factory()->create(['links' => $links]);
        $syncRun = SyncRun::factory()->create([
            'status' => SyncRunStatus::Completed,
            'stats' => $stats,
        ]);

        $this->assertSame('Website', $speaker->fresh()->links[0]['title']);
        $this->assertSame('https://example.test', $speaker->fresh()->links[0]['url']);
        $this->assertSame(SyncRunStatus::Completed, $syncRun->fresh()->status);
        $this->assertSame($stats, $syncRun->fresh()->stats);
    }

    public function test_users_normalize_email_addresses_and_cast_roles_and_enablement(): void
    {
        $user = User::factory()->admin()->disabled()->create([
            'email' => '  MC@Example.TEST  ',
        ]);

        $this->assertSame('mc@example.test', $user->email);
        $this->assertTrue($user->is_admin);
        $this->assertFalse($user->enabled);
    }

    public function test_factories_create_a_valid_schedule_graph_and_hide_challenge_hashes(): void
    {
        $conferenceSession = ConferenceSession::factory()->serviceSession()->create();
        $speaker = Speaker::factory()->create();
        $mc = User::factory()->create();
        $note = SessionNote::factory()->create([
            'conference_session_id' => $conferenceSession,
            'user_id' => $mc,
        ]);
        $challenge = LoginChallenge::factory()->create(['user_id' => $mc]);

        $conferenceSession->speakers()->attach($speaker, ['sort_order' => 0]);
        $conferenceSession->mcs()->attach($mc);

        $this->assertTrue($conferenceSession->is_service_session);
        $this->assertSame($speaker->id, $conferenceSession->speakers->sole()->id);
        $this->assertSame($mc->id, $conferenceSession->mcs->sole()->id);
        $this->assertSame($note->id, $conferenceSession->notes->sole()->id);
        $this->assertArrayNotHasKey('otp_hash', $challenge->toArray());
        $this->assertArrayNotHasKey('magic_token_hash', $challenge->toArray());
    }

    public function test_framework_http_sessions_and_conference_sessions_use_distinct_tables(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasTable('conference_sessions'));
    }
}
