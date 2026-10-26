<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Recebe os relatórios de violação da Content-Security-Policy (docs/17 §5). Sem login e sem CSRF (o navegador envia sozinho).
 * Guarda no log só diretivas e origens bloqueadas, sem query string, IP ou conteúdo; o corpo é limitado e nunca devolvido.
 */
final class CspReportController
{
    private const MAX_BYTES = 8192;

    public function __invoke(Request $request): Response
    {
        if ((bool) config('platform.security.csp.log_reports', true) && strlen($request->getContent()) <= self::MAX_BYTES) {
            $data = json_decode($request->getContent(), true);
            $report = is_array($data) ? ($data['csp-report'] ?? $data[0]['body'] ?? $data) : [];

            if (is_array($report)) {
                Log::notice('csp.violation', [
                    'directive' => $this->clean($report['effective-directive'] ?? $report['violated-directive'] ?? $report['effectiveDirective'] ?? ''),
                    'blocked' => $this->clean($report['blocked-uri'] ?? $report['blockedURL'] ?? ''),
                    'page' => $this->clean($report['document-uri'] ?? $report['documentURL'] ?? ''),
                ]);
            }
        }

        return response()->noContent();
    }

    private function clean(mixed $value): string
    {
        $text = is_string($value) ? $value : '';
        $text = (string) preg_replace('/[?#].*$/', '', $text);

        return mb_substr((string) preg_replace('/[^\x20-\x7E]/', '', $text), 0, 200);
    }
}
