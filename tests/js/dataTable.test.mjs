import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/dataTable.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'table-')), 'dataTable.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { compareValues, distinctOptions, filterRows, matchesQuery, nextSort, normalize, pageWindow, paginate, serverParams, sortRows, summaryText } = await import(out);

test('normalize ignores accents and case', () => {
    assert.equal(normalize('  FÁBIO Açaí '), 'fabio acai');
    assert.equal(normalize(null), '');
    assert.equal(normalize(12), '12');
});

test('compare sorts numbers by value, text naturally and empties last', () => {
    assert.ok(compareValues(2, 10) < 0);
    assert.ok(compareValues('Equipe 2', 'Equipe 10') < 0);
    assert.ok(compareValues('álamo', 'Beta') < 0);
    assert.ok(compareValues(null, 1) > 0);
    assert.ok(compareValues('', 'a') > 0);
    assert.equal(compareValues(null, undefined), 0);
});

test('sortRows is stable, does not mutate and keeps empties last in both directions', () => {
    const rows = [
        { id: 1, name: 'b', n: 2 },
        { id: 2, name: 'a', n: null },
        { id: 3, name: 'c', n: 2 },
        { id: 4, name: 'd', n: 1 },
    ];
    const copy = rows.slice();

    assert.deepEqual(sortRows(rows, (r) => r.n, 'asc').map((r) => r.id), [4, 1, 3, 2]);
    assert.deepEqual(sortRows(rows, (r) => r.n, 'desc').map((r) => r.id), [1, 3, 4, 2]);
    assert.deepEqual(rows, copy);
});

test('nextSort cycles asc, desc and back to the original order', () => {
    let state = null;
    state = nextSort(state, 'name');
    assert.deepEqual(state, { key: 'name', dir: 'asc' });
    state = nextSort(state, 'name');
    assert.deepEqual(state, { key: 'name', dir: 'desc' });
    assert.equal(nextSort(state, 'name'), null);
    assert.deepEqual(nextSort({ key: 'name', dir: 'desc' }, 'city'), { key: 'city', dir: 'asc' });
});

test('matchesQuery needs every word, in any order, ignoring accents', () => {
    const text = normalize('São Paulo FC · Capital');
    assert.ok(matchesQuery(text, 'paulo sao'));
    assert.ok(matchesQuery(text, ''));
    assert.ok(!matchesQuery(text, 'paulo rio'));
});

test('filterRows combines search and exact column filters', () => {
    const rows = [
        { name: 'Alfa', status: 'Ativo' },
        { name: 'Beta', status: 'Inativo' },
        { name: 'Álamo', status: 'Ativo' },
    ];
    const options = { searchText: (r) => r.name, filterValue: (r, key) => r[key] };

    assert.equal(filterRows(rows, { ...options, query: '', filters: {} }).length, 3);
    assert.deepEqual(filterRows(rows, { ...options, query: 'al', filters: {} }).map((r) => r.name), ['Alfa', 'Álamo']);
    assert.deepEqual(filterRows(rows, { ...options, query: 'al', filters: { status: 'Inativo' } }), []);
    assert.deepEqual(filterRows(rows, { ...options, query: '', filters: { status: 'Inativo' } }).map((r) => r.name), ['Beta']);
    assert.deepEqual(filterRows(rows, { ...options, query: '', filters: { status: '' } }).length, 3);
});

test('distinctOptions counts and sorts the values, skipping empties', () => {
    const options = distinctOptions([{ s: 'B' }, { s: 'a' }, { s: 'B' }, { s: null }, { s: '' }], (r) => r.s);
    assert.deepEqual(options, [
        { value: 'a', label: 'a', count: 1 },
        { value: 'B', label: 'B', count: 2 },
    ]);
});

test('paginate keeps the page inside the range and reports from/to', () => {
    assert.deepEqual(paginate(0, 1, 25), { page: 1, pages: 1, from: 0, to: 0, total: 0, size: 25 });
    assert.deepEqual(paginate(60, 3, 25), { page: 3, pages: 3, from: 51, to: 60, total: 60, size: 25 });
    assert.equal(paginate(10, 9, 25).page, 1);
    assert.equal(paginate(60, -4, 25).page, 1);
    assert.equal(paginate(5, 1, 0).size, 1);
});

test('pageWindow shows a short window with gaps', () => {
    assert.deepEqual(pageWindow(1, 3), [1, 2, 3]);
    assert.deepEqual(pageWindow(6, 20), [1, '…', 5, 6, 7, '…', 20]);
    assert.deepEqual(pageWindow(2, 20), [1, 2, 3, '…', 20]);
    assert.deepEqual(pageWindow(1, 1), [1]);
});

test('serverParams sends only what differs from the default', () => {
    const base = { q: '', sort: null, dir: 'asc', page: 1, per_page: 15, filters: {} };
    assert.deepEqual(serverParams(base, { per_page: 15 }), {});
    assert.deepEqual(serverParams({ ...base, q: '  lima ', sort: 'city', dir: 'desc', page: 3, per_page: 50, filters: { status: 'active', kind: '' } }, { per_page: 15 }), {
        q: 'lima',
        sort: 'city',
        dir: 'desc',
        per_page: 50,
        page: 3,
        'filter[status]': 'active',
    });
    // Ordenação igual ao padrão não vai na URL.
    assert.deepEqual(serverParams({ ...base, sort: 'name', dir: 'asc' }, { per_page: 15, sort: 'name', dir: 'asc' }), {});
});

test('summaryText says what is shown and when it is filtered', () => {
    assert.equal(summaryText(paginate(0, 1, 25), false), 'Nenhum registro.');
    assert.equal(summaryText(paginate(0, 1, 25), true), 'Nenhum resultado para os filtros.');
    assert.equal(summaryText(paginate(60, 2, 25), true), '26–50 de 60 (filtrado)');
});
