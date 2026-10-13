<?php

use App\Modules\Help\Http\Controllers\HelpController;
use App\Modules\Market\Http\Controllers\AthleteController;
use Illuminate\Support\Facades\Route;

/*
| Painel do atleta do Mercado (ADR 0008). Entra quem está autenticado e tem o papel `athlete`; o e-mail só precisa estar
| verificado para PUBLICAR o perfil (a ação confere). Cada rota trabalha só no perfil do próprio usuário.
*/
Route::middleware(['auth', 'athlete.member', 'legal.accepted', 'feature:market'])->prefix('atleta')->name('athlete.')->group(function () {
    Route::get('/', [AthleteController::class, 'show'])->name('dashboard');
    Route::put('perfil', [AthleteController::class, 'update'])->name('profile.update');
    Route::post('foto', [AthleteController::class, 'photo'])->name('photo.store');
    Route::delete('foto', [AthleteController::class, 'removePhoto'])->name('photo.destroy');
    Route::post('publicar', [AthleteController::class, 'publish'])->name('publish');
    Route::post('pausar', [AthleteController::class, 'pause'])->name('pause');
    Route::delete('conta', [AthleteController::class, 'destroy'])->name('destroy');
    Route::get('ajuda', [HelpController::class, 'index'])->defaults('area', 'athlete')->name('help.index');
    Route::get('ajuda/{slug}', [HelpController::class, 'show'])->defaults('area', 'athlete')->name('help.show');
});
