<?php

use App\Modules\Help\Http\Controllers\HelpController;
use App\Modules\Teams\Http\Controllers\Manager\CardsController;
use App\Modules\Teams\Http\Controllers\Manager\DashboardController;
use App\Modules\Teams\Http\Controllers\Manager\EntryController;
use App\Modules\Teams\Http\Controllers\Manager\GameController;
use App\Modules\Teams\Http\Controllers\Manager\NoticeController;
use App\Modules\Teams\Http\Controllers\Manager\RosterController;
use App\Modules\Teams\Http\Controllers\Manager\SupplementController;
use Illuminate\Support\Facades\Route;

/*
| Painel do gestor de equipe (ADR 0003). Entra quem está autenticado, verificado e tem o papel `manager`; cada rota
| ainda confere, no controlador, se o registro é de uma equipe vinculada ao usuário (`ManagedTeams`, 404 fora disso).
*/
Route::middleware(['auth', 'verified', 'manager.member', 'legal.accepted', 'feature:team_portal'])->prefix('gestor')->name('manager.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::patch('avisos', [NoticeController::class, 'update'])->name('notices.update');
    Route::get('inscricoes/{entry}', [EntryController::class, 'show'])->name('entries.show');
    Route::patch('elenco/{rosterEntry}', [RosterController::class, 'update'])->name('roster.update');
    Route::delete('elenco/{rosterEntry}', [RosterController::class, 'destroy'])->name('roster.destroy');
    Route::post('inscricoes/{entry}/reforcos', [SupplementController::class, 'store'])->middleware('feature:online_registration')->name('entries.supplement');
    Route::get('inscricoes/{entry}/carteirinhas', [CardsController::class, 'entry'])->middleware('feature:athlete_cards')->name('entries.cards');
    Route::get('ajuda', [HelpController::class, 'index'])->defaults('area', 'manager')->name('help.index');
    Route::get('ajuda/{slug}', [HelpController::class, 'show'])->defaults('area', 'manager')->name('help.show');
    Route::get('jogos', [GameController::class, 'index'])->name('games.index');
    Route::get('jogos/{game}', [GameController::class, 'show'])->name('games.show');
    Route::put('jogos/{game}/escalacao', [GameController::class, 'lineup'])->name('games.lineup');
});
