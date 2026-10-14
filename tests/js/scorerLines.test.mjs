import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/scorerLines.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'scorers-')), 'scorerLines.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { scorerLines } = await import(out);

const goal = (side, player, minute, own_goal = false) => ({ side, player, minute, own_goal });

test('groups the goals of each side by player, in order', () => {
    const goals = [goal('home', 'Rafa', 12), goal('away', 'Zé', 20), goal('home', 'Pepê', 30), goal('home', 'Rafa', 55)];

    assert.deepEqual(scorerLines(goals, 'home'), ["Rafa 12', 55'", "Pepê 30'"]);
    assert.deepEqual(scorerLines(goals, 'away'), ["Zé 20'"]);
});

test('marks own goals, tolerates unknown scorers and missing minutes', () => {
    const goals = [goal('home', 'Beto', 7, true), goal('home', null, null), goal(null, 'X', 1)];

    assert.deepEqual(scorerLines(goals, 'home'), ["Beto (contra) 7'", 'Gol']);
    assert.deepEqual(scorerLines([], 'away'), []);
});
