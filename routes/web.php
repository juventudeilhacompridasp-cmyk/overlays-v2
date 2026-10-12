<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ProfileController;
use App\Modules\Matches\Http\Controllers\PublicGameController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register') && RegisteredUserController::isOpen(),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Portal público: placar ao vivo (sem login).
Route::get('/jogos/{game}', [PublicGameController::class, 'show'])->name('public.games.show');
Route::get('/jogos/{game}/estado', [PublicGameController::class, 'state'])->name('public.games.state');

Route::get('/dashboard', fn () => redirect()->route('panel.dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/panel.php';
require __DIR__.'/auth.php';
