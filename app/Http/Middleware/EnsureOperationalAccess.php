<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperationalAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        Gate::forUser($user)->authorize('view-operational-content');

        if (! $request->session()->has('private_cache_namespace')) {
            $request->session()->put('private_cache_namespace', Str::random(40));
        }

        return $next($request);
    }
}
