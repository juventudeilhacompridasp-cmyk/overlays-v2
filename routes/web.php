<?php

use App\Http\Controllers\CspReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TwoFactorSettingsController;
use App\Http\Middleware\PrivateNoIndex;
use App\Modules\Broadcast\Http\Controllers\ChampionshipOverlayController;
use App\Modules\Broadcast\Http\Controllers\OverlayController;
use App\Modules\Market\Http\Controllers\SignupController;
use App\Modules\Marketing\Http\Controllers\LandingController;
use App\Modules\Marketing\Http\Controllers\LandingMetricsController;
use App\Modules\Marketing\Support\LandingHosts;
use App\Modules\Matches\Http\Controllers\GameSignatureController;
use App\Modules\Matches\Http\Controllers\PublicGameController;
use App\Modules\Portal\Http\Controllers\CardVerifyController;
use App\Modules\Portal\Http\Controllers\EmbedController;
use App\Modules\Portal\Http\Controllers\LegalPageController;
use App\Modules\Portal\Http\Controllers\PortalController;
use App\Modules\Portal\Http\Controllers\PushController;
use App\Modules\Registration\Http\Controllers\ApplyController;
use App\Modules\Registration\Http\Controllers\ManageController;
use App\Modules\Status\Http\Controllers\StatusPageController;
use App\Modules\Tenancy\Enums\Role;
use App\Modules\Tenancy\Http\Controllers\LegalAcceptController;
use App\Support\PlatformLimits;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Landing page do produto (docs/14): sempre em /produto; a raiz só nos hosts da landing quando `platform.landing.root` está ligado.
foreach (LandingHosts::all() as $landingHost) {
    Route::domain($landingHost)->get('/', [LandingController::class, 'show']);
}
Route::get('/produto', [LandingController::class, 'show'])->middleware(PlatformLimits::throttle('public'))->name('marketing.landing');
Route::post('/produto/contato', [LandingController::class, 'contact'])->middleware('throttle:5,1')->name('marketing.contact');
Route::post('/produto/medicao', [LandingMetricsController::class, 'store'])->middleware('throttle:30,1')->name('marketing.metrics');

// Relatórios de violação da CSP (docs/17 §5): o navegador envia sozinho; sem login, sem CSRF, limitado por IP.
Route::post('/csp-report', CspReportController::class)->middleware(PlatformLimits::throttle('public'))->withoutMiddleware(ValidateCsrfToken::class)->name('csp.report');

// Status da plataforma (docs/14): sem login e sem dado de organização; também é a página inicial de status.{domínio-base}.
if (is_string($statusBase = config('platform.base_domain')) && $statusBase !== '') {
    Route::domain("status.{$statusBase}")->get('/', [StatusPageController::class, 'index']);
}
Route::middleware(PlatformLimits::throttle('public'))->group(function () {
    Route::get('/status', [StatusPageController::class, 'index'])->name('status.index');
    Route::get('/status.json', [StatusPageController::class, 'json'])->name('status.json');
    Route::get('/status.atom', [StatusPageController::class, 'atom'])->name('status.atom');
    Route::get('/status/selo.svg', [StatusPageController::class, 'badge'])->name('status.badge');
    Route::post('/status/inscrever', [StatusPageController::class, 'subscribe'])->middleware('throttle:5,1')->name('status.subscribe');
    Route::get('/status/confirmar/{token}', [StatusPageController::class, 'confirm'])->where('token', '[A-Za-z0-9]{40}')->name('status.confirm');
    Route::get('/status/cancelar/{token}', [StatusPageController::class, 'unsubscribeShow'])->where('token', '[A-Za-z0-9]{40}')->name('status.unsubscribe.show');
    Route::post('/status/cancelar/{token}', [StatusPageController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{40}')->name('status.unsubscribe');
    Route::get('/status/preferencias/{token}', [StatusPageController::class, 'preferencesShow'])->where('token', '[A-Za-z0-9]{40}')->name('status.preferences.show');
    Route::post('/status/preferencias/{token}', [StatusPageController::class, 'preferencesUpdate'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:20,1')->name('status.preferences');
});

// Portal público (sem login): só edições publicadas.
Route::get('/', [PortalController::class, 'home'])->name('portal.home');
Route::get('/competicoes/{competition:slug}', [PortalController::class, 'competition'])->name('portal.competition');
Route::get('/competicoes/{competition:slug}/edicoes/{season}', [PortalController::class, 'season'])->name('portal.season');
Route::get('/competicoes/{competition:slug}/edicoes/{season}/estatisticas', [PortalController::class, 'stats'])->name('portal.stats');
Route::get('/locais/{venue}', [PortalController::class, 'venue'])->name('portal.venue');
Route::get('/feed.xml', [PortalController::class, 'feed'])->name('portal.feed')->middleware(PlatformLimits::throttle('public'));
Route::get('/busca', [PortalController::class, 'search'])->name('portal.search')->middleware(PlatformLimits::throttle('public'));
Route::get('/equipes/{team}', [PortalController::class, 'team'])->name('portal.team');
Route::get('/equipes/{team}/calendario.ics', [PortalController::class, 'teamCalendar'])->name('portal.team.calendar');
// Avisos push do público (jogo ou equipe); exigem o recurso `push` e as chaves VAPID.
Route::middleware([PlatformLimits::throttle('push'), 'feature:push'])->prefix('push')->name('push.')->group(function () {
    Route::post('assinar', [PushController::class, 'subscribe'])->name('subscribe');
    Route::post('cancelar', [PushController::class, 'unsubscribe'])->name('unsubscribe');
});

// Widgets para outros sites (iframe): sem login, só edição publicada, liberados pelo recurso "embeds".
Route::middleware([PlatformLimits::throttle('embed'), 'feature:embeds'])->prefix('embed')->name('embed.')->group(function () {
    Route::get('jogo/{game}', [EmbedController::class, 'game'])->name('game');
    Route::get('classificacao/{division}', [EmbedController::class, 'standings'])->name('standings');
    Route::get('jogos/{season}', [EmbedController::class, 'games'])->name('games');
});

// Verificação da carteirinha (QR): sem cache e fora dos buscadores (cabeçalho e robots).
Route::get('/carteirinha/{code}', CardVerifyController::class)->name('portal.card')->middleware([PlatformLimits::throttle('card'), 'feature:athlete_cards', PrivateNoIndex::class]);
Route::get('/termos', [LegalPageController::class, 'terms'])->name('portal.terms');
Route::get('/privacidade', [LegalPageController::class, 'privacy'])->name('portal.privacy');
Route::middleware('throttle:10,1')->prefix('assinaturas')->name('game-signatures.')->group(function () {
    Route::get('/{token}', [GameSignatureController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->name('confirm');
    Route::post('/{token}', [GameSignatureController::class, 'confirm'])->where('token', '[A-Za-z0-9]{64}')->name('confirm.submit');
});
Route::get('/sitemap.xml', [PortalController::class, 'sitemap'])->name('portal.sitemap');
Route::get('/robots.txt', [PortalController::class, 'robots'])->name('portal.robots');

// Inscrição online de equipes (sem login). O link pessoal da equipe (`/minha-inscricao/{token}`) é o acesso ao pedido.
Route::middleware([PlatformLimits::throttle('public'), 'feature:online_registration'])->group(function () {
    Route::get('/inscricoes/{competition:slug}/{season}', [ApplyController::class, 'show'])->name('registration.apply');
    Route::post('/inscricoes/{competition:slug}/{season}', [ApplyController::class, 'store'])->middleware(PlatformLimits::throttle('registration'))->name('registration.store');

    Route::prefix('/minha-inscricao/{registration:token}')->name('registration.')->group(function () {
        Route::get('/', [ManageController::class, 'show'])->name('manage');
        Route::patch('/equipe', [ManageController::class, 'updateTeam'])->name('team');
        Route::post('/pessoas', [ManageController::class, 'storePerson'])->name('people.store');
        Route::patch('/pessoas/{person}', [ManageController::class, 'updatePerson'])->name('people.update');
        Route::delete('/pessoas/{person}', [ManageController::class, 'destroyPerson'])->name('people.destroy');
        Route::post('/pessoas/{person}/documentos', [ManageController::class, 'storeDocument'])->name('documents.store');
        Route::delete('/pessoas/{person}/documentos/{document}', [ManageController::class, 'destroyDocument'])->name('documents.destroy');
        Route::post('/enviar', [ManageController::class, 'submit'])->name('submit');
    });
});

// Overlays do OBS (sem login; o token da URL é o acesso). A tela "estado" é a contingência sem WebSocket.
Route::get('/overlay/{channel:token}/estado', [OverlayController::class, 'state'])->name('overlay.state')->middleware([PlatformLimits::throttle('overlay'), 'feature:overlays']);
Route::get('/overlay/{channel:token}/fonte/{font}', [OverlayController::class, 'font'])->name('overlay.font')->middleware([PlatformLimits::throttle('overlay'), 'feature:overlays']);
Route::get('/overlay/{channel:token}/personalizado/{item}', [OverlayController::class, 'custom'])->name('overlay.custom')->middleware('feature:overlays');
Route::get('/overlay/{channel:token}/campeonato/dados', [ChampionshipOverlayController::class, 'data'])->name('overlay.championship.data')->middleware([PlatformLimits::throttle('overlay'), 'feature:overlays']);
Route::get('/overlay/{channel:token}/campeonato/{view}', [ChampionshipOverlayController::class, 'show'])->name('overlay.championship')->middleware('feature:overlays');
Route::get('/overlay/{channel:token}/{overlay}', [OverlayController::class, 'show'])->name('overlay.show')->middleware('feature:overlays');

// Portal público: placar ao vivo (sem login).
Route::get('/jogos/{game}', [PublicGameController::class, 'show'])->name('public.games.show');
Route::get('/jogos/{game}/estado', [PublicGameController::class, 'state'])->name('public.games.state');
Route::get('/jogos/{game}/imagem.png', [PublicGameController::class, 'image'])->name('public.games.image');

// Mercado de jogadores livres (ADR 0008): cadastro público do atleta; 404 sem o recurso, fora dos buscadores.
Route::middleware(['guest', 'feature:market', PrivateNoIndex::class])->prefix('mercado')->group(function () {
    Route::get('/cadastro', [SignupController::class, 'create'])->name('market.signup');
    Route::post('/cadastro', [SignupController::class, 'store'])->middleware('throttle:6,1')->name('market.signup.store');
});

// Destino padrão depois do login: cada papel vai para o seu painel (o gestor de equipe tem o próprio).
Route::get('/dashboard', fn () => redirect()->route(match (request()->user()?->role()) {
    Role::TeamManager => 'manager.dashboard',
    Role::Athlete => 'athlete.dashboard',
    default => request()->user()?->isOperatorOnly() ? 'panel.mesario' : 'panel.dashboard',
}))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'activity.log'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/two-factor', [TwoFactorSettingsController::class, 'begin'])->middleware('throttle:10,1')->name('profile.two-factor.begin');
    Route::post('/profile/two-factor/confirm', [TwoFactorSettingsController::class, 'confirm'])->middleware('throttle:10,1')->name('profile.two-factor.confirm');
    Route::post('/profile/two-factor/recovery-codes', [TwoFactorSettingsController::class, 'recoveryCodes'])->middleware('throttle:10,1')->name('profile.two-factor.recovery');
    Route::delete('/profile/two-factor', [TwoFactorSettingsController::class, 'disable'])->middleware('throttle:10,1')->name('profile.two-factor.disable');
});

// Aceite dos termos/privacidade publicados (exigido antes de usar o painel).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/aceite-termos', [LegalAcceptController::class, 'show'])->name('legal.accept.show');
    Route::post('/aceite-termos', [LegalAcceptController::class, 'store'])->name('legal.accept.store');
});

require __DIR__.'/panel.php';
require __DIR__.'/manager.php';
require __DIR__.'/athlete.php';
require __DIR__.'/platform.php';
require __DIR__.'/auth.php';
