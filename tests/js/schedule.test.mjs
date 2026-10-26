import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/schedule.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'schedule-')), 'schedule.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const s = await import(out);

test('dia e horário saem do texto da data', () => {
    assert.equal(s.dayOf('2026-05-10T16:30'), '2026-05-10');
    assert.equal(s.timeOf('2026-05-10T16:30'), '16:30');
    assert.equal(s.dayOf(null), 'none');
    assert.equal(s.timeOf(null), '');
});

test('mover para outro dia mantém o horário e usa o padrão quando não havia', () => {
    assert.equal(s.moveToDay('2026-05-10T18:15', '2026-05-17'), '2026-05-17T18:15');
    assert.equal(s.moveToDay(null, '2026-05-17'), '2026-05-17T16:00');
    assert.equal(s.moveToDay('2026-05-10T18:15', 'none'), null);
    assert.equal(s.moveToDay('2026-05-10T18:15', 'lixo'), null);
});

test('trocar só o horário', () => {
    assert.equal(s.withTime('2026-05-10T18:15', '09:00'), '2026-05-10T09:00');
    assert.equal(s.withTime('2026-05-10T18:15', '9h'), '2026-05-10T18:15');
    assert.equal(s.withTime(null, '09:00'), null);
});

test('agrupar por dia: dias em ordem, jogos por horário, sem data por último e dias extras vazios', () => {
    const games = [
        { id: 'c', scheduled_at: '2026-05-17T10:00' },
        { id: 'b', scheduled_at: '2026-05-10T18:00' },
        { id: 'a', scheduled_at: '2026-05-10T09:00' },
        { id: 'd', scheduled_at: null },
    ];
    const groups = s.groupByDay(games, ['2026-05-12', 'x']);

    assert.deepEqual(groups.map((g) => g.day), ['2026-05-10', '2026-05-12', '2026-05-17', 'none']);
    assert.deepEqual(groups[0].games.map((g) => g.id), ['a', 'b']);
    assert.deepEqual(groups[1].games, []);
    assert.deepEqual(groups[3].games.map((g) => g.id), ['d']);
    assert.deepEqual(s.groupByDay([]).map((g) => g.day), ['none']);
});

test('formatação do dia e dos conflitos', () => {
    assert.equal(s.formatDay('2026-05-10'), 'dom, 10/05/2026');
    assert.equal(s.formatDay('none'), 'Sem data');
    assert.match(s.conflictText({ kind: 'venue', label: 'A x B' }), /Mesmo estádio/);
    assert.match(s.conflictText({ kind: 'team', label: 'A x B' }), /outro jogo/);
});
