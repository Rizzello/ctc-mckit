<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateUser;
use App\Actions\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUserRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(User::query()->orderBy('name')->get());
    }

    public function store(StoreUserRequest $request, CreateUser $create): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $attributes = $request->validated();
        $user = $create->handle($actor, $attributes['name'], $attributes['email'], $attributes['is_admin'], $attributes['enabled']);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $update): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();
        $attributes = $request->validated();
        $updatedUser = $update->handle($actor, $user, $attributes['name'], $attributes['email'], $attributes['is_admin'], $attributes['enabled']);

        return new UserResource($updatedUser);
    }
}
