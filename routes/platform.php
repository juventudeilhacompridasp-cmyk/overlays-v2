<?php

use App\Modules\ApiDocs\Http\Controllers\ApiDocsController;
use App\Modules\Help\Http\Controllers\HelpController;
use App\Modules\Marketing\Http\Controllers\PlatformLeadController;
use App\Modules\Marketing\Http\Controllers\PlatformMetricsController;
use App\Modules\Status\Http\Controllers\PlatformStatusController;
use App\Modules\Tenancy\Http\Controllers\PlatformOrganizationController;
use Illuminate\Support\Facades\Route;

/*
| Área da plataforma: administradores da plataforma (`users.is_platform_admin`, criados por `php artisan platform:make-admin`).
| Não depende de papel numa organização. Quem não é administrador da plataforma recebe 404.
*/
Route::middleware(['auth', 'verified', 'platform.admin'])->prefix('plataforma')->name('platform.')->group(function () {
    Route::get('/', [PlatformOrganizationController::class, 'index'])->name('organizations.index');
    Route::get('ajuda', [HelpController::class, 'index'])->defaults('area', 'platform')->name('help.index');
    Route::get('ajuda/{slug}', [HelpController::class, 'show'])->defaults('area', 'platform')->name('help.show');
    Route::get('api-docs', [ApiDocsController::class, 'index'])->name('api-docs.index');
    Route::get('api-docs/openapi.json', [ApiDocsController::class, 'openapi'])->name('api-docs.openapi');
    Route::get('medicao', [PlatformMetricsController::class, 'index'])->name('metrics.index');
    Route::get('contatos', [PlatformLeadController::class, 'index'])->name('leads.index');
    Route::patch('contatos/{lead}', [PlatformLeadController::class, 'update'])->name('leads.update');
    Route::delete('contatos/{lead}', [PlatformLeadController::class, 'destroy'])->name('leads.destroy');
    Route::get('status', [PlatformStatusController::class, 'index'])->name('status.index');
    Route::post('status/incidents', [PlatformStatusController::class, 'store'])->name('status.incidents.store');
    Route::post('status/incidents/{incident}/updates', [PlatformStatusController::class, 'update'])->name('status.incidents.update');
    Route::delete('status/incidents/{incident}', [PlatformStatusController::class, 'destroy'])->name('status.incidents.destroy');
    Route::get('organizations/create', [PlatformOrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [PlatformOrganizationController::class, 'store'])->name('organizations.store');
    Route::post('organizations/{organization}/domains', [PlatformOrganizationController::class, 'storeDomain'])->name('organizations.domains.store');
    Route::delete('organizations/{organization}/domains/{domain}', [PlatformOrganizationController::class, 'destroyDomain'])->name('organizations.domains.destroy');
    Route::get('organizations/{organization}', [PlatformOrganizationController::class, 'edit'])->name('organizations.edit');
    Route::patch('organizations/{organization}', [PlatformOrganizationController::class, 'update'])->name('organizations.update');
});
