<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Tenancy\Http\Middleware\EnsureMember;
use App\Modules\Tenancy\Http\Middleware\EnsurePanelAbility;
use App\Modules\Tenancy\Http\Middleware\ResolveDefaultOrganization;
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
        // O nginx do app só é alcançável pelo Nginx Proxy Manager / Cloudflare.
        $middleware->trustProxies(at: '*');

        // A organização ativa precisa estar definida ANTES da resolução de {team}, {season}...
        // pelas rotas (route model binding), senão o escopo da organização não vale nelas.
        $middleware->web(prepend: [
            ResolveDefaultOrganization::class,
        ], append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // A API pública também precisa da organização ativa antes da resolução dos {parâmetros}.
        $middleware->api(prepend: [
            ResolveDefaultOrganization::class,
        ]);

        $middleware->alias([
            'panel.member' => EnsureMember::class,
            'panel.can' => EnsurePanelAbility::class,
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
