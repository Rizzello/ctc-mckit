<section @if ($syncInProgress) wire:poll.5s @endif class="max-w-xl space-y-5">
    <div>
        <p class="text-sm font-semibold text-sky-800">Administration</p>
        <h1 class="text-3xl font-bold">Sessionize</h1>
    </div>

    <div class="space-y-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <h2 class="font-bold">Configuration</h2>
            <p class="mt-1 text-sm text-slate-700">{{ $configured ? 'Configured' : 'Not configured' }}</p>
        </div>

        <div>
            <h2 class="font-bold">Latest sync</h2>
            @if ($lastSyncRun)
                <p class="mt-1 text-sm text-slate-700">
                    {{ ucfirst($lastSyncRun->status->value) }}
                    @if ($lastSyncRun->finished_at)
                        · {{ $lastSyncRun->finished_at->format('D M Y H:i') }}
                    @elseif ($lastSyncRun->started_at)
                        · Started {{ $lastSyncRun->started_at->format('D M Y H:i') }}
                    @endif
                </p>
                @if ($lastSyncRun->status === \App\Enums\SyncRunStatus::Failed)
                    <p class="mt-2 text-sm font-medium text-red-800">The last Sessionize synchronization failed. Check application logs for details.</p>
                @endif
            @else
                <p class="mt-1 text-sm text-slate-700">No synchronization has run yet.</p>
            @endif
        </div>

        @if ($lastSuccessfulSync?->finished_at)
            <div>
                <h2 class="font-bold">Last successful sync</h2>
                <p class="mt-1 text-sm text-slate-700">{{ $lastSuccessfulSync->finished_at->format('D M Y H:i') }}</p>
                @if ($lastSuccessfulSync->stats)
                    <p class="mt-1 text-sm text-slate-600">
                        {{ $lastSuccessfulSync->stats['sessions'] ?? 0 }} sessions · {{ $lastSuccessfulSync->stats['speakers'] ?? 0 }} speakers · {{ $lastSuccessfulSync->stats['rooms'] ?? 0 }} rooms
                    </p>
                @endif
            </div>
        @endif

        <button
            wire:click="queueSync"
            wire:loading.attr="disabled"
            @disabled(! $configured || $syncInProgress)
            type="button"
            class="min-h-11 rounded-md bg-sky-800 px-4 font-semibold text-white transition hover:bg-sky-900 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
        >
            @if ($syncInProgress)
                Synchronization in progress
            @elseif (! $configured)
                Sync unavailable
            @else
                Sync now
            @endif
        </button>
    </div>
</section>
