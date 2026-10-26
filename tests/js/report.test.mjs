import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/report.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'report-')), 'report.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { arcPath, compactBlock, donutSegments, formColor, isWideBlock, msToNextSlide, niceMax, reportSlideAt, sectionsQuery, share, OVERLAY_LIMITS } = await import(out);

test('o slide no ar segue a mesma conta do servidor', () => {
    const manual = { mode: 'manual', seconds: 10, step: 4, anchor_ms: 0 };
    const auto = { mode: 'auto', seconds: 10, step: 1, anchor_ms: 1000 };

    assert.equal(reportSlideAt(manual, 3, 99_000), 1);
    assert.equal(reportSlideAt(auto, 3, 1000), 1);
    assert.equal(reportSlideAt(auto, 3, 11_000), 2);
    assert.equal(reportSlideAt(auto, 3, 21_000), 0);
    assert.equal(reportSlideAt(auto, 0, 21_000), 0);
    // relógio do aparelho atrasado em relação à âncora não volta slides
    assert.equal(reportSlideAt(auto, 3, 0), 1);
});

test('agenda a próxima troca só no automático', () => {
    assert.equal(msToNextSlide({ mode: 'manual', seconds: 10, step: 0, anchor_ms: 0 }, 5000), null);
    assert.equal(msToNextSlide({ mode: 'auto', seconds: 10, step: 0, anchor_ms: 0 }, 2500), 7500);
    assert.equal(msToNextSlide({ mode: 'auto', seconds: 10, step: 0, anchor_ms: 0 }, 10_000), 10_000);
});

test('eixo e barras', () => {
    assert.equal(niceMax(0), 1);
    assert.equal(niceMax(3), 4);
    assert.equal(niceMax(7), 10);
    assert.equal(niceMax(13), 20);
    assert.equal(niceMax(240), 250);
    assert.equal(share(5, 10), 0.5);
    assert.equal(share(15, 10), 1);
    assert.equal(share(3, 0), 0);
});

test('fatias da rosca acumulam e ignoram partes vazias', () => {
    const segments = donutSegments([{ value: 1, color: '#a' }, { value: 0, color: '#b' }, { value: 3, color: '#c' }]);

    assert.deepEqual(segments, [{ from: 0, to: 0.25, color: '#a' }, { from: 0.25, to: 1, color: '#c' }]);
    assert.deepEqual(donutSegments([{ value: 0, color: '#a' }]), []);
    assert.match(arcPath(50, 50, 40, 25, 0, 0.25), /^M50\.00 10\.00A40 40 0 0 1 90\.00 50\.00L75\.00 50\.00A25 25 0 0 0 50\.00 25\.00Z$/);
    // fatia inteira não colapsa num ponto só
    assert.ok(!arcPath(50, 50, 40, 25, 0, 1).includes('A40 40 0 1 1 50.00 10.00'));
});

test('corta os blocos para caber num slide', () => {
    const rows = Array.from({ length: 20 }, (_, index) => ({ cells: { team: `E${index}` }, highlight: false, color: null }));
    const items = Array.from({ length: 20 }, (_, index) => ({ minute: `${index}'`, period: null, side: 'home', icon: 'ball', color: '#000', title: 'Gol', detail: null }));

    assert.equal(compactBlock({ type: 'table', title: null, columns: [], rows }).rows.length, OVERLAY_LIMITS.table);
    const timeline = compactBlock({ type: 'timeline', items });
    assert.equal(timeline.items.length, OVERLAY_LIMITS.timeline);
    assert.equal(timeline.items.at(-1).minute, "19'");
    assert.deepEqual(compactBlock({ type: 'note', text: 'x' }), { type: 'note', text: 'x' });
    assert.equal(isWideBlock({ type: 'kpis', items: [] }), true);
    assert.equal(isWideBlock({ type: 'table', title: null, columns: [], rows: [] }), false);
});

test('cores e endereço das seções', () => {
    assert.equal(formColor('V'), '#16a34a');
    assert.equal(formColor('D'), '#dc2626');
    assert.equal(formColor('E'), '#94a3b8');
    assert.equal(sectionsQuery([]), '');
    assert.equal(sectionsQuery(['overview', 'timeline']), '?secoes=overview,timeline');
});
