<?php

use App\Modules\Tenancy\Http\Controllers\PlatformOrganizationController;
use Illuminate\Support\Facades\Route;

/*
| Área da plataforma: administradores da plataforma (`users.is_platform_admin`, criados por `php artisan platform:make-admin`).
| Não depende de papel numa organização. Quem não é administrador da plataforma recebe 404.
*/
Route::middleware(['auth', 'verified', 'platform.admin'])->prefix('plataforma')->name('platform.')->group(function () {
    Route::get('/', [PlatformOrganizationController::class, 'index'])->name('organizations.index');
    Route::get('organizations/{organization}', [PlatformOrganizationController::class, 'edit'])->name('organizations.edit');
    Route::patch('organizations/{organization}', [PlatformOrganizationController::class, 'update'])->name('organizations.update');
});
