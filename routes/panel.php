<?php

use App\Http\Controllers\Panel\DashboardController;
use App\Modules\Broadcast\Http\Controllers\ChannelController;
use App\Modules\Competitions\Http\Controllers\CompetitionController;
use App\Modules\Competitions\Http\Controllers\DivisionController;
use App\Modules\Competitions\Http\Controllers\EntryController;
use App\Modules\Competitions\Http\Controllers\ReportController;
use App\Modules\Competitions\Http\Controllers\SeasonController;
use App\Modules\Competitions\Http\Controllers\StageController;
use App\Modules\Help\Http\Controllers\HelpController;
use App\Modules\Market\Http\Controllers\Panel\AdminController as MarketAdminController;
use App\Modules\Matches\Http\Controllers\FixtureController;
use App\Modules\Matches\Http\Controllers\GameController;
use App\Modules\Matches\Http\Controllers\MesarioController;
use App\Modules\Matches\Http\Controllers\SheetController;
use App\Modules\Matches\Http\Controllers\SheetReviewController;
use App\Modules\Matches\Http\Controllers\SheetUploadController;
use App\Modules\Matches\Http\Controllers\VenueController;
use App\Modules\Portal\Http\Controllers\EmbedPanelController;
use App\Modules\Registration\Http\Controllers\ReviewController;
use App\Modules\Registration\Http\Controllers\SeasonSettingsController;
use App\Modules\Teams\Http\Controllers\CardController;
use App\Modules\Teams\Http\Controllers\PersonController;
use App\Modules\Teams\Http\Controllers\PersonPhotoController;
use App\Modules\Teams\Http\Controllers\RosterController;
use App\Modules\Teams\Http\Controllers\TeamController;
use App\Modules\Teams\Http\Controllers\TeamLogoController;
use App\Modules\Teams\Http\Controllers\TeamManagerController;
use App\Modules\Tenancy\Http\Controllers\AccessProfileController;
use App\Modules\Tenancy\Http\Controllers\CommunicationsController;
use App\Modules\Tenancy\Http\Controllers\LegalController;
use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
| Painel do organizador. Entra quem está autenticado, verificado e tem papel na organização (`panel.member`).
| Consultar vale para qualquer papel; escrever exige a permissão da área (`panel.can:{ability}`, ver `Role`).
*/
Route::middleware(['auth', 'verified', 'panel.member', 'legal.accepted'])->prefix('painel')->name('panel.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Cadastros: equipes, estádios, atletas e comissão, elencos.
    Route::middleware('panel.can:manage-registry')->group(function () {
        Route::resource('teams', TeamController::class)->except('show');
        Route::post('teams/{team}/logo', [TeamLogoController::class, 'store'])->name('teams.logo.store');
        Route::delete('teams/{team}/logo', [TeamLogoController::class, 'destroy'])->name('teams.logo.destroy');
        Route::middleware('feature:team_portal')->group(function () {
            Route::post('teams/{team}/managers', [TeamManagerController::class, 'store'])->name('teams.managers.store');
            Route::post('team-managers/{teamManager}/resend', [TeamManagerController::class, 'resend'])->name('teams.managers.resend');
            Route::delete('team-managers/{teamManager}', [TeamManagerController::class, 'destroy'])->name('teams.managers.destroy');
        });
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

    // Inscrições online: análise dos pedidos. Tem dados pessoais e documentos (de menores, inclusive): só quem gerencia
    // competições, até para consultar (`can:`, e não `panel.can:`, que libera a leitura a qualquer membro).
    // Cada área depende de um recurso do pacote da organização (`feature:`).
    Route::middleware(['can:manage-competitions', 'feature:athlete_cards'])->group(function () {
        Route::get('entries/{entry}/cards', [CardController::class, 'entry'])->name('entries.cards');
        Route::get('roster/{rosterEntry}/card', [CardController::class, 'show'])->name('roster.card');
        Route::post('roster/{rosterEntry}/card/regenerate', [CardController::class, 'regenerate'])->name('roster.card.regenerate');
    });

    // Central de ajuda (ADR 0006): qualquer membro; o que aparece depende do perfil e do pacote.
    Route::get('ajuda', [HelpController::class, 'index'])->defaults('area', 'panel')->name('help.index');
    Route::get('ajuda/{slug}', [HelpController::class, 'show'])->defaults('area', 'panel')->name('help.show');

    Route::get('widgets', [EmbedPanelController::class, 'index'])->middleware('feature:embeds')->name('widgets.index');

    Route::middleware(['can:manage-competitions', 'feature:reports'])->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{season}/{type}.csv', [ReportController::class, 'csv'])->name('reports.csv');
    });

    Route::middleware(['can:manage-competitions', 'feature:online_registration'])->group(function () {
        Route::get('registrations', [ReviewController::class, 'index'])->name('registrations.index');
        Route::get('registrations/{registration}', [ReviewController::class, 'show'])->name('registrations.show');
        Route::patch('registrations/{registration}/people/{person}', [ReviewController::class, 'reviewPerson'])->name('registrations.people.review');
        Route::post('registrations/{registration}/approve', [ReviewController::class, 'approve'])->name('registrations.approve');
        Route::post('registrations/{registration}/manager', [ReviewController::class, 'inviteManager'])->middleware('feature:team_portal')->name('registrations.manager');
        Route::post('registrations/{registration}/return', [ReviewController::class, 'returnForChanges'])->name('registrations.return');
        Route::post('registrations/{registration}/reject', [ReviewController::class, 'reject'])->name('registrations.reject');
        Route::delete('registrations/{registration}', [ReviewController::class, 'destroy'])->name('registrations.destroy');
        Route::get('registration-documents/{document}', [ReviewController::class, 'document'])->name('registrations.documents.show');
        Route::patch('seasons/{season}/registration', [SeasonSettingsController::class, 'update'])->name('seasons.registration.update');
    });

    // Operação do jogo: escalação, súmula, cronômetro, pênaltis e encerramento.
    Route::middleware('panel.can:operate-games')->group(function () {
        Route::get('mesario', [MesarioController::class, 'index'])->name('mesario');
        Route::get('games/{game}/sheet', [SheetController::class, 'show'])->name('games.sheet');
        Route::put('games/{game}/lineups/{entry}', [SheetController::class, 'lineup'])->name('games.lineup');
        Route::post('games/{game}/sync', [SheetController::class, 'sync'])->name('games.sync');
        Route::post('games/{game}/events', [SheetController::class, 'storeEvent'])->name('games.events.store');
        Route::post('games/{game}/points', [SheetController::class, 'point'])->name('games.points.store');
        Route::post('games/{game}/events/{event}/void', [SheetController::class, 'voidEvent'])->name('games.events.void');
        Route::post('games/{game}/periods', [SheetController::class, 'period'])->name('games.periods.store');
        Route::post('games/{game}/clock', [SheetController::class, 'clockPause'])->name('games.clock.pause');
        Route::post('games/{game}/shootout', [SheetController::class, 'shootout'])->name('games.shootout');
        Route::post('games/{game}/finish', [SheetController::class, 'finish'])->name('games.finish');
    });

    // Súmulas enviadas depois do jogo (ADR 0004): consultar é para qualquer membro; enviar e excluir é de quem opera jogos.
    Route::middleware('feature:sheet_import')->group(function () {
        Route::get('games/{game}/uploads', [SheetUploadController::class, 'index'])->name('games.uploads');
        Route::get('game-sheet-uploads/{upload}', [SheetUploadController::class, 'show'])->name('games.uploads.show');
        Route::get('game-sheet-uploads/{upload}/review', [SheetReviewController::class, 'review'])->name('games.uploads.review');
        Route::get('game-sheet-uploads/{upload}/report', [SheetReviewController::class, 'report'])->name('games.uploads.report');
        Route::get('game-sheet-uploads/{upload}/report.csv', [SheetReviewController::class, 'csv'])->name('games.uploads.report.csv');
        Route::get('sheet-import/template.csv', [SheetUploadController::class, 'template'])->name('sheet-import.template');
        Route::patch('sheet-import/settings', [SheetUploadController::class, 'settings'])->middleware('panel.can:manage-users')->name('sheet-import.settings');
        Route::middleware('panel.can:operate-games')->group(function () {
            Route::post('games/{game}/uploads', [SheetUploadController::class, 'store'])->name('games.uploads.store');
            Route::post('game-sheet-uploads/{upload}/read', [SheetUploadController::class, 'read'])->name('games.uploads.read');
            Route::post('game-sheet-uploads/{upload}/review', [SheetReviewController::class, 'confirm'])->name('games.uploads.review.confirm');
            Route::post('game-sheet-uploads/{upload}/discard', [SheetReviewController::class, 'discard'])->name('games.uploads.discard');
            Route::delete('game-sheet-uploads/{upload}', [SheetUploadController::class, 'destroy'])->name('games.uploads.destroy');
        });
    });

    // Transmissão (OBS): ver a lista é para qualquer membro; criar/excluir canal é do organizador; o operador
    // escolhe o jogo no ar e liga camadas durante o jogo.
    Route::middleware('feature:overlays')->group(function () {
        Route::get('broadcast', [ChannelController::class, 'index'])->name('broadcast.index');
        Route::patch('broadcast/{channel}', [ChannelController::class, 'update'])->middleware('panel.can:operate-games')->name('broadcast.update');
    });
    Route::middleware(['panel.can:manage-competitions', 'feature:overlays'])->group(function () {
        Route::post('broadcast', [ChannelController::class, 'store'])->name('broadcast.store');
        Route::post('broadcast/{channel}/regenerate', [ChannelController::class, 'regenerate'])->name('broadcast.regenerate');
        Route::delete('broadcast/{channel}', [ChannelController::class, 'destroy'])->name('broadcast.destroy');
    });

    // Mercado de jogadores: moderação (denúncias, suspensão, cota). Nem consultar sem a permissão: há denúncias.
    Route::middleware(['can:manage-market', 'feature:market'])->prefix('mercado')->name('market.')->group(function () {
        Route::get('/', [MarketAdminController::class, 'index'])->name('index');
        Route::post('perfis/{profile}/suspender', [MarketAdminController::class, 'suspend'])->name('suspend');
        Route::post('perfis/{profile}/reativar', [MarketAdminController::class, 'restore'])->name('restore');
        Route::patch('denuncias/{report}', [MarketAdminController::class, 'resolve'])->name('reports.resolve');
        Route::patch('configuracoes', [MarketAdminController::class, 'settings'])->name('settings');
    });

    // Usuários, papéis e identidade do portal (só administradores; nem consultar).
    Route::middleware('panel.can:manage-users')->group(function () {
        Route::get('legal', [LegalController::class, 'edit'])->name('legal.edit');
        Route::patch('legal/settings', [LegalController::class, 'updateSettings'])->name('legal.settings');
        Route::post('legal/publish', [LegalController::class, 'publish'])->name('legal.publish');
        Route::get('profiles', [AccessProfileController::class, 'index'])->name('profiles.index');
        Route::get('profiles/create', [AccessProfileController::class, 'create'])->name('profiles.create');
        Route::post('profiles', [AccessProfileController::class, 'store'])->name('profiles.store');
        Route::get('profiles/{profile}/edit', [AccessProfileController::class, 'edit'])->name('profiles.edit');
        Route::patch('profiles/{profile}', [AccessProfileController::class, 'update'])->name('profiles.update');
        Route::post('profiles/{profile}/duplicate', [AccessProfileController::class, 'duplicate'])->name('profiles.duplicate');
        Route::delete('profiles/{profile}', [AccessProfileController::class, 'destroy'])->name('profiles.destroy');
        Route::get('communications', [CommunicationsController::class, 'edit'])->name('communications.edit');
        Route::patch('communications', [CommunicationsController::class, 'update'])->name('communications.update');
        Route::get('organization', [OrganizationController::class, 'edit'])->name('organization.edit');
        Route::patch('organization', [OrganizationController::class, 'update'])->name('organization.update');
        Route::patch('organization/portal', [OrganizationController::class, 'updatePortal'])->name('organization.portal');
        Route::middleware('feature:white_label')->group(function () {
            Route::post('organization/logo', [OrganizationController::class, 'storeLogo'])->name('organization.logo.store');
            Route::delete('organization/logo', [OrganizationController::class, 'destroyLogo'])->name('organization.logo.destroy');
        });
        Route::get('users', [MemberController::class, 'index'])->name('users.index');
        Route::post('users', [MemberController::class, 'store'])->name('users.store');
        Route::patch('users/{membership}', [MemberController::class, 'update'])->name('users.update');
        Route::delete('users/{membership}', [MemberController::class, 'destroy'])->name('users.destroy');
    });
});
