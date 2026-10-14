import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/lineupStage.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'lineup-stage-')), 'lineupStage.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { LINEUP_TRANSITIONS, PACKAGE_ANIMATIONS, cardLeaveDelay, cardLeaveTotal, packageEnterClass, packageExitClass, packageTiming, stageMasked, stageEnterClass, stageExitClass, stageKey, transitionTiming } = await import(out);

test('every transition has a distinct enter and exit class and a timing', () => {
    assert.equal(new Set(LINEUP_TRANSITIONS.map((item) => item.key)).size, 8);
    for (const { key } of LINEUP_TRANSITIONS) {
        assert.equal(stageEnterClass(key), `lineup-stage-in-${key}`);
        assert.equal(stageExitClass(key), `lineup-stage-out-${key}`);
        const timing = transitionTiming(key, 'dynamic');
        assert.ok(timing.enter > 0 && timing.exit > 0 && timing.exit <= timing.enter);
    }
});

test('motion off swaps instantly and subtle motion is faster than dynamic', () => {
    assert.deepEqual(transitionTiming('slide', 'none'), { exit: 0, enter: 0 });
    assert.ok(transitionTiming('slide', 'subtle').enter < transitionTiming('slide', 'dynamic').enter);
});

test('the stage key changes with the team, the kind and the player', () => {
    assert.notEqual(stageKey({ kind: 'grid', index: 0 }, 'home'), stageKey({ kind: 'formation', index: 0 }, 'home'));
    assert.notEqual(stageKey({ kind: 'individual', index: 1 }, 'home'), stageKey({ kind: 'individual', index: 2 }, 'home'));
    assert.notEqual(stageKey({ kind: 'grid', index: 0 }, 'home'), stageKey({ kind: 'grid', index: 0 }, 'away'));
});

test('speed scales every animation and the package can be turned off', () => {
    assert.ok(transitionTiming('zoom', 'dynamic', 200).enter < transitionTiming('zoom', 'dynamic', 100).enter);
    assert.ok(transitionTiming('zoom', 'dynamic', 50).enter > transitionTiming('zoom', 'dynamic', 100).enter);
    assert.equal(transitionTiming('zoom', 'dynamic', 999).enter, transitionTiming('zoom', 'dynamic', 200).enter);
    assert.deepEqual(packageTiming('none', 'dynamic'), { exit: 0, enter: 0 });
    assert.deepEqual(packageTiming('rise', 'none'), { exit: 0, enter: 0 });
    assert.ok(packageTiming('curtain', 'dynamic').exit > 0);
    assert.deepEqual(PACKAGE_ANIMATIONS.map((item) => item.key), ['none', ...LINEUP_TRANSITIONS.map((item) => item.key)]);
});

test('package classes, masks and the cascade exit of the cards', () => {
    assert.equal(packageEnterClass('none'), '');
    assert.equal(packageExitClass('none'), '');
    assert.equal(packageEnterClass('curtain'), 'lineup-pkg-in-curtain');
    assert.equal(packageExitClass('rise'), 'lineup-pkg-out-rise');
    assert.deepEqual(LINEUP_TRANSITIONS.filter((item) => stageMasked(item.key)).map((item) => item.key), ['wipe', 'curtain']);
    // O último cartão a entrar é o primeiro a sair; só a cascata sai em sequência.
    assert.equal(cardLeaveDelay('cascade', 10, 11), 0);
    assert.equal(cardLeaveDelay('cascade', 0, 11), 400);
    assert.equal(cardLeaveDelay('slide', 3, 11), null);
    assert.equal(cardLeaveTotal('slide', 11), 0);
    assert.equal(cardLeaveTotal('cascade', 11), 11 * 40 + 240);
    assert.equal(cardLeaveTotal('cascade', 80), 20 * 40 + 240);
});
