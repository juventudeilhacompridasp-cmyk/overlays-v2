import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/operatorConsole.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'operator-')), 'operatorConsole.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { OPERATOR_CARTELAS, OPERATOR_CHAMPIONSHIP_LABELS, OPERATOR_LAYERS, OPERATOR_NOTICES, OPERATOR_SCREENS, bump, hotkeyAction, liveCount, previewKey, previewLabel, stepLabel } = await import(out);

test('the five live layers have unique hotkeys 1 to 5 and only lineup and table need a game', () => {
    assert.deepEqual(OPERATOR_LAYERS.map((layer) => layer.hotkey), ['1', '2', '3', '4', '5']);
    assert.deepEqual(OPERATOR_LAYERS.filter((layer) => layer.needsGame).map((layer) => layer.key), ['lineup', 'standings']);
    assert.equal(new Set(OPERATOR_SCREENS.map((screen) => screen.key)).size, OPERATOR_SCREENS.length);
    assert.equal(OPERATOR_SCREENS.length, 9);
    assert.ok(OPERATOR_NOTICES.length >= 6 && OPERATOR_CARTELAS.length >= 4);
});

test('hotkeys map to layers, the lineup and exit', () => {
    const body = { tagName: 'BODY' };
    assert.deepEqual(hotkeyAction('3', body), { type: 'layer', key: 'lineup' });
    assert.deepEqual(hotkeyAction('5', body), { type: 'layer', key: 'sponsors' });
    assert.deepEqual(hotkeyAction('ArrowRight', body), { type: 'lineup', command: 'next' });
    assert.deepEqual(hotkeyAction('ArrowLeft', body), { type: 'lineup', command: 'previous' });
    assert.deepEqual(hotkeyAction('r', body), { type: 'lineup', command: 'reset' });
    assert.deepEqual(hotkeyAction('Escape', body), { type: 'exit' });
    assert.equal(hotkeyAction('9', body), null);
    assert.equal(hotkeyAction('x', null), null);
});

test('hotkeys never fire while typing or with modifiers (only Esc leaves)', () => {
    for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT']) {
        assert.equal(hotkeyAction('1', { tagName }), null);
        assert.equal(hotkeyAction('ArrowRight', { tagName }), null);
        assert.deepEqual(hotkeyAction('Escape', { tagName }), { type: 'exit' });
    }
    assert.equal(hotkeyAction('1', { tagName: 'DIV', isContentEditable: true }), null);
    assert.equal(hotkeyAction('1', { tagName: 'BODY' }, { ctrl: true }), null);
    assert.equal(hotkeyAction('ArrowRight', { tagName: 'BODY' }, { meta: true }), null);
});

test('the counters stay between 0 and 999', () => {
    assert.equal(bump(5, 1), 6);
    assert.equal(bump(0, -1), 0);
    assert.equal(bump(999, 1), 999);
    assert.equal(bump(Number.NaN, 1), 1);
});

test('step label and live count', () => {
    assert.equal(stepLabel('manual', 2, 14), 'Etapa 3 de 14');
    assert.equal(stepLabel('manual', 40, 14), 'Etapa 14 de 14');
    assert.equal(stepLabel('manual', 0, 0), 'Etapa 1 de 1');
    assert.equal(stepLabel('auto', 2, 14), 'Passando sozinho');
    assert.equal(liveCount({ a: true, b: false }, { c: true, d: true }, {}), 3);
});

test('every championship screen has a short label that fits a compact button', () => {
    assert.deepEqual(Object.keys(OPERATOR_CHAMPIONSHIP_LABELS).sort(), ['champion', 'discipline', 'fixtures', 'matchcard', 'results', 'scorers', 'standings', 'ticker']);
    for (const label of Object.values(OPERATOR_CHAMPIONSHIP_LABELS)) assert.ok(label.length <= 12);
});

test('the PVW only accepts layers and screens the operator can take on air', () => {
    for (const key of ['lineup', 'standings', 'sponsors', ...OPERATOR_SCREENS.map((screen) => screen.key)]) assert.equal(previewKey(key), key);

    for (const bad of ['scoreboard', 'lower_third', '', 'x', null, undefined, 3]) assert.equal(previewKey(bad), null);

    assert.equal(previewLabel('lineup'), 'Escalação');
    assert.equal(previewLabel('pregame'), 'Pré-jogo');
    assert.equal(previewLabel(null), 'Passe o mouse num botão');
});
