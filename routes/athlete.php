<?php

use App\Modules\Help\Http\Controllers\HelpController;
use App\Modules\Market\Http\Controllers\AthleteController;
use App\Modules\Market\Http\Controllers\AthleteRequestController;
use App\Modules\Market\Http\Controllers\AthleteVideoController;
use Illuminate\Support\Facades\Route;

/*
| Painel do atleta do Mercado (ADR 0008). Entra quem está autenticado e tem o papel `athlete`; o e-mail só precisa estar
| verificado para PUBLICAR o perfil (a ação confere). Cada rota trabalha só no perfil do próprio usuário.
*/
Route::middleware(['auth', 'athlete.member', 'legal.accepted', 'feature:market', 'activity.log'])->prefix('atleta')->name('athlete.')->group(function () {
    Route::get('/', [AthleteController::class, 'show'])->name('dashboard');
    Route::put('perfil', [AthleteController::class, 'update'])->name('profile.update');
    Route::post('foto', [AthleteController::class, 'photo'])->name('photo.store');
    Route::delete('foto', [AthleteController::class, 'removePhoto'])->name('photo.destroy');
    Route::post('videos', [AthleteVideoController::class, 'store'])->name('videos.store');
    Route::delete('videos/{video}', [AthleteVideoController::class, 'destroy'])->name('videos.destroy');
    Route::post('publicar', [AthleteController::class, 'publish'])->name('publish');
    Route::post('pausar', [AthleteController::class, 'pause'])->name('pause');
    Route::post('pedidos/{contactRequest}/aceitar', [AthleteRequestController::class, 'accept'])->name('requests.accept');
    Route::post('pedidos/{contactRequest}/recusar', [AthleteRequestController::class, 'decline'])->name('requests.decline');
    Route::post('pedidos/{contactRequest}/bloquear', [AthleteRequestController::class, 'block'])->name('requests.block');
    Route::post('pedidos/{contactRequest}/denunciar', [AthleteRequestController::class, 'report'])->name('requests.report');
    Route::delete('bloqueios/{block}', [AthleteRequestController::class, 'unblock'])->name('blocks.destroy');
    Route::delete('conta', [AthleteController::class, 'destroy'])->name('destroy');
    Route::get('ajuda', [HelpController::class, 'index'])->defaults('area', 'athlete')->name('help.index');
    Route::get('ajuda/{slug}', [HelpController::class, 'show'])->defaults('area', 'athlete')->name('help.show');
    Route::post('ajuda/{slug}/avaliar', [HelpController::class, 'feedback'])->defaults('area', 'athlete')->name('help.feedback');
});
