<?php

use App\Http\Controllers\Panel\DashboardController;
use App\Modules\Competitions\Http\Controllers\CompetitionController;
use App\Modules\Competitions\Http\Controllers\DivisionController;
use App\Modules\Competitions\Http\Controllers\EntryController;
use App\Modules\Competitions\Http\Controllers\SeasonController;
use App\Modules\Competitions\Http\Controllers\StageController;
use App\Modules\Matches\Http\Controllers\FixtureController;
use App\Modules\Matches\Http\Controllers\GameController;
use App\Modules\Matches\Http\Controllers\SheetController;
use App\Modules\Matches\Http\Controllers\VenueController;
use App\Modules\Teams\Http\Controllers\PersonController;
use App\Modules\Teams\Http\Controllers\RosterController;
use App\Modules\Teams\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

/*
| Painel do organizador. Por enquanto qualquer usuário autenticado e verificado administra a organização padrão;
| papéis e permissões entram junto com a multi-tenancy completa.
*/
Route::middleware(['auth', 'verified'])->prefix('painel')->name('panel.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('teams', TeamController::class)->except('show');
    Route::resource('venues', VenueController::class)->except('show');
    Route::resource('people', PersonController::class)->except('show');

    Route::resource('competitions', CompetitionController::class);
    Route::resource('competitions.seasons', SeasonController::class)->shallow()->except('index');
    Route::resource('seasons.divisions', DivisionController::class)->shallow()->only(['store', 'show', 'update', 'destroy']);
    Route::resource('divisions.stages', StageController::class)->shallow()->only(['store', 'show', 'update', 'destroy']);
    Route::resource('divisions.entries', EntryController::class)->shallow()->only(['store', 'show', 'update', 'destroy']);
    Route::post('entries/{entry}/roster', [RosterController::class, 'store'])->name('entries.roster.store');
    Route::patch('roster/{rosterEntry}', [RosterController::class, 'update'])->name('roster.update');
    Route::delete('roster/{rosterEntry}', [RosterController::class, 'destroy'])->name('roster.destroy');

    Route::post('stages/{stage}/fixtures', [FixtureController::class, 'store'])->name('stages.fixtures.store');
    Route::delete('stages/{stage}/fixtures', [FixtureController::class, 'destroy'])->name('stages.fixtures.destroy');

    Route::patch('games/{game}', [GameController::class, 'update'])->name('games.update');
    Route::post('games/{game}/result', [GameController::class, 'result'])->name('games.result');
    Route::post('games/{game}/walkover', [GameController::class, 'walkover'])->name('games.walkover');
    Route::post('games/{game}/reopen', [GameController::class, 'reopen'])->name('games.reopen');

    Route::get('games/{game}/sheet', [SheetController::class, 'show'])->name('games.sheet');
    Route::put('games/{game}/lineups/{entry}', [SheetController::class, 'lineup'])->name('games.lineup');
    Route::post('games/{game}/events', [SheetController::class, 'storeEvent'])->name('games.events.store');
    Route::post('games/{game}/events/{event}/void', [SheetController::class, 'voidEvent'])->name('games.events.void');
    Route::post('games/{game}/periods', [SheetController::class, 'period'])->name('games.periods.store');
    Route::post('games/{game}/shootout', [SheetController::class, 'shootout'])->name('games.shootout');
    Route::post('games/{game}/finish', [SheetController::class, 'finish'])->name('games.finish');
});
