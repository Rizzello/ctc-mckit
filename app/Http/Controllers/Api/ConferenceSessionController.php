<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateConferenceSessionMcContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateMcContentRequest;
use App\Http\Resources\ConferenceSessionResource;
use App\Models\ConferenceSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConferenceSessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();
        $query = ConferenceSession::query()->ordered()->with(['room', 'speakers', 'mcs', 'notes']);

        if ($user->is_admin && $request->boolean('show_removed')) {
            $query->whereNotNull('sessionize_status');
        } else {
            $query->active();
        }

        return ConferenceSessionResource::collection($query->get());
    }

    public function show(Request $request, ConferenceSession $conferenceSession): ConferenceSessionResource
    {
        $this->authorize('view', $conferenceSession);

        return new ConferenceSessionResource($conferenceSession->load(['room', 'speakers', 'mcs', 'notes']));
    }

    public function updateMcContent(UpdateMcContentRequest $request, ConferenceSession $conferenceSession, UpdateConferenceSessionMcContent $update): ConferenceSessionResource
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validated();
        $update->handle(
            $user,
            $conferenceSession,
            $attributes['mc_description'] ?? $conferenceSession->mc_description,
            $attributes['mc_script'] ?? $conferenceSession->mc_script,
        );

        return new ConferenceSessionResource($conferenceSession->fresh(['room', 'speakers', 'mcs', 'notes']));
    }
}
