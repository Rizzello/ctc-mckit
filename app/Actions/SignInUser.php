<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SignInUser
{
    public function handle(User $user, Session $session): void
    {
        Auth::guard('web')->login($user);
        $session->regenerate();
        $session->put('private_cache_namespace', Str::random(40));
    }
}
