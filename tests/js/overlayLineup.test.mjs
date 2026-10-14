import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/overlayLineup.ts', import.meta.url), 'utf8').replace(/^import .*$/gm, '');
const out = join(mkdtempSync(join(tmpdir(), 'overlay-lineup-')), 'overlayLineup.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { currentStep, individuals, nextChangeAt, stepCount, stepStarts } = await import(out);

const settings = (extra = {}) => ({ presentation: 'show', list_seconds: 5, team: 'home', individual_mode: 'auto', individual_seconds: 6, individual_scope: 'starters', sponsor_banner: false, step: 0, anchor_ms: 1000, ...extra });
const side = {
    starters: [{ shirt: 6, name: 'Joãozinho', position: 'LAT', position_label: 'Lateral', captain: false, photo_url: null, photo_large_url: 'x' }, { shirt: 3, name: 'Renan', position: 'ZAG', position_label: 'Zagueiro', captain: true, photo_url: null, photo_large_url: null }],
    bench: [{ shirt: 12, name: 'Reserva', captain: false, photo_url: null, photo_large_url: null }],
    coach: 'Cléristón',
};

test('the individual presentation walks the starters, optionally the bench, and ends with the coach', () => {
    assert.deepEqual(individuals(side, 'starters').map((item) => item.name), ['Joãozinho', 'Renan', 'Cléristón']);
    assert.deepEqual(individuals(side, 'all').map((item) => item.name), ['Joãozinho', 'Renan', 'Reserva', 'Cléristón']);
    assert.equal(individuals(side, 'starters').at(-1).kind, 'coach');
    assert.equal(individuals({ ...side, coach: null }, 'starters').length, 2);
});

test('show has one more step than players (the grid first); individual has exactly one per player', () => {
    assert.equal(stepCount('show', 3), 4);
    assert.equal(stepCount('individual', 3), 3);
    assert.equal(stepCount('individual', 0), 1);
    assert.equal(stepCount('cards', 9), 1);
    assert.deepEqual(stepStarts(settings(), 3), [0, 5000, 11000, 17000]);
    assert.deepEqual(stepStarts(settings({ presentation: 'individual' }), 3), [0, 6000, 12000]);
});

test('automatic mode follows the server clock and every screen agrees', () => {
    const at = (offset, extra = {}) => currentStep(settings(extra), 3, 1000 + offset);

    assert.equal(at(0), 0);
    assert.equal(at(4999), 0);
    assert.equal(at(5000), 1);
    assert.equal(at(10999), 1);
    assert.equal(at(11000), 2);
    assert.equal(at(17000), 3);
    assert.equal(at(22999), 3);
    assert.equal(at(23000), 0, 'show returns to the grid and holds');
    assert.equal(at(99999999), 0);
    assert.equal(at(99999999, { presentation: 'individual' }), 2, 'individual holds the last player');
    assert.equal(currentStep(settings(), 3, 0), 0, 'a clock slightly behind the anchor does not break');
});

test('manual mode uses the step the operator chose, kept inside the limit', () => {
    assert.equal(currentStep(settings({ individual_mode: 'manual', step: 2 }), 3, 5000000), 2);
    assert.equal(currentStep(settings({ individual_mode: 'manual', step: 99 }), 3, 0), 3);
    assert.equal(currentStep(settings({ individual_mode: 'manual', step: -4 }), 3, 0), 0);
});

test('only cards, list and the other two-team modes have a single fixed step', () => {
    assert.equal(currentStep(settings({ presentation: 'cards' }), 3, 99999), 0);
});

test('the screen is told when the step changes, and never in manual mode', () => {
    assert.equal(nextChangeAt(settings(), 3, 1000 + 4000), 1000 + 5000);
    assert.equal(nextChangeAt(settings(), 3, 1000 + 5000), 1000 + 11000);
    assert.equal(nextChangeAt(settings(), 3, 1000 + 17000), 1000 + 23000);
    assert.equal(nextChangeAt(settings(), 3, 1000 + 99999), null);
    assert.equal(nextChangeAt(settings({ individual_mode: 'manual' }), 3, 1000), null);
    assert.equal(nextChangeAt(settings({ presentation: 'cards' }), 3, 1000), null);
});
