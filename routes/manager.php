<?php

use App\Modules\Teams\Http\Controllers\Manager\DashboardController;
use App\Modules\Teams\Http\Controllers\Manager\EntryController;
use App\Modules\Teams\Http\Controllers\Manager\GameController;
use Illuminate\Support\Facades\Route;

/*
| Painel do gestor de equipe (ADR 0003). Entra quem está autenticado, verificado e tem o papel `manager`; cada rota
| ainda confere, no controlador, se o registro é de uma equipe vinculada ao usuário (`ManagedTeams`, 404 fora disso).
*/
Route::middleware(['auth', 'verified', 'manager.member', 'legal.accepted', 'feature:team_portal'])->prefix('gestor')->name('manager.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('inscricoes/{entry}', [EntryController::class, 'show'])->name('entries.show');
    Route::get('jogos', [GameController::class, 'index'])->name('games.index');
    Route::get('jogos/{game}', [GameController::class, 'show'])->name('games.show');
    Route::put('jogos/{game}/escalacao', [GameController::class, 'lineup'])->name('games.lineup');
});
