<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

class SignInUser
{
    public function handle(User $user, Session $session): void
    {
        Auth::guard('web')->login($user);
        $session->regenerate();
    }
}
