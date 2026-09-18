<?php

namespace Tests\Feature;

use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\SessionNote;
use App\Models\Speaker;
use App\Models\User;
use App\Services\UpdateConferenceSchedule;
use App\Sessionize\SessionizeImportData;
use App\Sessionize\SessionizeNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class UpdateConferenceScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_synchronizes_imported_fields_and_preserves_local_operational_content(): void
    {
        $oldRoom = Room::factory()->create();
        $session = ConferenceSession::factory()->create([
            'sessionize_id' => 'talk-1',
            'room_id' => $oldRoom->id,
            'mc_description' => 'Local introduction.',
            'mc_script' => 'Local script.',
        ]);
        $mc = User::factory()->create();
        $session->mcs()->attach($mc);
        $note = SessionNote::factory()->create(['conference_session_id' => $session->id]);

        $stats = app(UpdateConferenceSchedule::class)->execute($this->data());
        $session = $session->fresh(['room', 'speakers', 'mcs', 'notes']);

        $this->assertSame(['rooms' => 2, 'speakers' => 2, 'sessions' => 2, 'removed' => 0], $stats);
        $this->assertSame('Reliable systems', $session->title);
        $this->assertSame('Main Room', $session->room?->name);
        $this->assertSame('Local introduction.', $session->mc_description);
        $this->assertSame('Local script.', $session->mc_script);
        $this->assertSame([$mc->id], $session->mcs->modelKeys());
        $this->assertSame([$note->id], $session->notes->modelKeys());
        $this->assertSame(['speaker-a', 'speaker-b'], $session->speakers->pluck('sessionize_id')->all());
        $this->assertSame([0, 1], $session->speakers->pluck('pivot.sort_order')->all());
    }

    public function test_updates_room_moves_and_is_idempotent(): void
    {
        app(UpdateConferenceSchedule::class)->execute($this->data());

        $payload = $this->payload();
        $payload['all']['rooms'][0]['name'] = 'Main Hall';
        $payload['grid'][0]['rooms'][0]['name'] = 'Main Hall';
        $payload['all']['speakers'][0]['fullName'] = 'Amina Patel';
        $payload['grid'][0]['rooms'][0]['sessions'][0]['roomId'] = 20;
        $movedData = app(SessionizeNormalizer::class)->normalize($payload);

        app(UpdateConferenceSchedule::class)->execute($movedData);
        app(UpdateConferenceSchedule::class)->execute($movedData);

        $session = ConferenceSession::query()->where('sessionize_id', 'talk-1')->sole();

        $this->assertSame(Room::query()->where('sessionize_id', '20')->sole()->id, $session->room_id);
        $this->assertSame('Main Hall', Room::query()->where('sessionize_id', '10')->sole()->name);
        $this->assertSame('Amina Patel', Speaker::query()->where('sessionize_id', 'speaker-a')->sole()->name);
        $this->assertSame(2, Room::query()->count());
        $this->assertSame(2, Speaker::query()->count());
        $this->assertSame(2, ConferenceSession::query()->count());
        $this->assertSame(2, $session->speakers()->count());
    }

    public function test_marks_missing_sessions_as_removed_and_reactivates_them_when_they_return(): void
    {
        $updater = app(UpdateConferenceSchedule::class);
        $updater->execute($this->data());

        $updater->execute(new SessionizeImportData([], [], [$this->data()->sessions[0]]));

        $this->assertTrue(ConferenceSession::query()->where('sessionize_id', 'service-1')->sole()->sessionize_status->value === 'removed');
        $this->assertCount(0, ConferenceSession::active()->where('sessionize_id', 'service-1')->get());

        $updater->execute($this->data());

        $this->assertTrue(ConferenceSession::query()->where('sessionize_id', 'talk-1')->sole()->sessionize_status->value === 'active');
    }

    public function test_rejects_an_empty_import_when_an_active_schedule_exists_without_mutating_it(): void
    {
        $room = Room::factory()->create(['name' => 'Existing room']);
        $session = ConferenceSession::factory()->create([
            'room_id' => $room->id,
            'title' => 'Existing schedule entry',
            'mc_description' => 'Local preparation.',
        ]);

        try {
            app(UpdateConferenceSchedule::class)->execute(new SessionizeImportData([], [], []));
            $this->fail('Expected the empty import to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sessionize import contains no sessions while active sessions already exist.', $exception->getMessage());
        }

        $session = $session->fresh();

        $this->assertSame('Existing schedule entry', $session->title);
        $this->assertSame('Local preparation.', $session->mc_description);
        $this->assertSame('active', $session->sessionize_status->value);
        $this->assertSame('Existing room', $room->fresh()->name);
    }

    public function test_empty_import_is_a_safe_no_op_without_an_active_schedule(): void
    {
        $stats = app(UpdateConferenceSchedule::class)->execute(new SessionizeImportData([], [], []));

        $this->assertSame(['rooms' => 0, 'speakers' => 0, 'sessions' => 0, 'removed' => 0], $stats);
        $this->assertSame(0, ConferenceSession::query()->count());
        $this->assertSame(0, Room::query()->count());
        $this->assertSame(0, Speaker::query()->count());
    }

    public function test_rolls_back_the_entire_schedule_when_persistence_fails(): void
    {
        $existingSession = ConferenceSession::factory()->create(['title' => 'Existing schedule entry']);
        $data = new SessionizeImportData(
            [['sessionize_id' => 'room-failure', 'name' => 'New room']],
            [],
            [[
                'sessionize_id' => 'session-failure',
                'title' => str_repeat('x', 256),
                'description' => null,
                'room_sessionize_id' => 'room-failure',
                'starts_at' => null,
                'ends_at' => null,
                'status' => null,
                'is_confirmed' => false,
                'is_service_session' => false,
                'is_plenum_session' => false,
                'categories' => [],
                'speaker_ids' => [],
            ]],
        );

        try {
            app(UpdateConferenceSchedule::class)->execute($data);
            $this->fail('Expected the invalid schedule persistence to fail.');
        } catch (QueryException) {
        }

        $this->assertDatabaseMissing('rooms', ['sessionize_id' => 'room-failure']);
        $this->assertSame('Existing schedule entry', $existingSession->fresh()->title);
    }

    private function data(): SessionizeImportData
    {
        return app(SessionizeNormalizer::class)->normalize($this->payload());
    }

    /** @return array{all: array<string, mixed>, grid: list<array<string, mixed>>} */
    private function payload(): array
    {
        return [
            'all' => $this->fixture('all.json'),
            'grid' => $this->fixture('grid-smart.json'),
        ];
    }

    /** @return array<mixed> */
    private function fixture(string $name): array
    {
        $contents = file_get_contents(base_path("tests/Fixtures/sessionize/{$name}"));

        $this->assertIsString($contents);

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
