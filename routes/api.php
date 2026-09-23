<?php

use App\Http\Controllers\Api\AuthenticationController;
use App\Http\Controllers\Api\ConferenceSessionController;
use App\Http\Controllers\Api\ConferenceSessionMcController;
use App\Http\Controllers\Api\CurrentUserController;
use App\Http\Controllers\Api\SessionizeStatusController;
use App\Http\Controllers\Api\SessionNoteController;
use App\Http\Controllers\Api\SnapshotController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureOperationalAccess;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('v1')->group(function (): void {
    Route::post('/auth/challenge', [AuthenticationController::class, 'challenge'])
        ->middleware(['guest', 'throttle:api-login-challenge', 'throttle:api-login-ip'])
        ->name('api.v1.auth.challenge');
    Route::post('/auth/verify-otp', [AuthenticationController::class, 'verifyOtp'])
        ->middleware(['guest', 'throttle:api-login-otp'])
        ->name('api.v1.auth.verify-otp');

    Route::post('/logout', [AuthenticationController::class, 'logout'])
        ->middleware('auth')
        ->name('api.v1.logout');

    Route::middleware(['auth', EnsureOperationalAccess::class])->group(function (): void {
        Route::get('/me', CurrentUserController::class)->name('api.v1.me');
        Route::get('/snapshot', SnapshotController::class)->name('api.v1.snapshot');
        Route::get('/sessions', [ConferenceSessionController::class, 'index'])->name('api.v1.sessions.index');
        Route::get('/sessions/{conferenceSession}', [ConferenceSessionController::class, 'show'])->name('api.v1.sessions.show');
        Route::patch('/sessions/{conferenceSession}/mc-content', [ConferenceSessionController::class, 'updateMcContent'])->name('api.v1.sessions.mc-content');
        Route::post('/sessions/{conferenceSession}/notes', [SessionNoteController::class, 'store'])->name('api.v1.sessions.notes.store');
        Route::post('/sessions/{conferenceSession}/mcs', [ConferenceSessionMcController::class, 'store'])->name('api.v1.sessions.mcs.store');
        Route::delete('/sessions/{conferenceSession}/mcs/{user}', [ConferenceSessionMcController::class, 'destroy'])->name('api.v1.sessions.mcs.destroy');
        Route::patch('/notes/{sessionNote}', [SessionNoteController::class, 'update'])->name('api.v1.notes.update');
        Route::delete('/notes/{sessionNote}', [SessionNoteController::class, 'destroy'])->name('api.v1.notes.destroy');
        Route::get('/users', [UserController::class, 'index'])->name('api.v1.users.index');
        Route::post('/users', [UserController::class, 'store'])->name('api.v1.users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('api.v1.users.update');
        Route::get('/sessionize', [SessionizeStatusController::class, 'show'])->name('api.v1.sessionize.show');
        Route::post('/sessionize/sync', [SessionizeStatusController::class, 'store'])->name('api.v1.sessionize.sync');
    });
});
