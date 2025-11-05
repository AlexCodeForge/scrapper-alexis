<?php

use App\Http\Controllers\AuthController;
use App\Livewire\Dashboard;
use App\Livewire\Images;
use App\Livewire\Logs;
use App\Livewire\ScrapedMessages;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/dashboard', Dashboard::class);
    Route::get('/images', Images::class)->name('images');
    Route::get('/images/download/{zipFileName}', function ($zipFileName) {
        $zipPath = storage_path('app/public/' . basename($zipFileName));

        if (file_exists($zipPath)) {
            return response()->download($zipPath)->deleteFileAfterSend();
        }

        abort(404);
    })->name('images.download');
    Route::get('/messages', ScrapedMessages::class)->name('messages');
    Route::get('/settings', Settings::class)->name('settings');
    Route::get('/logs', Logs::class)->name('logs');
});
