import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/statusPage.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'status-')), 'statusPage.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { STATUS_TONE, dayColor, dayTitle, formatDuration, formatUptime, formatWhen } = await import(out);

test('cor do dia por faixa de disponibilidade', () => {
    assert.equal(dayColor(null), 'bg-gray-200');
    assert.equal(dayColor(100), 'bg-emerald-500');
    assert.equal(dayColor(99.9), 'bg-emerald-500');
    assert.equal(dayColor(99.5), 'bg-amber-400');
    assert.equal(dayColor(95), 'bg-amber-400');
    assert.equal(dayColor(80), 'bg-red-500');
});

test('formatação de disponibilidade e tooltip', () => {
    assert.equal(formatUptime(null), '—');
    assert.equal(formatUptime(99.95), '99,95%');
    assert.equal(formatUptime(100), '100,00%');
    assert.equal(dayTitle('2026-10-13', 99.5), '13/10: 99,50%');
    assert.equal(dayTitle('2026-10-13', null), '13/10: sem dados');
});

test('duração e horário em São Paulo', () => {
    assert.equal(formatDuration('2026-10-14T10:00:00Z', null), 'em andamento');
    assert.equal(formatDuration('2026-10-14T10:00:00Z', '2026-10-14T10:12:00Z'), '12 min');
    assert.equal(formatDuration('2026-10-14T10:00:00Z', '2026-10-14T12:05:00Z'), '2 h 5 min');
    assert.equal(formatDuration('2026-10-14T10:00:00Z', '2026-10-14T12:00:00Z'), '2 h');
    assert.match(formatWhen('2026-10-14T15:30:00Z'), /14\/10.*12:30/);
});

test('todas as situações têm cores', () => {
    for (const key of ['operational', 'degraded', 'outage', 'maintenance']) assert.ok(STATUS_TONE[key].banner);
});
