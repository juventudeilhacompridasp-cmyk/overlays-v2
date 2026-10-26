import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/remoteControl.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'remote-')), 'remote.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const r = await import(out);

test('pickChannel volta ao primeiro canal quando o guardado não existe mais', () => {
    const channels = [{ id: 'a' }, { id: 'b' }];

    assert.equal(r.pickChannel(channels, 'b').id, 'b');
    assert.equal(r.pickChannel(channels, 'x').id, 'a');
    assert.equal(r.pickChannel(channels, null).id, 'a');
    assert.equal(r.pickChannel([], 'a'), undefined);
});

test('nextStoppage nunca sai de 0 a 15', () => {
    assert.equal(r.nextStoppage(0, -1), 0);
    assert.equal(r.nextStoppage(15, 1), 15);
    assert.equal(r.nextStoppage(3, 1), 4);
    assert.equal(r.nextStoppage(Number.NaN, 1), 1);
});

test('tileHint diz o estado por texto, não só por cor', () => {
    assert.equal(r.tileHint(true, false, false), 'NO AR');
    assert.equal(r.tileHint(false, false, true), 'Oculto');
    assert.equal(r.tileHint(false, true, false), 'Escolha o jogo');
});

test('firstError pega a primeira mensagem do servidor', () => {
    assert.equal(r.firstError({ game_id: 'Jogo inválido.', layers: 'x' }), 'Jogo inválido.');
    assert.equal(r.firstError({}), null);
    assert.equal(r.firstError(undefined), null);
});
