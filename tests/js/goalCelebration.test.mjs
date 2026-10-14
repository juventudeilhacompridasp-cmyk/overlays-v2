import assert from 'node:assert/strict';
import { test } from 'node:test';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/goalCelebration.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'goal-celebration-')), 'goalCelebration.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { scoredSide, celebrationPhase, celebrationDuration } = await import(out);

test('detects which side scored and ignores corrections and ties', () => {
    assert.equal(scoredSide({ home: 0, away: 0 }, { home: 1, away: 0 }), 'home');
    assert.equal(scoredSide({ home: 1, away: 1 }, { home: 1, away: 2 }), 'away');
    assert.equal(scoredSide({ home: 2, away: 1 }, { home: 1, away: 1 }), null);
    assert.equal(scoredSide({ home: 1, away: 1 }, { home: 1, away: 1 }), null);
    assert.equal(scoredSide({ home: 0, away: 0 }, { home: 1, away: 1 }), null);
});

test('goes from the word to the team and then stops', () => {
    assert.equal(celebrationPhase(0, 2, 2), 'word');
    assert.equal(celebrationPhase(1999, 2, 2), 'word');
    assert.equal(celebrationPhase(2000, 2, 2), 'team');
    assert.equal(celebrationPhase(3999, 2, 2), 'team');
    assert.equal(celebrationPhase(4000, 2, 2), null);
    assert.equal(celebrationPhase(2000, 2, 0), null);
    assert.equal(celebrationPhase(-1, 2, 2), null);
    assert.equal(celebrationDuration(2, 3), 5000);
});
