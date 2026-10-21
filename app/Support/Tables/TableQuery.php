<?php

namespace App\Support\Tables;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Busca, filtros, ordenação e tamanho de página das listas dinâmicas (modo servidor do `DataTable`), SEMPRE por lista branca:
 * o que vem da URL (`q`, `sort`, `dir`, `per_page`, `filter[chave]`) nunca vira nome de coluna nem SQL solto.
 *
 * Uso: `$table = TableQuery::fromRequest($request, ['name', 'city'], 'name', ['city']);` e depois `search`, `filter` e `sort`
 * no builder; `paginate($table->perPage)` e `'filters' => $table->props()` para a tela.
 */
final class TableQuery
{
    public const PER_PAGE = [10, 15, 25, 50, 100];

    /** @param  array<string, string>  $filters */
    private function __construct(
        public readonly string $q,
        public readonly string $sort,
        public readonly string $dir,
        public readonly int $perPage,
        public readonly array $filters,
    ) {}

    /**
     * @param  list<string>  $sorts  nomes de ordenação aceitos (o primeiro valor de `$defaultSort` deve estar aqui)
     * @param  list<string>  $filterKeys  chaves de `filter[...]` aceitas
     */
    public static function fromRequest(Request $request, array $sorts, string $defaultSort, array $filterKeys = [], string $defaultDir = 'asc', int $defaultPerPage = 15): self
    {
        $sort = (string) $request->query('sort', '');
        $dir = strtolower((string) $request->query('dir', ''));
        $perPage = (int) $request->query('per_page', $defaultPerPage);
        $raw = $request->query('filter', []);
        $filters = [];

        foreach ($filterKeys as $key) {
            $value = is_array($raw) ? ($raw[$key] ?? '') : '';

            if (is_string($value) && $value !== '' && mb_strlen($value) <= 64) {
                $filters[$key] = $value;
            }
        }

        $valid = in_array($sort, $sorts, true);

        return new self(
            q: mb_substr(trim((string) $request->query('q', '')), 0, 100),
            sort: $valid ? $sort : $defaultSort,
            // Sem `sort` válido a direção é a padrão (o `dir` sozinho não significa nada).
            dir: $valid && in_array($dir, ['asc', 'desc'], true) ? $dir : $defaultDir,
            perPage: in_array($perPage, self::PER_PAGE, true) ? $perPage : $defaultPerPage,
            filters: $filters,
        );
    }

    /**
     * Todas as palavras precisam aparecer (em qualquer das colunas), sem diferenciar maiúsculas. `$extra` soma uma condição
     * alternativa (ex.: achar uma pessoa pelo hash do CPF digitado por inteiro).
     *
     * @param  list<string>  $columns  nomes de coluna ESCRITOS NO CÓDIGO (nunca da requisição)
     * @param  Closure(Builder): void|null  $extra
     */
    public function search(Builder $query, array $columns, ?Closure $extra = null): Builder
    {
        $terms = preg_split('/\s+/', mb_strtolower($this->q), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($terms === [] || $columns === []) {
            return $query;
        }

        $matches = function (Builder $group) use ($terms, $columns): void {
            foreach ($terms as $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';

                $group->where(function (Builder $inner) use ($columns, $like): void {
                    foreach ($columns as $column) {
                        $inner->orWhereRaw("lower({$column}) like ? escape '\\'", [$like]);
                    }
                });
            }
        };

        if ($extra === null) {
            return $query->where($matches);
        }

        return $query->where(function (Builder $outer) use ($matches, $extra): void {
            $outer->where($matches)->orWhere(fn (Builder $alternative) => $extra($alternative));
        });
    }

    /**
     * @param  array<string, Closure(Builder, string): mixed>  $handlers  chave do filtro → como aplicar o valor escolhido
     */
    public function filter(Builder $query, array $handlers): Builder
    {
        foreach ($this->filters as $key => $value) {
            if (isset($handlers[$key])) {
                $handlers[$key]($query, $value);
            }
        }

        return $query;
    }

    /**
     * Ordena pela lista branca (`nome da ordenação` → coluna/alias). O `id` desempata, para a paginação não repetir nem pular
     * linhas. Opções do item (use o formato array):
     * - `text`: ignora maiúsculas/minúsculas (`lower(coluna)`);
     * - `nullable`: vazios vão para o fim nos dois sentidos (NÃO use em alias de `withCount`: o Postgres não deixa usar o
     *   alias dentro de uma expressão);
     * - `inverted`: coluna "ao contrário" (idade crescente = nascimento decrescente);
     * - `raw`: expressão SQL ESCRITA NO CÓDIGO no lugar da coluna (ex.: `case status when ... end`), nunca vinda da requisição;
     * - `then`: ordenações seguintes, `[[coluna, 'asc'|'desc'], ...]`.
     *
     * @param  array<string, string|array{column?: string, raw?: string, text?: bool, nullable?: bool, inverted?: bool, then?: list<array{string, string}>}>  $map
     */
    public function sort(Builder $query, array $map): Builder
    {
        $entry = $map[$this->sort] ?? reset($map);
        $options = is_array($entry) ? $entry : ['column' => $entry];
        $column = $options['column'] ?? '';
        $direction = ($this->dir === 'asc') !== ($options['inverted'] ?? false) ? 'asc' : 'desc';

        if (isset($options['raw'])) {
            $query->orderByRaw("({$options['raw']}) {$direction}");
        } else {
            if ($options['nullable'] ?? false) {
                $query->orderByRaw("({$column} is null) asc");
            }

            if ($options['text'] ?? false) {
                $query->orderByRaw("lower({$column}) {$direction}");
            } else {
                $query->orderBy($column, $direction);
            }
        }

        foreach ($options['then'] ?? [] as [$thenColumn, $thenDirection]) {
            $query->orderBy($thenColumn, $thenDirection === 'desc' ? 'desc' : 'asc');
        }

        return $query->orderBy('id');
    }

    /** O que a tela precisa para desenhar o estado atual (e montar a próxima URL). @return array<string, mixed> */
    public function props(): array
    {
        return ['q' => $this->q, 'sort' => $this->sort, 'dir' => $this->dir, 'per_page' => $this->perPage, 'values' => (object) $this->filters];
    }
}
