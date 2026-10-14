import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/overlayLineup.ts', import.meta.url), 'utf8').replace(/^import .*$/gm, '');
const out = join(mkdtempSync(join(tmpdir(), 'overlay-lineup-')), 'overlayLineup.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { currentStep, individuals, lineupPlan, nextChangeAt, stepContent, stepCount, stepStarts } = await import(out);

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

const full = (extra = {}) => settings({ presentation: 'full', list_seconds: 5, formation_seconds: 9, individual_seconds: 4, full_individuals: true, ...extra });

test('full plans the grid, the formation (only when ready) and each player, in the same order as the server', () => {
    assert.deepEqual(lineupPlan(full(), 3, true).map((step) => step.kind), ['grid', 'formation', 'individual', 'individual', 'individual']);
    assert.deepEqual(lineupPlan(full(), 3, false).map((step) => step.kind), ['grid', 'individual', 'individual', 'individual']);
    assert.deepEqual(lineupPlan(full({ full_individuals: false }), 3, true).map((step) => step.kind), ['grid', 'formation']);
    assert.deepEqual(stepStarts(full(), 3, true), [0, 5000, 14000, 18000, 22000]);
    assert.equal(stepCount('full', 3, { formation: true }), 5);
    assert.equal(stepCount('full', 3, { formation: true, individuals: false }), 2);
});

test('full runs on the server clock, goes back to the grid at the end and schedules the next change', () => {
    const base = full({ anchor_ms: 1000 });
    assert.equal(currentStep(base, 3, 1000 + 4_999, true), 0);
    assert.equal(currentStep(base, 3, 1000 + 5_000, true), 1);
    assert.equal(currentStep(base, 3, 1000 + 14_000, true), 2);
    assert.equal(currentStep(base, 3, 1000 + 23_000, true), 4);
    assert.equal(currentStep(base, 3, 1000 + 26_000, true), 0);
    assert.equal(nextChangeAt(base, 3, 1000 + 6_000, true), 1000 + 14_000);
    assert.equal(nextChangeAt(base, 3, 1000 + 23_000, true), 1000 + 26_000);
    assert.equal(nextChangeAt(base, 3, 1000 + 30_000, true), null);
    assert.equal(nextChangeAt(full({ individual_mode: 'manual' }), 3, 5000, true), null);
});

test('full in manual mode obeys the step and never leaves the range', () => {
    assert.equal(currentStep(full({ individual_mode: 'manual', step: 9 }), 3, 0, true), 4);
    assert.equal(currentStep(full({ individual_mode: 'manual', step: 1 }), 3, 0, true), 1);
});

test('stepContent says what to draw: grid, formation or the n-th player', () => {
    assert.deepEqual(stepContent(full(), 3, 0, true), { kind: 'grid', index: 0 });
    assert.deepEqual(stepContent(full(), 3, 1, true), { kind: 'formation', index: 0 });
    assert.deepEqual(stepContent(full(), 3, 2, true), { kind: 'individual', index: 0 });
    assert.deepEqual(stepContent(full(), 3, 4, true), { kind: 'individual', index: 2 });
    assert.deepEqual(stepContent(full(), 3, 1, false), { kind: 'individual', index: 0 });
    assert.deepEqual(stepContent(settings({ presentation: 'show' }), 3, 2, false), { kind: 'individual', index: 1 });
});
