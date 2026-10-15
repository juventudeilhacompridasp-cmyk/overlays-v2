<?php

namespace App\Modules\ApiDocs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ApiDocs\Support\ApiChangelog;
use App\Modules\ApiDocs\Support\OpenApiBuilder;
use App\Modules\ApiDocs\Support\PostmanExporter;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Referência interna das rotas (ADR 0007). Só o Super Admin (`platform.admin`; os demais recebem 404). */
class ApiDocsController extends Controller
{
    public function index(OpenApiBuilder $builder): Response
    {
        $operations = OpenApiBuilder::operations($builder->build());

        return Inertia::render('Platform/ApiDocs/Index', [
            'tags' => OpenApiBuilder::tagNames(),
            'operations' => $operations,
            'documented' => count(array_filter($operations, fn (array $operation) => $operation['x-documented'])),
            'version' => ApiChangelog::current(),
            'changelog' => ApiChangelog::entries(),
        ]);
    }

    /** Coleção do Postman (v2.1) gerada da mesma especificação; sem segredos (tokens ficam como variáveis vazias). */
    public function postman(OpenApiBuilder $builder): JsonResponse
    {
        return response()->json(PostmanExporter::fromSpec($builder->build()), 200, [
            'Content-Disposition' => 'attachment; filename="overlays-v2.postman_collection.json"',
            'Cache-Control' => 'no-store',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function openapi(OpenApiBuilder $builder): JsonResponse
    {
        return response()->json($builder->build(), 200, [
            'Content-Disposition' => 'attachment; filename="overlays-v2-openapi.json"',
            'Cache-Control' => 'no-store',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
