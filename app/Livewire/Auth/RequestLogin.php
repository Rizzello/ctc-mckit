<?php

namespace App\Livewire\Auth;

use App\Auth\SendLoginChallenge;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class RequestLogin extends Component
{
    public string $email = '';

    public function send(SendLoginChallenge $sendLoginChallenge): void
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

        $sendLoginChallenge->handle($email, session()->driver());

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
