<?php

use App\Http\Controllers\ProfileController;
use App\Modules\Broadcast\Http\Controllers\OverlayController;
use App\Modules\Matches\Http\Controllers\PublicGameController;
use App\Modules\Portal\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

// Portal público (sem login): só edições publicadas.
Route::get('/', [PortalController::class, 'home'])->name('portal.home');
Route::get('/competicoes/{competition:slug}', [PortalController::class, 'competition'])->name('portal.competition');
Route::get('/competicoes/{competition:slug}/edicoes/{season}', [PortalController::class, 'season'])->name('portal.season');
Route::get('/equipes/{team}', [PortalController::class, 'team'])->name('portal.team');
Route::get('/sitemap.xml', [PortalController::class, 'sitemap'])->name('portal.sitemap');
Route::get('/robots.txt', [PortalController::class, 'robots'])->name('portal.robots');

// Overlays do OBS (sem login; o token da URL é o acesso). A tela "estado" é a contingência sem WebSocket.
Route::get('/overlay/{channel:token}/estado', [OverlayController::class, 'state'])->name('overlay.state')->middleware('throttle:120,1');
Route::get('/overlay/{channel:token}/{overlay}', [OverlayController::class, 'show'])->name('overlay.show');

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
