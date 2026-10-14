import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/lineupSkin.ts', import.meta.url), 'utf8').replace(/^import .*$/gm, '');
const out = join(mkdtempSync(join(tmpdir(), 'lineup-skin-')), 'lineupSkin.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { LINEUP_ANIMATIONS, LINEUP_LAYOUTS, LINEUP_STYLES, enterMotion, fade, lineupSkin } = await import(out);

const look = (extra = {}) => ({ layout: 'classic', style: 'editorial', animation: 'slide', surface: 96, radius: null, text_scale: 100, ...extra });

test('there are five concepts besides the classic one, six styles and six animations, all unique', () => {
    assert.equal(LINEUP_LAYOUTS.length, 6);
    assert.equal(LINEUP_STYLES.length, 6);
    assert.equal(LINEUP_ANIMATIONS.length, 6);
    for (const list of [LINEUP_LAYOUTS, LINEUP_STYLES, LINEUP_ANIMATIONS]) assert.equal(new Set(list.map((item) => item.key)).size, list.length);
});

test('each concept has its own composition', () => {
    const skins = Object.fromEntries(LINEUP_LAYOUTS.map((item) => [item.key, lineupSkin(look({ layout: item.key }), '#0f172a', '#14b8a6')]));

    assert.equal(skins.tunnel.vertical, true);
    assert.equal(skins.sidebar.list, true);
    assert.match(skins.sidebar.width, /26rem/);
    assert.equal(skins.matchday.ink, '#1c1917');
    assert.match(skins.stadium.number.className, /text-amber-300/);
    assert.match(skins.poster.card.className, /border-2/);
    assert.equal(skins.classic.list, false);
    assert.equal(new Set(Object.values(skins).map((skin) => skin.grid)).size >= 4, true);
});

test('the style only changes the classic concept', () => {
    const premier = lineupSkin(look({ style: 'premier' }), '#0f172a', '#14b8a6');
    const glass = lineupSkin(look({ style: 'glass' }), '#0f172a', '#14b8a6');
    const tunnelPremier = lineupSkin(look({ layout: 'tunnel', style: 'premier' }), '#0f172a', '#14b8a6');
    const tunnelGlass = lineupSkin(look({ layout: 'tunnel', style: 'glass' }), '#0f172a', '#14b8a6');

    assert.notEqual(premier.card.className, glass.card.className);
    // Sem backdrop-filter: o desfoque do fundo repinta a cada quadro e engasga no navegador do OBS.
    assert.doesNotMatch(glass.panel.className, /backdrop-blur/);
    assert.notEqual(glass.panel.style.background, premier.panel.style.background);
    assert.equal(tunnelPremier.card.className, tunnelGlass.card.className);
});

test('radius overrides the corners and the surface sets the background opacity', () => {
    const skin = lineupSkin(look({ radius: 10, surface: 50 }), '#0f172a', '#14b8a6');

    assert.equal(skin.panel.style.borderRadius, '10px');
    assert.equal(skin.panel.style.background, '#0f172a80');
    assert.equal(fade('#112233', 100), '#112233');
});

test('only the cascade staggers the entrance', () => {
    assert.deepEqual(enterMotion('cascade', 3), { className: 'lineup-enter-cascade', delay: 210 });
    assert.deepEqual(enterMotion('zoom', 3), { className: 'lineup-enter-zoom', delay: 0 });
    assert.equal(enterMotion('cascade', 99).delay, 1400);
});
