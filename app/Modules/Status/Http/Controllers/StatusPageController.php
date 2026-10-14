<?php

namespace App\Modules\Status\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Status\Support\StatusSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/** Página pública de status da plataforma (sem login, sem organização no conteúdo): HTML, JSON e feed Atom. */
class StatusPageController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Status/Index', ['status' => StatusSummary::get(), 'json_url' => route('status.json')]);
    }

    public function json(): JsonResponse
    {
        return response()->json(StatusSummary::get(), 200, [
            'Cache-Control' => 'public, max-age=30',
            'Access-Control-Allow-Origin' => '*',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Feed Atom dos incidentes e manutenções recentes (um item por incidente; o conteúdo é a linha do tempo). */
    public function atom(): Response
    {
        $status = StatusSummary::get();
        $items = [...$status['incidents'], ...$status['maintenance']];
        usort($items, fn (array $a, array $b) => strcmp($b['updates'][0]['at'] ?? $b['starts_at'], $a['updates'][0]['at'] ?? $a['starts_at']));

        $e = fn (string $value) => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<feed xmlns="http://www.w3.org/2005/Atom">'."\n";
        $xml .= '  <title>'.$e(config('app.name').' · Status').'</title>'."\n";
        $xml .= '  <id>'.$e(route('status.index')).'</id>'."\n";
        $xml .= '  <link rel="self" href="'.$e(route('status.atom')).'"/>'."\n";
        $xml .= '  <link rel="alternate" href="'.$e(route('status.index')).'"/>'."\n";
        $xml .= '  <updated>'.$e($items[0]['updates'][0]['at'] ?? $status['updated_at']).'</updated>'."\n";

        foreach ($items as $item) {
            $body = implode("\n", array_map(fn (array $update) => '['.$update['state_label'].'] '.$update['body'], $item['updates']));
            $xml .= "  <entry>\n";
            $xml .= '    <title>'.$e($item['title'].' — '.$item['state_label']).'</title>'."\n";
            $xml .= '    <id>'.$e(route('status.index').'#'.$item['id']).'</id>'."\n";
            $xml .= '    <link rel="alternate" href="'.$e(route('status.index')).'"/>'."\n";
            $xml .= '    <updated>'.$e($item['updates'][0]['at'] ?? $item['starts_at']).'</updated>'."\n";
            $xml .= '    <summary type="text">'.$e($body).'</summary>'."\n";
            $xml .= "  </entry>\n";
        }

        return response($xml.'</feed>', 200, ['Content-Type' => 'application/atom+xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=60']);
    }
}
