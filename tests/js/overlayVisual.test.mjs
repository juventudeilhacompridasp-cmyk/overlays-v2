import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/overlayVisual.ts', import.meta.url), 'utf8').replace(/^import .*$/gm, '');
const out = join(mkdtempSync(join(tmpdir(), 'overlay-visual-')), 'overlayVisual.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { freePosition, nextSponsorIndex, overlayFontFamily, overlayOrigin, overlayPosition, overlayStyle, scoreboardFrame, sponsorFrameClass, withAlpha } = await import(out);

const appearance = (extra = {}) => ({ preset: 'arena', accent_color: '#14b8a6', background_color: '#0f172a', text_color: '#f8fafc', position: 'top-left', scale: 1, motion: 'dynamic', font: 'system', ...extra });

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

test('the font choice maps to a stack, a custom id to the registered family, and the default to nothing', () => {
    assert.equal(overlayFontFamily('system'), undefined);
    assert.match(overlayFontFamily('bebas'), /^"Bebas Neue"/);
    assert.match(overlayFontFamily('01JABCDEF0123456789XYZ0001'), /^"OverlayCustom"/);
    assert.equal(overlayStyle(appearance({ font: 'inter' })).fontFamily, overlayFontFamily('inter'));
    assert.equal('fontFamily' in overlayStyle(appearance({ font: 'system' })) && overlayStyle(appearance({ font: 'system' })).fontFamily, undefined);
});

test('the new styles have their own frame and keep the same data', () => {
    const glass = scoreboardFrame(appearance({ preset: 'glass' }));
    const contrast = scoreboardFrame(appearance({ preset: 'contrast' }));
    const neon = scoreboardFrame(appearance({ preset: 'neon' }));
    const ribbon = scoreboardFrame(appearance({ preset: 'ribbon' }));
    const gradient = scoreboardFrame(appearance({ preset: 'gradient' }));
    const aurora = scoreboardFrame(appearance({ preset: 'aurora' }));

    assert.match(glass.className, /backdrop-blur/);
    assert.equal(contrast.style.borderColor, '#14b8a6');
    assert.ok(neon.style.boxShadow.includes('#14b8a6'));
    assert.ok(ribbon.style.clipPath.includes('polygon'));
    assert.ok(gradient.style.background.startsWith('linear-gradient(120deg, #14b8a6'));
    assert.ok(aurora.style.background.startsWith('radial-gradient(circle at 12% 0%'));
});

test('radius, surface and shadow override the style defaults', () => {
    const custom = scoreboardFrame(appearance({ preset: 'classic', radius: 12, surface: 50, shadow: 'none' }));

    assert.equal(custom.style.borderRadius, '12px');
    assert.doesNotMatch(custom.className, /rounded-md/);
    assert.equal(custom.style.backgroundColor, '#0f172a80');
    assert.doesNotMatch(custom.className, /shadow-/);
    assert.match(scoreboardFrame(appearance({ preset: 'classic', shadow: 'strong' })).className, /shadow-2xl/);
});

test('alpha is multiplied by the surface and a fully opaque colour stays as is', () => {
    assert.equal(withAlpha('#112233', 1, 100), '#112233');
    assert.equal(withAlpha('#112233', 1, 50), '#11223380');
    assert.equal(withAlpha('#112233', 0.5, 100), '#11223380');
});

test('the free position only counts when both coordinates are set', () => {
    assert.deepEqual(freePosition({ x: 10, y: 20 }), { left: '10%', top: '20%' });
    assert.equal(freePosition({ x: 10, y: null }), null);
    assert.equal(freePosition({}), null);
});
