<?php

namespace App\Modules\Help\Support;

use Illuminate\Support\Str;

/**
 * Busca textual nos artigos (sem IA e sem serviço externo): ignora acento e caixa, tolera um erro de digitação em
 * palavras longas e dá mais peso a título e tags do que ao corpo. O acervo é pequeno e versionado, então o índice
 * é montado na hora (e a busca funciona igual em qualquer banco).
 */
final class HelpSearch
{
    private const STOPWORDS = ['de', 'da', 'do', 'das', 'dos', 'e', 'o', 'a', 'os', 'as', 'um', 'uma', 'para', 'por', 'como', 'em', 'no', 'na', 'nos', 'nas', 'que', 'com', 'se', 'ao', 'eu', 'meu', 'minha'];

    /**
     * @param  list<HelpArticle>  $articles
     * @return list<array{article: HelpArticle, score: int, snippet: string}>
     */
    public static function search(array $articles, string $query): array
    {
        $terms = self::terms($query);

        if ($terms === []) {
            return [];
        }

        $results = [];

        foreach ($articles as $article) {
            $score = self::score($article, $terms);

            if ($score > 0) {
                $results[] = ['article' => $article, 'score' => $score, 'snippet' => self::snippet($article, $terms)];
            }
        }

        usort($results, fn (array $a, array $b) => [$b['score'], $a['article']->title] <=> [$a['score'], $b['article']->title]);

        return $results;
    }

    /**
     * Palavras da consulta, sem acento, sem palavras vazias e no singular aproximado.
     *
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $terms = [];

        foreach ($words as $word) {
            if (strlen($word) < 2 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }

            $terms[] = self::stem($word);
        }

        return array_values(array_unique($terms));
    }

    /** Singular aproximado: "inscrições" → "inscricao", "gestores" → "gestor", "regras" → "regra". */
    private static function stem(string $word): string
    {
        return match (true) {
            strlen($word) > 4 && str_ends_with($word, 'oes') => substr($word, 0, -3).'ao',
            strlen($word) > 5 && str_ends_with($word, 'es') => substr($word, 0, -2),
            strlen($word) > 3 && str_ends_with($word, 's') => substr($word, 0, -1),
            default => $word,
        };
    }

    /**
     * @param  list<string>  $terms
     */
    private static function score(HelpArticle $article, array $terms): int
    {
        $title = self::terms($article->title);
        $tags = self::terms(implode(' ', $article->tags));
        $category = self::terms($article->category);
        $headings = self::terms(implode(' ', self::headings($article->body)));
        $body = self::terms($article->body);
        $bodyText = Str::lower(Str::ascii($article->body));

        $score = 0;
        $matched = 0;

        foreach ($terms as $term) {
            $points = 0;
            $points += self::has($title, $term) ? 6 : 0;
            $points += self::has($tags, $term) ? 5 : 0;
            $points += self::has($category, $term) ? 2 : 0;
            $points += self::has($headings, $term) ? 3 : 0;
            $points += min(5, substr_count($bodyText, $term));

            // Erro de digitação: uma letra de diferença em palavra longa, no título, nas tags ou nos títulos de seção.
            if ($points === 0 && strlen($term) >= 5) {
                foreach ([...$title, ...$tags, ...$headings, ...$body] as $word) {
                    if (abs(strlen($word) - strlen($term)) <= 1 && levenshtein($word, $term) <= 1) {
                        $points = 2;
                        break;
                    }
                }
            }

            if ($points > 0) {
                $matched++;
                $score += $points;
            }
        }

        // Acertar todas as palavras da busca vale mais do que acertar só uma.
        return $matched === count($terms) ? $score + 5 : $score;
    }

    /**
     * @param  list<string>  $words
     */
    private static function has(array $words, string $term): bool
    {
        foreach ($words as $word) {
            if ($word === $term || (strlen($term) >= 4 && str_starts_with($word, $term))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function headings(string $body): array
    {
        preg_match_all('/^#{2,3}\s+(.+)$/m', $body, $matches);

        return $matches[1];
    }

    /**
     * @param  list<string>  $terms
     */
    private static function snippet(HelpArticle $article, array $terms): string
    {
        $lines = array_values(array_filter(array_map(fn (string $line) => HelpMarkdown::plain(preg_replace('/^(\d+\.|[-*])\s+/', '', $line) ?? $line), preg_split('/\R/', $article->body) ?: []), fn (string $line) => $line !== ''));

        foreach ($lines as $line) {
            $normalized = Str::lower(Str::ascii($line));

            foreach ($terms as $term) {
                if (str_contains($normalized, $term)) {
                    return Str::limit($line, 180);
                }
            }
        }

        return Str::limit($lines[0] ?? '', 180);
    }
}
