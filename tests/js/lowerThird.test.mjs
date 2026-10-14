import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/lowerThird.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'lower-third-')), 'lowerThird.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const lib = await import(out);

const banner = (extra = {}) => ({ id: 'x', kind: 'goal', title: 'GOL!', subtitle: 'Rafa', team: 'Leões', color: '#00aa00', minute: 10, ...extra });

test('there are at least five styles and five animations, and every key is unique', () => {
    assert.ok(lib.LOWER_THIRD_STYLES.length >= 5 && lib.LOWER_THIRD_ANIMATIONS.length >= 5);
    assert.equal(new Set(lib.LOWER_THIRD_STYLES.map((s) => s.key)).size, lib.LOWER_THIRD_STYLES.length);
    assert.equal(new Set(lib.LOWER_THIRD_ANIMATIONS.map((s) => s.key)).size, lib.LOWER_THIRD_ANIMATIONS.length);
});

test('every animation has an entry and a final frame and the final one is visible', () => {
    for (const { key } of lib.LOWER_THIRD_ANIMATIONS) {
        const frames = lib.lowerThirdFrames(key);
        assert.ok(frames.length >= 2, key);
        assert.equal(frames[frames.length - 1].opacity, 1, key);
        if (key !== 'wipe') assert.ok(frames[0].opacity === 0, key);
    }
    assert.equal(lib.lowerThirdFrames('slide')[0].transform, 'translate3d(-110%, 0, 0)');
    assert.equal(lib.lowerThirdFrames('slide', true)[0].transform, 'translate3d(0, 2rem, 0)');
});

test('tones fall back to the goal tone and the team color wins when valid', () => {
    assert.equal(lib.lowerThirdTone('yellow_card').icon, '🟨');
    assert.equal(lib.lowerThirdTone('desconhecido').accent, lib.lowerThirdTone('goal').accent);
    assert.equal(lib.teamAccent(banner()), '#00aa00');
    assert.equal(lib.teamAccent(banner({ color: 'verde' })), lib.lowerThirdTone('goal').accent);
});

test('text color follows the background brightness', () => {
    assert.equal(lib.inkOn('#ffffff'), '#111827');
    assert.equal(lib.inkOn('#eab308'), '#111827');
    assert.equal(lib.inkOn('#0f172a'), '#ffffff');
    assert.equal(lib.inkOn('nada'), '#ffffff');
});

test('score line and shirt label', () => {
    assert.equal(lib.scoreLine(banner()), null);
    assert.equal(lib.scoreLine(banner({ score: { home: 2, away: 1, home_short: 'LEO', away_short: 'AGU' } })), 'LEO 2 × 1 AGU');
    assert.equal(lib.shirtLabel(banner({ player: { name: 'Rafa', shirt: 7, position: null } })), '07');
    assert.equal(lib.shirtLabel(banner({ player: { name: 'Rafa', shirt: null, position: null } })), null);
    assert.equal(lib.shirtLabel(banner()), null);
});
