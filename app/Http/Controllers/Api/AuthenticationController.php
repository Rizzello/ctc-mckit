<?php

namespace App\Http\Controllers\Api;

use App\Actions\SignInUser;
use App\Actions\VerifyLoginOtp;
use App\Auth\SendLoginChallenge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLoginChallengeRequest;
use App\Http\Requests\Api\VerifyLoginOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticationController extends Controller
{
    public function challenge(StoreLoginChallengeRequest $request, SendLoginChallenge $sendLoginChallenge): JsonResponse
    {
        $sendLoginChallenge->handle(mb_strtolower(trim($request->string('email')->toString())), $request->session());

        return response()->json(['message' => 'If an account exists for this email, a sign-in message has been sent.']);
    }

    public function verifyOtp(VerifyLoginOtpRequest $request, VerifyLoginOtp $verifyLoginOtp, SignInUser $signInUser): JsonResponse
    {
        $challengeId = $request->session()->get('login_challenge_id');

        if (! is_int($challengeId) && ! ctype_digit((string) $challengeId)) {
            return response()->json(['message' => 'This sign-in code is invalid or has expired.'], 422);
        }

        $user = $verifyLoginOtp->handle((int) $challengeId, $request->string('otp')->toString());

        if (! $user instanceof User) {
            return response()->json(['message' => 'This sign-in code is invalid or has expired.'], 422);
        }

        $request->session()->forget('login_challenge_id');
        $signInUser->handle($user, $request->session());

        return (new UserResource($user))->response();
    }

    public function logout(): Response
    {
        $request = request();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
