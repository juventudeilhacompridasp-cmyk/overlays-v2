<?php

namespace App\Modules\Matches\Reading;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Leitor de foto/PDF por IA com visão (Claude). Envia ao provedor SÓ o arquivo e o contexto mínimo (esporte e nomes das
 * equipes) — nada de cookies, tokens ou dados da plataforma — e manda ignorar qualquer documento pessoal.
 */
final class VisionSheetReader implements SheetReader
{
    private const PROMPT = <<<'PROMPT'
Você lê a súmula de uma partida esportiva (foto ou PDF, em português do Brasil) e devolve SOMENTE um objeto JSON, sem texto antes ou depois, neste formato:

{
  "home_team": string|null, "away_team": string|null,
  "home_score": int|null, "away_score": int|null,
  "lineups": [{"team": "home"|"away", "name": string|null, "shirt": int|null, "starter": bool|null, "confidence": number}],
  "events": [{"type": "goal"|"own_goal"|"yellow_card"|"red_card"|"substitution"|"foul"|"timeout"|"point"|"other", "team": "home"|"away", "name": string|null, "shirt": int|null, "minute": string|null, "detail": string|null, "confidence": number}],
  "notes": string|null,
  "confidence": number
}

Regras:
- "home" é a equipe mandante (primeira) e "away" a visitante.
- Se um campo estiver ilegível, ausente ou você tiver dúvida, use null. NUNCA invente nomes, números ou minutos.
- "confidence" vai de 0 a 1 e diz o quanto você tem certeza daquela linha (0.9+ = nítido; abaixo de 0.6 = duvidoso).
- Em substituição, "name" é quem entra e "detail" é quem sai. "minute" é texto (ex.: "23", "45+2").
- IGNORE e NÃO copie CPF, RG, números de documento, endereço, telefone, e-mail ou assinaturas. Eles não fazem parte do esquema.
- Em "notes" resuma só observações do jogo (ex.: atraso, ocorrência, W.O.), em até 500 caracteres.
PROMPT;

    public function name(): string
    {
        return 'vision';
    }

    public function read(string $contents, string $mime, array $context = []): ExtractedSheet
    {
        $key = config('services.anthropic.key');

        if (blank($key)) {
            throw new SheetReadFailed('A leitura por IA não está configurada neste ambiente.');
        }

        $block = $mime === 'application/pdf' ? 'document' : 'image';
        $hint = 'Esporte: '.($context['sport'] ?? 'não informado').'. Mandante: '.($context['home'] ?? 'não informado').'. Visitante: '.($context['away'] ?? 'não informado').'.';

        try {
            $response = Http::withHeaders(['x-api-key' => (string) $key, 'anthropic-version' => '2023-06-01'])
                ->timeout(120)
                ->post((string) config('services.anthropic.url'), [
                    'model' => config('services.anthropic.model'),
                    'max_tokens' => 4096,
                    'system' => self::PROMPT,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => $block, 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($contents)]],
                            ['type' => 'text', 'text' => $hint.' Leia a súmula e devolva o JSON.'],
                        ],
                    ]],
                ]);
        } catch (ConnectionException) {
            throw new SheetReadFailed('Não foi possível falar com o serviço de leitura. Tente novamente em instantes.');
        }

        if ($response->failed()) {
            throw new SheetReadFailed("O serviço de leitura respondeu com erro (HTTP {$response->status()}). Tente novamente mais tarde.");
        }

        $text = collect((array) $response->json('content'))->where('type', 'text')->pluck('text')->implode('');
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $data = $start !== false && $end !== false ? json_decode(substr($text, $start, $end - $start + 1), true) : null;

        if (! is_array($data)) {
            throw new SheetReadFailed('Não foi possível entender a resposta da leitura. Tente novamente ou use uma foto mais nítida.');
        }

        return ExtractedSheet::fromArray($data);
    }
}
