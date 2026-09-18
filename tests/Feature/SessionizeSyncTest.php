<?php

namespace Tests\Feature;

use App\Enums\SyncRunStatus;
use App\Jobs\SyncSessionize;
use App\Livewire\Admin\SessionizeStatus;
use App\Models\ConferenceSession;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\UpdateConferenceSchedule;
use App\Sessionize\SessionizeClient;
use App\Sessionize\SessionizeNormalizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SessionizeSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_job_completes_a_sync_run_and_records_safe_statistics(): void
    {
        $this->fakeSuccessfulHttp();
        $syncRun = SyncRun::factory()->create();

        (new SyncSessionize($syncRun->id))->handle(
            app(SessionizeClient::class),
            app(SessionizeNormalizer::class),
            app(UpdateConferenceSchedule::class),
        );

        $syncRun = $syncRun->fresh();

        $this->assertSame(SyncRunStatus::Completed, $syncRun->status);
        $this->assertNotNull($syncRun->started_at);
        $this->assertNotNull($syncRun->finished_at);
        $this->assertSame(2, $syncRun->stats['rooms']);
        $this->assertSame(2, $syncRun->stats['speakers']);
        $this->assertSame(2, $syncRun->stats['sessions']);
        $this->assertSame(0, $syncRun->stats['removed']);
        $this->assertSame(2, ConferenceSession::query()->count());
    }

    public function test_failed_job_records_a_safe_failure_and_rethrows(): void
    {
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All', 'sessionize.retry' => 0]);
        Http::preventStrayRequests();
        Http::fake(['https://sessionize.com/api/v2/test-event/view/All' => Http::response([], 500)]);
        $syncRun = SyncRun::factory()->create();

        try {
            (new SyncSessionize($syncRun->id))->handle(
                app(SessionizeClient::class),
                app(SessionizeNormalizer::class),
                app(UpdateConferenceSchedule::class),
            );
            $this->fail('Expected the remote failure to be rethrown.');
        } catch (RequestException) {
        }

        $syncRun = $syncRun->fresh();

        $this->assertSame(SyncRunStatus::Failed, $syncRun->status);
        $this->assertSame('Synchronization failed. Check application logs for details.', $syncRun->error_message);
        $this->assertNotNull($syncRun->finished_at);
    }

    public function test_normalization_failure_marks_the_sync_run_as_failed(): void
    {
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All', 'sessionize.retry' => 0]);
        Http::preventStrayRequests();
        Http::fake([
            'https://sessionize.com/api/v2/test-event/view/All' => Http::response(['sessions' => []]),
            'https://sessionize.com/api/v2/test-event/view/GridSmart' => Http::response([]),
        ]);
        $syncRun = SyncRun::factory()->create();

        try {
            (new SyncSessionize($syncRun->id))->handle(
                app(SessionizeClient::class),
                app(SessionizeNormalizer::class),
                app(UpdateConferenceSchedule::class),
            );
            $this->fail('Expected malformed import data to fail normalization.');
        } catch (RuntimeException) {
        }

        $this->assertSame(SyncRunStatus::Failed, $syncRun->fresh()->status);
    }

    public function test_persistence_failure_marks_the_sync_run_as_failed_without_partial_schedule_changes(): void
    {
        $all = $this->fixture('all.json');
        $all['sessions'][0]['title'] = str_repeat('x', 256);
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All', 'sessionize.retry' => 0]);
        Http::preventStrayRequests();
        Http::fake([
            'https://sessionize.com/api/v2/test-event/view/All' => Http::response($all),
            'https://sessionize.com/api/v2/test-event/view/GridSmart' => Http::response($this->fixture('grid-smart.json')),
        ]);
        $syncRun = SyncRun::factory()->create();
        $existingSession = ConferenceSession::factory()->create([
            'title' => 'Existing schedule entry',
            'mc_description' => 'Local preparation.',
        ]);

        try {
            (new SyncSessionize($syncRun->id))->handle(
                app(SessionizeClient::class),
                app(SessionizeNormalizer::class),
                app(UpdateConferenceSchedule::class),
            );
            $this->fail('Expected invalid schedule persistence to fail.');
        } catch (QueryException) {
        }

        $this->assertSame(SyncRunStatus::Failed, $syncRun->fresh()->status);
        $this->assertDatabaseMissing('rooms', ['sessionize_id' => '10']);
        $this->assertSame('Existing schedule entry', $existingSession->fresh()->title);
        $this->assertSame('Local preparation.', $existingSession->fresh()->mc_description);
    }

    public function test_admin_ui_queues_one_synchronization_without_fetching_remote_data(): void
    {
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All']);
        Queue::fake([SyncSessionize::class]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test(SessionizeStatus::class)
            ->assertSee('Configured')
            ->assertDontSee('test-event')
            ->call('queueSync')
            ->assertDispatched('toast', type: 'success', message: 'Sessionize synchronization queued.');

        $syncRun = SyncRun::query()->sole();
        $this->assertSame(SyncRunStatus::Queued, $syncRun->status);
        Queue::assertPushed(SyncSessionize::class, fn (SyncSessionize $job): bool => $job->syncRunId === $syncRun->id);
    }

    public function test_normal_users_cannot_queue_a_synchronization_and_existing_runs_prevent_duplicates(): void
    {
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All']);
        Queue::fake([SyncSessionize::class]);
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get(route('admin.sessionize'))->assertForbidden();

        $admin = User::factory()->admin()->create();
        SyncRun::factory()->create(['status' => SyncRunStatus::Queued]);
        $this->actingAs($admin);

        Livewire::test(SessionizeStatus::class)
            ->assertSee('Synchronization in progress')
            ->call('queueSync')
            ->assertDispatched('toast', type: 'info', message: 'A synchronization is already in progress.');

        Queue::assertNothingPushed();
        $this->assertInstanceOf(ShouldBeUnique::class, new SyncSessionize(1));
        $this->assertSame('sessionize-sync', (new SyncSessionize(1))->uniqueId());
    }

    private function fakeSuccessfulHttp(): void
    {
        config(['sessionize.endpoint_url' => 'https://sessionize.com/api/v2/test-event/view/All', 'sessionize.retry' => 0]);
        Http::preventStrayRequests();
        Http::fake([
            'https://sessionize.com/api/v2/test-event/view/All' => Http::response($this->fixture('all.json')),
            'https://sessionize.com/api/v2/test-event/view/GridSmart' => Http::response($this->fixture('grid-smart.json')),
        ]);
    }

    /** @return array<mixed> */
    private function fixture(string $name): array
    {
        $contents = file_get_contents(base_path("tests/Fixtures/sessionize/{$name}"));

        $this->assertIsString($contents);

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }
}
