<?php

namespace App\Jobs;

use App\Enums\SyncRunStatus;
use App\Models\SyncRun;
use App\Services\UpdateConferenceSchedule;
use App\Sessionize\SessionizeClient;
use App\Sessionize\SessionizeNormalizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncSessionize implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public function __construct(public int $syncRunId) {}

    public function uniqueId(): string
    {
        return 'sessionize-sync';
    }

    public function handle(
        SessionizeClient $client,
        SessionizeNormalizer $normalizer,
        UpdateConferenceSchedule $updater,
    ): void {
        $syncRun = SyncRun::query()->find($this->syncRunId);

        if (! $syncRun instanceof SyncRun) {
            throw new RuntimeException('The synchronization run could not be found.');
        }

        $syncRun->forceFill([
            'status' => SyncRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ]);
        $syncRun->save();

        try {
            $payload = $client->fetch();
            $data = $normalizer->normalize($payload);
            $stats = $updater->execute($data);

            $syncRun->forceFill([
                'status' => SyncRunStatus::Completed,
                'finished_at' => now(),
                'stats' => $stats,
            ]);
            $syncRun->save();

            Log::info('Sessionize synchronization completed.', [
                'sync_run_id' => $syncRun->id,
                'stats' => $stats,
            ]);
        } catch (Throwable $exception) {
            $syncRun->forceFill([
                'status' => SyncRunStatus::Failed,
                'finished_at' => now(),
                'error_message' => 'Synchronization failed. Check application logs for details.',
            ]);
            $syncRun->save();

            Log::warning('Sessionize synchronization failed.', [
                'sync_run_id' => $syncRun->id,
                'exception' => $exception::class,
            ]);

            throw new RuntimeException('Sessionize synchronization failed.');
        }
    }
}
