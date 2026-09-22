<?php

namespace App\Http\Controllers\Api;

use App\Actions\AssignMcToConferenceSession;
use App\Actions\UnassignMcFromConferenceSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignMcRequest;
use App\Http\Resources\ConferenceSessionResource;
use App\Models\ConferenceSession;
use App\Models\User;
use Illuminate\Http\Response;

class ConferenceSessionMcController extends Controller
{
    public function store(AssignMcRequest $request, ConferenceSession $conferenceSession, AssignMcToConferenceSession $assign): ConferenceSessionResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $mc = User::query()->findOrFail($request->integer('user_id'));
        $assign->handle($actor, $conferenceSession, $mc);

        return new ConferenceSessionResource($conferenceSession->fresh(['room', 'speakers', 'mcs', 'notes']));
    }

    public function destroy(ConferenceSession $conferenceSession, User $user, UnassignMcFromConferenceSession $unassign): Response
    {
        /** @var User $actor */
        $actor = request()->user();
        $unassign->handle($actor, $conferenceSession, $user);

        return response()->noContent();
    }
}
