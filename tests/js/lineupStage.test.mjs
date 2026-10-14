import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/lineupStage.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'lineup-stage-')), 'lineupStage.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { LINEUP_TRANSITIONS, stageEnterClass, stageExitClass, stageKey, transitionTiming } = await import(out);

test('every transition has a distinct enter and exit class and a timing', () => {
    assert.equal(new Set(LINEUP_TRANSITIONS.map((item) => item.key)).size, 5);
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
