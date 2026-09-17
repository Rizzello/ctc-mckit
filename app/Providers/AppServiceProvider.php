<?php

namespace App\Providers;

use App\Models\ConferenceSession;
use App\Models\SessionNote;
use App\Models\User;
use App\Policies\ConferenceSessionPolicy;
use App\Policies\SessionNotePolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(ConferenceSession::class, ConferenceSessionPolicy::class);
        Gate::policy(SessionNote::class, SessionNotePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Gate::define('view-operational-content', fn (User $user): bool => $user->enabled);
        Gate::define('sync-sessionize', fn (User $user): bool => $user->enabled && $user->is_admin);

        RateLimiter::for('magic-login', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
    }
}
