<?php

use App\Http\Middleware\EnsureOperationalAccess;
use App\Models\ConferenceSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): RedirectResponse => redirect()->route('agenda'));

Route::view('/login', 'guest.login')->name('login');

Route::middleware(['auth', EnsureOperationalAccess::class])->group(function (): void {
    Route::view('/agenda', 'app.agenda')->name('agenda');
    Route::view('/live', 'app.live')->name('live');
    Route::redirect('/schedule', '/agenda')->name('schedule');
    Route::view('/sessions', 'app.sessions')->name('sessions.index');
    Route::get('/sessions/{conferenceSession}', fn (ConferenceSession $conferenceSession) => view('app.session-detail', compact('conferenceSession')))->name('sessions.show');

    Route::middleware('can:viewAny,'.User::class)->prefix('admin')->as('admin.')->group(function (): void {
        Route::view('/users', 'app.admin.users.index')->name('users.index');
        Route::view('/users/create', 'app.admin.users.create')->name('users.create');
        Route::get('/users/{user}/edit', fn (User $user) => view('app.admin.users.edit', compact('user')))->name('users.edit');
        Route::view('/sessionize', 'app.admin.sessionize')->name('sessionize');
    });
});

Route::post('/logout', function (Request $request): RedirectResponse {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');
