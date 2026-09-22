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
use Illuminate\Support\Str;

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
        RateLimiter::for('api-login-challenge', function (Request $request): Limit {
            $email = Str::lower(trim($request->string('email')->toString()));
            $ip = $request->ip() ?? 'unknown';

            return Limit::perMinutes(10, 5)->by(hash('sha256', $email.'|'.$ip));
        });
        RateLimiter::for('api-login-ip', fn (Request $request): Limit => Limit::perMinutes(10, 20)->by(hash('sha256', $request->ip() ?? 'unknown')));
        RateLimiter::for('api-login-otp', fn (Request $request): Limit => Limit::perMinutes(10, 10)->by(hash('sha256', (string) $request->session()->get('login_challenge_id').'|'.($request->ip() ?? 'unknown'))));
    }
}
