import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/overlayVisual.ts', import.meta.url), 'utf8').replace(/^import .*$/gm, '');
const out = join(mkdtempSync(join(tmpdir(), 'overlay-visual-')), 'overlayVisual.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { nextSponsorIndex, overlayOrigin, overlayPosition, scoreboardFrame, sponsorFrameClass } = await import(out);

const appearance = (extra = {}) => ({ preset: 'arena', accent_color: '#14b8a6', background_color: '#0f172a', text_color: '#f8fafc', position: 'top-left', scale: 1, motion: 'dynamic', ...extra });

test('every position anchors the scoreboard to its own corner or edge', () => {
    assert.match(overlayPosition('top-center'), /items-start justify-center/);
    assert.match(overlayPosition('bottom-right'), /items-end justify-end/);
    assert.equal(overlayOrigin('bottom-center'), 'bottom center');
    assert.equal(overlayOrigin('top-left'), 'top left');
});

test('each preset has its own frame over the same data', () => {
    const arena = scoreboardFrame(appearance());
    const classic = scoreboardFrame(appearance({ preset: 'classic' }));
    const minimal = scoreboardFrame(appearance({ preset: 'minimal' }));

    assert.match(arena.className, /rounded-2xl/);
    assert.ok(arena.style.background.includes('linear-gradient'));
    assert.match(classic.className, /rounded-md/);
    assert.equal(classic.style.backgroundColor, '#0f172a');
    assert.match(minimal.className, /rounded-full/);
    assert.equal(minimal.style.backgroundColor, '#0f172ab3');
});

test('the sponsor rotation loops, and survives an empty or shrunken list', () => {
    assert.equal(nextSponsorIndex(0, 3), 1);
    assert.equal(nextSponsorIndex(2, 3), 0);
    assert.equal(nextSponsorIndex(5, 0), 0);
    assert.equal(nextSponsorIndex(7, 2), 0);
});

test('the sponsor card follows the preset corners', () => {
    assert.equal(sponsorFrameClass('arena'), 'rounded-2xl');
    assert.equal(sponsorFrameClass('classic'), 'rounded-md');
    assert.equal(sponsorFrameClass('minimal'), 'rounded-full');
});
