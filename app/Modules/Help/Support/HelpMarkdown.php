<?php

namespace App\Modules\Help\Support;

use Closure;

/**
 * Renderizador seguro do Markdown da ajuda: devolve blocos estruturados para o React desenhar (nunca HTML).
 * Aceita `## título`, `### subtítulo`, parágrafos, listas (`- ` e `1. `), `> aviso`, **negrito**, `código` e links
 * `[texto](help:slug)` / `[texto](route:nome.da.rota)`; link que não resolve vira texto simples.
 */
final class HelpMarkdown
{
    /**
     * @param  Closure(string): ?string  $resolveLink  recebe `help:slug` ou `route:nome` e devolve a URL (ou null)
     * @return list<array<string, mixed>>
     */
    public static function render(string $markdown, Closure $resolveLink): array
    {
        $blocks = [];
        $paragraph = [];
        $list = null;

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            $line = rtrim($line);

            if ($line === '') {
                self::flush($blocks, $paragraph, $list, $resolveLink);

                continue;
            }

            if (preg_match('/^(#{2,3})\s+(.+)$/', $line, $m)) {
                self::flush($blocks, $paragraph, $list, $resolveLink);
                $blocks[] = ['type' => strlen($m[1]) === 2 ? 'h2' : 'h3', 'text' => trim($m[2])];
            } elseif (preg_match('/^>\s?(.*)$/', $line, $m)) {
                self::flush($blocks, $paragraph, $list, $resolveLink);
                $blocks[] = ['type' => 'note', 'inline' => self::inline($m[1], $resolveLink)];
            } elseif (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
                if ($list === null || $list['type'] !== 'ul') {
                    self::flush($blocks, $paragraph, $list, $resolveLink);
                    $list = ['type' => 'ul', 'items' => []];
                }
                $list['items'][] = $m[1];
            } elseif (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
                if ($list === null || $list['type'] !== 'ol') {
                    self::flush($blocks, $paragraph, $list, $resolveLink);
                    $list = ['type' => 'ol', 'items' => []];
                }
                $list['items'][] = $m[1];
            } else {
                if ($list !== null) {
                    self::flush($blocks, $paragraph, $list, $resolveLink);
                }
                $paragraph[] = trim($line);
            }
        }

        self::flush($blocks, $paragraph, $list, $resolveLink);

        return $blocks;
    }

    /**
     * Fecha o parágrafo ou a lista em andamento e põe nos blocos.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<string>  $paragraph
     * @param  array{type: string, items: list<string>}|null  $list
     * @param  Closure(string): ?string  $resolveLink
     */
    private static function flush(array &$blocks, array &$paragraph, ?array &$list, Closure $resolveLink): void
    {
        if ($paragraph !== []) {
            $blocks[] = ['type' => 'p', 'inline' => self::inline(implode(' ', $paragraph), $resolveLink)];
            $paragraph = [];
        }

        if ($list !== null) {
            $blocks[] = ['type' => $list['type'], 'items' => array_map(fn (string $item) => self::inline($item, $resolveLink), $list['items'])];
            $list = null;
        }
    }

    /** Texto puro de um trecho (sem símbolos de Markdown), para busca e resumo. */
    public static function plain(string $markdown): string
    {
        $text = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $markdown);
        $text = (string) preg_replace('/[*`>#]+/', '', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * @param  Closure(string): ?string  $resolveLink
     * @return list<array{t: string, v: string, href?: string}>
     */
    private static function inline(string $text, Closure $resolveLink): array
    {
        $parts = [];
        $offset = 0;

        preg_match_all('/\*\*(.+?)\*\*|`(.+?)`|\[(.+?)\]\((.+?)\)/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $start = $match[0][1];

            if ($start > $offset) {
                $parts[] = ['t' => 'text', 'v' => substr($text, $offset, $start - $offset)];
            }

            if (isset($match[1]) && $match[1][1] >= 0) {
                $parts[] = ['t' => 'b', 'v' => $match[1][0]];
            } elseif (isset($match[2]) && $match[2][1] >= 0) {
                $parts[] = ['t' => 'code', 'v' => $match[2][0]];
            } else {
                $href = $resolveLink($match[4][0]);
                $parts[] = $href === null ? ['t' => 'text', 'v' => $match[3][0]] : ['t' => 'link', 'v' => $match[3][0], 'href' => $href];
            }

            $offset = $start + strlen($match[0][0]);
        }

        if ($offset < strlen($text)) {
            $parts[] = ['t' => 'text', 'v' => substr($text, $offset)];
        }

        return $parts;
    }
}
