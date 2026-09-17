<?php

namespace App\Livewire\Auth;

use App\Actions\SignInUser;
use App\Actions\VerifyLoginOtp;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class VerifyLoginCode extends Component
{
    public string $otp = '';

    public function verify(VerifyLoginOtp $verifyLoginOtp, SignInUser $signInUser): void
    {
        $validated = $this->validate([
            'otp' => ['required', 'digits:6'],
        ]);
        $challengeId = session('login_challenge_id');

        if (! is_int($challengeId) && ! ctype_digit((string) $challengeId)) {
            $this->dispatch('toast', type: 'error', message: 'This sign-in code is invalid or has expired.');

            return;
        }

        $rateLimitKey = 'login-otp:'.hash('sha256', $challengeId.'|'.(request()->ip() ?? 'unknown'));

        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->dispatch('toast', type: 'error', message: 'Too many verification attempts. Please request a new sign-in email.');

            return;
        }

        RateLimiter::hit($rateLimitKey, 600);
        $user = $verifyLoginOtp->handle((int) $challengeId, $validated['otp']);

        if (! $user instanceof User) {
            $this->dispatch('toast', type: 'error', message: 'This sign-in code is invalid or has expired.');

            return;
        }

        session()->forget('login_challenge_id');
        $signInUser->handle($user, session()->driver());
        $this->redirectIntended(route('agenda'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.verify-login-code');
    }
}
