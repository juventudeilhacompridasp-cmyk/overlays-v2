import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/officials.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'officials-')), 'officials.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const o = await import(out);

const roles = [{ value: 'referee' }, { value: 'scorer' }];

test('duplicated acha a mesma pessoa em duas funções e ignora vagas livres', () => {
    assert.deepEqual(o.duplicated({ referee: 'a', scorer: 'b' }), []);
    assert.deepEqual(o.duplicated({ referee: 'a', scorer: 'a' }), ['a']);
    assert.deepEqual(o.duplicated({ referee: '', scorer: '' }), []);
});

test('sameSelection trata vazio e ausente como iguais', () => {
    assert.equal(o.sameSelection({ referee: 'a' }, { referee: 'a', scorer: '' }, roles), true);
    assert.equal(o.sameSelection({ referee: 'a' }, { referee: 'b' }, roles), false);
});

test('openGames só devolve jogos sem árbitro que ainda aceitam escala', () => {
    const games = [
        { id: 1, locked: false, assignments: {} },
        { id: 2, locked: false, assignments: { referee: 'a' } },
        { id: 3, locked: true, assignments: {} },
    ];

    assert.deepEqual(o.openGames(games).map((game) => game.id), [1]);
});
