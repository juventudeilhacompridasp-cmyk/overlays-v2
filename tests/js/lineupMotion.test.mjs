import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/lineupMotion.ts', import.meta.url), 'utf8').replace(/^import .*$/gm, '');
const out = join(mkdtempSync(join(tmpdir(), 'lineup-motion-')), 'lineupMotion.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { LINEUP_MOTION_PRESETS, activeMotionPreset } = await import(out);

const ANIMATIONS = ['slide', 'wipe', 'rise', 'zoom', 'split', 'cascade'];
const TRANSITIONS = ['fade', 'slide', 'rise', 'wipe', 'curtain', 'split', 'zoom', 'flip'];
const PACKAGE = ['none', ...TRANSITIONS];

test('every motion package uses only animations the server accepts and they are all different', () => {
    assert.ok(LINEUP_MOTION_PRESETS.length >= 6);
    assert.equal(new Set(LINEUP_MOTION_PRESETS.map((preset) => preset.key)).size, LINEUP_MOTION_PRESETS.length);
    assert.equal(new Set(LINEUP_MOTION_PRESETS.map((preset) => JSON.stringify(preset.settings))).size, LINEUP_MOTION_PRESETS.length);

    for (const { settings } of LINEUP_MOTION_PRESETS) {
        assert.ok(ANIMATIONS.includes(settings.animation));
        assert.ok(TRANSITIONS.includes(settings.transition));
        assert.ok(PACKAGE.includes(settings.package_animation));
    }
});

test('the active package is found from the settings, and a custom mix has none', () => {
    for (const preset of LINEUP_MOTION_PRESETS) assert.equal(activeMotionPreset({ ...preset.settings, x: 1 }), preset.key);
    assert.equal(activeMotionPreset({ animation: 'slide', transition: 'flip', package_animation: 'none' }), null);
    assert.equal(activeMotionPreset({}), null);
});
