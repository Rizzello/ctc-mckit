<?php

namespace App\Http\Controllers\Api;

use App\Actions\QueueSessionizeSync;
use App\Enums\SyncRunStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyncRunResource;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionizeStatusController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $lastSyncRun = SyncRun::query()->latest('created_at')->first();
        $lastSuccessfulSync = SyncRun::query()->where('status', SyncRunStatus::Completed->value)->latest('finished_at')->first();

        return response()->json([
            'configured' => filled(config('sessionize.endpoint_url')),
            'last_sync' => $lastSyncRun instanceof SyncRun ? (new SyncRunResource($lastSyncRun))->resolve($request) : null,
            'last_successful_sync' => $lastSuccessfulSync instanceof SyncRun ? (new SyncRunResource($lastSuccessfulSync))->resolve($request) : null,
        ]);
    }

    public function store(Request $request, QueueSessionizeSync $queue): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new SyncRunResource($queue->handle($user)))->response()->setStatusCode(201);
    }
}
