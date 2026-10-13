<?php

use App\Modules\Portal\Http\Controllers\Api\V1Controller;
use Illuminate\Support\Facades\Route;

/*
| API pública versionada (somente leitura). Quem consome fixa o prefixo da versão; mudanças incompatíveis
| entram em /api/v2 e a v1 continua funcionando.
*/
Route::prefix('v1')->name('api.v1.')->middleware('throttle:60,1')->group(function () {
    Route::get('competitions', [V1Controller::class, 'competitions'])->name('competitions.index');
    Route::get('seasons/{season}', [V1Controller::class, 'season'])->name('seasons.show');
    Route::get('seasons/{season}/stats', [V1Controller::class, 'stats'])->name('seasons.stats');
    Route::get('games/{game}', [V1Controller::class, 'game'])->name('games.show');
});
