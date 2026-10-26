<?php

use App\Http\Middleware\EnsureTwoFactorForAdmins;
use App\Http\Middleware\FrameOptions;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Market\Http\Middleware\EnsureAthlete;
use App\Modules\Tenancy\Http\Middleware\EnsureFeature;
use App\Modules\Tenancy\Http\Middleware\EnsureLegalAccepted;
use App\Modules\Tenancy\Http\Middleware\EnsureMember;
use App\Modules\Tenancy\Http\Middleware\EnsurePanelAbility;
use App\Modules\Tenancy\Http\Middleware\EnsurePlatformAdmin;
use App\Modules\Tenancy\Http\Middleware\EnsureTeamManager;
use App\Modules\Tenancy\Http\Middleware\ResolveOrganization;
use App\Support\TrustedProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Só proxies da rede interna (nginx, NPM, cloudflared) valem para X-Forwarded-*; `*` deixaria forjar o IP (limites e
        // bloqueio de login por IP). Ver App\Support\TrustedProxies e docs/17-seguranca.md.
        $middleware->trustProxies(at: TrustedProxies::resolve(env('TRUSTED_PROXIES')));

        // A organização ativa precisa estar definida ANTES da resolução de {team}, {season}...
        // pelas rotas (route model binding), senão o escopo da organização não vale nelas.
        $middleware->web(prepend: [
            ResolveOrganization::class,
        ], append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            FrameOptions::class,
            SecurityHeaders::class,
        ]);

        // A API pública também precisa da organização ativa antes da resolução dos {parâmetros}.
        $middleware->api(prepend: [
            ResolveOrganization::class,
        ], append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'panel.member' => EnsureMember::class,
            'manager.member' => EnsureTeamManager::class,
            'athlete.member' => EnsureAthlete::class,
            'panel.can' => EnsurePanelAbility::class,
            'feature' => EnsureFeature::class,
            'legal.accepted' => EnsureLegalAccepted::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'two-factor.admins' => EnsureTwoFactorForAdmins::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sem permissão: formulário/ação (não-GET) volta com aviso; página cai numa tela própria em vez da 403 crua.
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 403 || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
                return null;
            }

            if (! $request->isMethodSafe()) {
                return back()->with('error', $exception->getMessage() !== '' ? $exception->getMessage() : 'Você não tem permissão para esta ação.');
            }

            return Inertia::render('Errors/Forbidden')->toResponse($request)->setStatusCode(403);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
