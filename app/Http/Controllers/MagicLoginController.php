<?php

namespace App\Http\Controllers;

use App\Actions\ConsumeMagicLoginLink;
use App\Actions\SignInUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MagicLoginController extends Controller
{
    public function __invoke(
        Request $request,
        string $challenge,
        string $token,
        ConsumeMagicLoginLink $consumeMagicLoginLink,
        SignInUser $signInUser,
    ): RedirectResponse {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login', ['error' => 'magic-link-invalid']);
        }

        $user = $consumeMagicLoginLink->handle($challenge, $token);

        if (! $user instanceof User) {
            return redirect()->route('login', ['error' => 'magic-link-invalid']);
        }

        $signInUser->handle($user, $request->session());

        return redirect()->intended(route('agenda'));
    }
}
