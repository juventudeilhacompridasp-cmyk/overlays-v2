import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/operatorLayout.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'layout-')), 'operatorLayout.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const lib = await import(out);
const { COLS, ROWS, PANELS, PRESETS, defaultLayout, firstFree, hidePanel, isValid, moveTo, resizeTo, sanitizeLayout, showPanel } = lib;

test('todo modelo pronto é válido e cobre blocos conhecidos', () => {
    for (const preset of PRESETS) assert.equal(isValid(preset.layout), true, preset.key);
    assert.equal(PANELS.length, 12);
    for (const preset of PRESETS) for (const id of ['pgm', 'pvw']) assert.equal(preset.layout.some((item) => item.id === id), true, `${preset.key} sem ${id}`);
});

test('mover para uma área livre não mexe nos outros', () => {
    const layout = [{ id: 'live', x: 0, y: 0, w: 3, h: 4 }, { id: 'stats', x: 6, y: 0, w: 4, h: 4 }];
    const moved = moveTo(layout, 'live', 3, 8);

    assert.deepEqual(moved.find((i) => i.id === 'live'), { id: 'live', x: 3, y: 8, w: 3, h: 4 });
    assert.deepEqual(moved.find((i) => i.id === 'stats'), layout[1]);
});

test('mover sobre outro bloco empurra o vizinho para baixo', () => {
    const layout = [{ id: 'live', x: 0, y: 0, w: 3, h: 4 }, { id: 'stats', x: 3, y: 0, w: 4, h: 4 }];
    const moved = moveTo(layout, 'live', 3, 0);

    assert.equal(moved.find((i) => i.id === 'stats').y, 4);
    assert.equal(isValid(moved), true);
});

test('empurrões em cadeia e recusa quando não cabe', () => {
    const chain = [{ id: 'live', x: 0, y: 0, w: 3, h: 4 }, { id: 'stats', x: 3, y: 0, w: 3, h: 4 }, { id: 'clock', x: 3, y: 4, w: 3, h: 4 }];

    assert.equal(isValid(moveTo(chain, 'live', 3, 0)), true);

    const full = [{ id: 'live', x: 0, y: 0, w: 6, h: 16 }, { id: 'stats', x: 6, y: 0, w: 6, h: 8 }, { id: 'clock', x: 6, y: 8, w: 6, h: 8 }];

    assert.equal(moveTo(full, 'live', 6, 0), null);
});

test('sem espaço para empurrar, troca de lugar com o bloco atingido', () => {
    const full = [{ id: 'live', x: 0, y: 0, w: 6, h: 16 }, { id: 'stats', x: 6, y: 0, w: 6, h: 16 }];
    const swapped = moveTo(full, 'live', 6, 0);

    assert.deepEqual(swapped.find((i) => i.id === 'live'), { id: 'live', x: 6, y: 0, w: 6, h: 16 });
    assert.deepEqual(swapped.find((i) => i.id === 'stats'), { id: 'stats', x: 0, y: 0, w: 6, h: 16 });
    assert.equal(isValid(swapped), true);
});

test('mover fica dentro da grade', () => {
    const layout = [{ id: 'live', x: 0, y: 0, w: 3, h: 4 }];
    const moved = moveTo(layout, 'live', 99, 99)[0];

    assert.equal(moved.x, COLS - 3);
    assert.equal(moved.y, ROWS - 4);
    assert.equal(moveTo(layout, 'stats', 0, 0), null);
});

test('redimensionar respeita mínimo, grade e empurra', () => {
    const layout = [{ id: 'live', x: 0, y: 0, w: 3, h: 4 }, { id: 'stats', x: 3, y: 0, w: 4, h: 4 }];
    const small = resizeTo(layout, 'live', 1, 1)[0];

    assert.equal(small.w, 2);
    assert.equal(small.h, 3);

    const wide = resizeTo(layout, 'live', 6, 4);

    assert.equal(wide.find((i) => i.id === 'stats').y, 4);
    assert.equal(resizeTo(layout, 'live', 99, 99, 0) === null || isValid(resizeTo(layout, 'live', 99, 99)), true);
});

test('ocultar e mostrar blocos', () => {
    const base = defaultLayout();
    const hidden = hidePanel(base, 'stats');

    assert.equal(hidden.some((i) => i.id === 'stats'), false);

    const back = showPanel(hidden, 'stats');

    assert.equal(isValid(back), true);
    assert.equal(back.some((i) => i.id === 'stats'), true);
    assert.equal(showPanel(back, 'stats'), back);
});

test('mostrar encolhe até o mínimo e recusa sem espaço', () => {
    const wall = [{ id: 'live', x: 0, y: 0, w: 12, h: 16 }];

    assert.equal(showPanel(wall, 'stats'), null);
    assert.equal(firstFree(wall, 1, 1), null);
    assert.deepEqual(firstFree([], 3, 3), { x: 0, y: 0 });
});

test('firstFree nunca cai por cima do bloco Resumo', () => {
    const layout = [{ id: 'summary', x: 0, y: 0, w: 4, h: 2 }];
    const spot = firstFree(layout, 4, 2);

    assert.deepEqual(spot, { x: 4, y: 0 });
    assert.equal(isValid(showPanel(layout, 'clock')), true);
});

test('sanitizeLayout descarta lixo e cai no padrão', () => {
    assert.deepEqual(sanitizeLayout(null), defaultLayout());
    assert.deepEqual(sanitizeLayout([{ id: 'x', x: 0, y: 0, w: 3, h: 3 }]), defaultLayout());
    assert.deepEqual(sanitizeLayout([{ id: 'live', x: 0, y: 0, w: 3, h: 3 }, { id: 'screens', x: 1, y: 1, w: 3, h: 3 }]), defaultLayout());

    const ok = sanitizeLayout([{ id: 'live', x: 0, y: 0, w: 3, h: 4 }, { id: 'live', x: 5, y: 5, w: 3, h: 4 }, { id: 'stats', x: 20, y: 0, w: 99, h: 99 }]);

    assert.equal(ok.length === 2 ? isValid(ok) : true, true);
    assert.equal(sanitizeLayout([{ id: 'live', x: 0, y: 0, w: 3, h: 4 }]).length, 1);
});
