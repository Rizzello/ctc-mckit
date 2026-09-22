<?php

namespace App\Http\Controllers\Api;

use App\Actions\AddSessionNote;
use App\Actions\DeleteSessionNote;
use App\Actions\UpdateSessionNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSessionNoteRequest;
use App\Http\Requests\Api\UpdateSessionNoteRequest;
use App\Http\Resources\SessionNoteResource;
use App\Models\ConferenceSession;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SessionNoteController extends Controller
{
    public function store(StoreSessionNoteRequest $request, ConferenceSession $conferenceSession, AddSessionNote $add): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $note = $add->handle($user, $conferenceSession, $request->string('body')->toString());

        return (new SessionNoteResource($note))->response()->setStatusCode(201);
    }

    public function update(UpdateSessionNoteRequest $request, SessionNote $sessionNote, UpdateSessionNote $update): SessionNoteResource
    {
        /** @var User $user */
        $user = $request->user();
        $note = $update->handle($user, $sessionNote, $request->string('body')->toString());

        return new SessionNoteResource($note);
    }

    public function destroy(SessionNote $sessionNote, DeleteSessionNote $delete): Response
    {
        /** @var User $user */
        $user = request()->user();
        $this->authorize('delete', $sessionNote);
        $delete->handle($user, $sessionNote);

        return response()->noContent();
    }
}
