<?php

namespace App\Livewire\Auth;

use App\Actions\IssueLoginChallenge;
use App\Mail\LoginChallengeMail;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class RequestLogin extends Component
{
    public string $email = '';

    public function send(IssueLoginChallenge $issueLoginChallenge): void
    {
        $this->email = Str::lower(trim($this->email));
        $validated = $this->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);
        $email = $validated['email'];

        if ($this->isRateLimited($email)) {
            $this->dispatch('toast', type: 'error', message: 'Too many sign-in requests. Please try again later.');

            return;
        }

        $user = User::query()->where('email', $email)->where('enabled', true)->first();

        if ($user instanceof User) {
            $issuedChallenge = $issueLoginChallenge->handle($user);

            if ($issuedChallenge !== null) {
                $magicUrl = URL::temporarySignedRoute(
                    'auth.magic',
                    $issuedChallenge->expiresAt,
                    ['challenge' => $issuedChallenge->challenge->id, 'token' => $issuedChallenge->magicToken],
                );

                try {
                    Mail::to($user)->send(new LoginChallengeMail(
                        magicUrl: $magicUrl,
                        otp: $issuedChallenge->otp,
                        expiresAt: $issuedChallenge->expiresAt,
                    ));

                    session()->put('login_challenge_id', $issuedChallenge->challenge->id);
                } catch (Throwable) {
                    $issuedChallenge->challenge->forceFill(['consumed_at' => now()])->save();
                    session()->forget('login_challenge_id');
                }
            } else {
                session()->forget('login_challenge_id');
            }
        } else {
            session()->forget('login_challenge_id');
        }

        session()->flash('success', 'If an account exists for this email, a sign-in message has been sent.');
        $this->redirectRoute('login.code', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.request-login');
    }

    private function isRateLimited(string $email): bool
    {
        $ip = request()->ip() ?? 'unknown';
        $emailKey = 'login-email:'.hash('sha256', $email.'|'.$ip);
        $ipKey = 'login-ip:'.hash('sha256', $ip);

        if (RateLimiter::tooManyAttempts($emailKey, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            return true;
        }

        RateLimiter::hit($emailKey, 600);
        RateLimiter::hit($ipKey, 600);

        return false;
    }
}
