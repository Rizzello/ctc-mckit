<?php

use App\Http\Controllers\MagicLoginController;
use App\Http\Controllers\SpaController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', SpaController::class)->name('login');
    Route::get('/login/code', SpaController::class)->name('login.code');
    Route::get('/login/magic/{challenge}/{token}', MagicLoginController::class)
        ->middleware('throttle:magic-login')
        ->name('auth.magic');
});

Route::get('/csrf-cookie', fn () => response()->noContent())->name('csrf.cookie');

Route::get('/', SpaController::class)->name('home');
Route::get('/agenda', SpaController::class)->name('agenda');
Route::get('/live', SpaController::class)->name('live');
Route::get('/sessions', SpaController::class)->name('sessions.index');
Route::get('/sessions/{session}', SpaController::class)
    ->whereNumber('session')
    ->name('sessions.show');
Route::get('/admin/users', SpaController::class)->name('admin.users.index');
Route::get('/admin/sync', SpaController::class)->name('admin.sync');

Route::post('/logout', function (Request $request): RedirectResponse {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');
