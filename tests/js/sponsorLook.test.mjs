import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/sponsorLook.ts', import.meta.url), 'utf8').replace(/^import .*$/gm, '');
const out = join(mkdtempSync(join(tmpdir(), 'sponsor-look-')), 'sponsorLook.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { animationMs, barFrame, barItems, sponsorContent, sponsorFrame } = await import(out);

const item = (extra = {}) => ({ name: 'Padaria', logo_url: 'logo.webp', banner_url: 'banner.webp', ...extra });

test('each format decides what the sponsor card shows', () => {
    assert.deepEqual(sponsorContent(item(), 'logo'), { image: 'logo.webp', wide: false, name: null });
    assert.deepEqual(sponsorContent(item(), 'logo_name'), { image: 'logo.webp', wide: false, name: 'Padaria' });
    assert.deepEqual(sponsorContent(item(), 'text'), { image: null, wide: false, name: 'Padaria' });
    assert.deepEqual(sponsorContent(item(), 'banner'), { image: 'banner.webp', wide: true, name: null });
    assert.deepEqual(sponsorContent(item(), 'banner_name'), { image: 'banner.webp', wide: true, name: 'Padaria' });
});

test('a wide format falls back to the logo when the sponsor has no banner', () => {
    assert.deepEqual(sponsorContent(item({ banner_url: null }), 'banner'), { image: 'logo.webp', wide: false, name: null });
});

test('the bar prefers sponsors with a banner and falls back to everyone', () => {
    const a = item({ name: 'A' });
    const b = item({ name: 'B', banner_url: null });

    assert.deepEqual(barItems([a, b]).map((entry) => entry.name), ['A']);
    assert.deepEqual(barItems([b]).map((entry) => entry.name), ['B']);
    assert.deepEqual(barItems([]), []);
});

test('the bar frame is always 1500 x 200 with the chosen border, shadow, corners and opacity', () => {
    const look = { bar_border: 'accent', bar_shadow: 'strong', bar_radius: 12, bar_opacity: 80, bar_background: '#112233' };
    const frame = barFrame(look, '#14b8a6');

    assert.equal(frame.width, 1500);
    assert.equal(frame.height, 200);
    assert.equal(frame.border, '4px solid #14b8a6');
    assert.equal(frame.borderRadius, '12px');
    assert.equal(frame.opacity, 0.8);
    assert.equal(frame.backgroundColor, '#112233');
    assert.equal(barFrame({ ...look, bar_border: 'none', bar_shadow: 'none' }, '#000000').boxShadow, 'none');
    assert.equal(barFrame({ ...look, bar_border: 'none' }, '#000000').border, 'none');
});

test('speed shortens the animation and the style picks a frame', () => {
    assert.equal(animationMs(100), 600);
    assert.equal(animationMs(200), 300);
    assert.equal(animationMs(50), 1200);
    assert.equal(animationMs(1000), 300);
    assert.match(sponsorFrame('ribbon'), /border-l-8/);
    assert.match(sponsorFrame('boxed'), /rounded-xl/);
});
