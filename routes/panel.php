<?php

use App\Http\Controllers\Panel\DashboardController;
use App\Modules\Broadcast\Http\Controllers\ChannelController;
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
use App\Modules\Teams\Http\Controllers\PersonPhotoController;
use App\Modules\Teams\Http\Controllers\RosterController;
use App\Modules\Teams\Http\Controllers\TeamController;
use App\Modules\Teams\Http\Controllers\TeamLogoController;
use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
| Painel do organizador. Entra quem está autenticado, verificado e tem papel na organização (`panel.member`).
| Consultar vale para qualquer papel; escrever exige a permissão da área (`panel.can:{ability}`, ver `Role`).
*/
Route::middleware(['auth', 'verified', 'panel.member'])->prefix('painel')->name('panel.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Cadastros: equipes, estádios, atletas e comissão, elencos.
    Route::middleware('panel.can:manage-registry')->group(function () {
        Route::resource('teams', TeamController::class)->except('show');
        Route::post('teams/{team}/logo', [TeamLogoController::class, 'store'])->name('teams.logo.store');
        Route::delete('teams/{team}/logo', [TeamLogoController::class, 'destroy'])->name('teams.logo.destroy');
        Route::resource('venues', VenueController::class)->except('show');
        Route::resource('people', PersonController::class)->except('show');
        Route::post('people/{person}/photo', [PersonPhotoController::class, 'store'])->name('people.photo.store');
        Route::delete('people/{person}/photo', [PersonPhotoController::class, 'destroy'])->name('people.photo.destroy');
        Route::post('entries/{entry}/roster', [RosterController::class, 'store'])->name('entries.roster.store');
        Route::patch('roster/{rosterEntry}', [RosterController::class, 'update'])->name('roster.update');
        Route::delete('roster/{rosterEntry}', [RosterController::class, 'destroy'])->name('roster.destroy');
    });

    // Competições: edições, divisões, fases, inscrições, tabela e resultados.
    Route::middleware('panel.can:manage-competitions')->group(function () {
        Route::resource('competitions', CompetitionController::class);
        Route::resource('competitions.seasons', SeasonController::class)->shallow()->except('index');
        Route::resource('seasons.divisions', DivisionController::class)->shallow()->only(['store', 'show', 'update', 'destroy']);
        Route::resource('divisions.stages', StageController::class)->shallow()->only(['store', 'show', 'update', 'destroy']);
        Route::resource('divisions.entries', EntryController::class)->shallow()->only(['store', 'show', 'update', 'destroy']);

        Route::post('stages/{stage}/fixtures', [FixtureController::class, 'store'])->name('stages.fixtures.store');
        Route::delete('stages/{stage}/fixtures', [FixtureController::class, 'destroy'])->name('stages.fixtures.destroy');

        Route::patch('games/{game}', [GameController::class, 'update'])->name('games.update');
        Route::post('games/{game}/result', [GameController::class, 'result'])->name('games.result');
        Route::post('games/{game}/walkover', [GameController::class, 'walkover'])->name('games.walkover');
        Route::post('games/{game}/reopen', [GameController::class, 'reopen'])->name('games.reopen');
    });

    // Operação do jogo: escalação, súmula, cronômetro, pênaltis e encerramento.
    Route::middleware('panel.can:operate-games')->group(function () {
        Route::get('games/{game}/sheet', [SheetController::class, 'show'])->name('games.sheet');
        Route::put('games/{game}/lineups/{entry}', [SheetController::class, 'lineup'])->name('games.lineup');
        Route::post('games/{game}/events', [SheetController::class, 'storeEvent'])->name('games.events.store');
        Route::post('games/{game}/events/{event}/void', [SheetController::class, 'voidEvent'])->name('games.events.void');
        Route::post('games/{game}/periods', [SheetController::class, 'period'])->name('games.periods.store');
        Route::post('games/{game}/shootout', [SheetController::class, 'shootout'])->name('games.shootout');
        Route::post('games/{game}/finish', [SheetController::class, 'finish'])->name('games.finish');
    });

    // Transmissão (OBS): ver a lista é para qualquer membro; criar/excluir canal é do organizador; o operador
    // escolhe o jogo no ar e liga camadas durante o jogo.
    Route::get('broadcast', [ChannelController::class, 'index'])->name('broadcast.index');
    Route::middleware('panel.can:manage-competitions')->group(function () {
        Route::post('broadcast', [ChannelController::class, 'store'])->name('broadcast.store');
        Route::post('broadcast/{channel}/regenerate', [ChannelController::class, 'regenerate'])->name('broadcast.regenerate');
        Route::delete('broadcast/{channel}', [ChannelController::class, 'destroy'])->name('broadcast.destroy');
    });
    Route::patch('broadcast/{channel}', [ChannelController::class, 'update'])->middleware('panel.can:operate-games')->name('broadcast.update');

    // Usuários, papéis e identidade do portal (só administradores; nem consultar).
    Route::middleware('panel.can:manage-users')->group(function () {
        Route::get('organization', [OrganizationController::class, 'edit'])->name('organization.edit');
        Route::patch('organization', [OrganizationController::class, 'update'])->name('organization.update');
        Route::get('users', [MemberController::class, 'index'])->name('users.index');
        Route::post('users', [MemberController::class, 'store'])->name('users.store');
        Route::patch('users/{membership}', [MemberController::class, 'update'])->name('users.update');
        Route::delete('users/{membership}', [MemberController::class, 'destroy'])->name('users.destroy');
    });
});
