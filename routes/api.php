<?php

use App\Modules\Broadcast\Http\Controllers\OverlayApiController;
use App\Modules\Portal\Http\Controllers\Api\V1Controller;
use App\Support\PlatformLimits;
use Illuminate\Support\Facades\Route;

/*
| API pública versionada de consulta. Quem consome fixa o prefixo da versão; mudanças incompatíveis entram em /api/v2
| e a v1 continua funcionando. O controle privado dos overlays fica abaixo, isolado por chave própria do canal.
*/
Route::prefix('v1')->name('api.v1.')->middleware(PlatformLimits::throttle('api'))->group(function () {
    Route::get('competitions', [V1Controller::class, 'competitions'])->name('competitions.index');
    Route::get('seasons/{season}', [V1Controller::class, 'season'])->name('seasons.show');
    Route::get('seasons/{season}/stats', [V1Controller::class, 'stats'])->name('seasons.stats');
    Route::get('games/{game}', [V1Controller::class, 'game'])->name('games.show');
});

// Controle privado de canais do OBS. A chave Bearer é própria do canal e nunca é o token público da URL de exibição.
Route::patch('v1/overlay-channels/{channel}/state', [OverlayApiController::class, 'update'])
    ->middleware([PlatformLimits::throttle('overlay'), 'feature:overlays'])
    ->name('api.v1.overlay-channels.state.update');
