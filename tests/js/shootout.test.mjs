import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/shootout.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'shootout-')), 'shootout.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { isSuddenDeath, shootoutCaption, shootoutDots, shootoutRows, shootoutScore } = await import(out);

const data = (home, away, extra = {}) => ({ home, away, goals: { home: home.filter(Boolean).length, away: away.filter(Boolean).length }, first: 'home', next: 'away', decided: false, winner: null, rounds: 5, by_kick: true, ...extra });
const names = { home: 'Leões', away: 'Tigres' };

test('the regular kicks are always five dots and the missing ones are pending', () => {
    assert.deepEqual(shootoutDots([true, false], 5), ['goal', 'miss', 'pending', 'pending', 'pending']);
    assert.deepEqual(shootoutDots([], 5), ['pending', 'pending', 'pending', 'pending', 'pending']);
});

test('sudden death adds one dot per round and both rows stay aligned', () => {
    const rows = shootoutRows(data([true, true, true, true, true, true], [true, true, true, true, true]));
    assert.equal(rows.home.length, 6);
    assert.equal(rows.away.length, 6);
    assert.deepEqual(rows.away.slice(4), ['goal', 'pending']);
});

test('the caption follows the shootout situation', () => {
    assert.equal(shootoutCaption(data([], [], { next: null, first: null }), names), 'Aguardando a primeira cobrança');
    assert.equal(shootoutCaption(data([true], []), names), 'Cobra: Tigres');
    assert.equal(shootoutCaption(data([true, true, true], [false, false, false], { decided: true, winner: 'home', next: null }), names), 'Leões vence nos pênaltis');
    assert.equal(shootoutCaption(data([true, true, true, true, true, true], [true, true, true, true, true, true], { next: 'home' }), names), 'Morte súbita');
    assert.equal(isSuddenDeath(data([true, true, true, true, true], [true, true, true, true, true])), false);
});

test('the score text', () => {
    assert.equal(shootoutScore(data([true, true, false], [true])), '2 x 1');
});
