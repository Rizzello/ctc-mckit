<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConferenceSessionResource;
use App\Http\Resources\RoomResource;
use App\Http\Resources\SpeakerResource;
use App\Http\Resources\UserResource;
use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SnapshotController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sessions = ConferenceSession::query()
            ->active()
            ->ordered()
            ->with(['room', 'speakers', 'mcs', 'notes'])
            ->get();

        $version = implode('|', [
            $sessions->map(fn (ConferenceSession $session): string => $session->id.':'.$session->updated_at?->getTimestamp())->implode('|'),
            (string) Room::query()->max('updated_at'),
            (string) Speaker::query()->max('updated_at'),
            (string) DB::table('session_notes')->max('updated_at'),
            (string) DB::table('conference_session_mc')->max('updated_at'),
        ]);

        return response()->json([
            'version' => hash('sha256', $version),
            'generated_at' => now()->toIso8601String(),
            'current_user' => (new UserResource($user))->resolve($request),
            'rooms' => RoomResource::collection(Room::query()->whereHas('conferenceSessions', fn ($query) => $query->active())->orderBy('name')->get())->resolve($request),
            'speakers' => SpeakerResource::collection(Speaker::query()->whereHas('conferenceSessions', fn ($query) => $query->active())->orderBy('name')->get())->resolve($request),
            'sessions' => ConferenceSessionResource::collection($sessions)->resolve($request),
        ]);
    }
}
