import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/statsLook.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'stats-look-')), 'statsLook.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { STATS_ANIMATIONS, STATS_METRICS, STATS_STYLES, STATS_VIEWS, barShares, isExpired, leader, remainingMs } = await import(out);

test('the two halves of a bar always add up to 100 and an empty row is split in half', () => {
    assert.deepEqual(barShares(7, 3), { home: 70, away: 30 });
    assert.deepEqual(barShares(1, 2), { home: 33, away: 67 });
    assert.deepEqual(barShares(0, 0), { home: 50, away: 50 });
    assert.deepEqual(barShares(5, 0), { home: 100, away: 0 });
    for (const [a, b] of [[1, 6], [13, 7], [2, 2]]) assert.equal(barShares(a, b).home + barShares(a, b).away, 100);
});

test('the screen leaves only when the server deadline passes', () => {
    assert.equal(isExpired(null, 5000), false);
    assert.equal(isExpired(10_000, 9_999), false);
    assert.equal(isExpired(10_000, 10_000), true);
    assert.equal(remainingMs(null, 1), null);
    assert.equal(remainingMs(10_000, 4_000), 6_000);
    assert.equal(remainingMs(10_000, 20_000), 0);
});

test('who leads a row, and a tie leads nobody', () => {
    assert.equal(leader({ home: 3, away: 1 }), 'home');
    assert.equal(leader({ home: 1, away: 3 }), 'away');
    assert.equal(leader({ home: 2, away: 2 }), null);
});

test('three views, three styles, five animations and seven indicators, all unique', () => {
    assert.equal(STATS_VIEWS.length, 3);
    assert.equal(STATS_STYLES.length, 3);
    assert.equal(STATS_ANIMATIONS.length, 5);
    assert.equal(STATS_METRICS.length, 7);
    for (const list of [STATS_VIEWS, STATS_STYLES, STATS_ANIMATIONS, STATS_METRICS]) assert.equal(new Set(list.map((item) => item.key)).size, list.length);
});
