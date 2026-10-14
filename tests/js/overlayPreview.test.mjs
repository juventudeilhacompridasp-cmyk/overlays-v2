import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/overlayPreview.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'overlay-preview-')), 'overlayPreview.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { overlayPreviewStatus } = await import(out);

const layers = { lineup: false, standings: false };

test('all screens wait for a game before reporting an on-air state', () => {
    for (const slug of ['placar', 'eventos', 'escalacao', 'tabela']) {
        assert.deepEqual(overlayPreviewStatus(slug, false, layers), {
            label: 'Sem jogo',
            tone: 'waiting',
            description: 'Escolha o jogo no ar para alimentar esta tela.',
        });
    }
});

test('on-demand screens reflect whether their layer is visible', () => {
    assert.equal(overlayPreviewStatus('escalacao', true, layers).tone, 'hidden');
    assert.equal(overlayPreviewStatus('tabela', true, layers).tone, 'hidden');
    assert.equal(overlayPreviewStatus('escalacao', true, { ...layers, lineup: true }).tone, 'live');
    assert.equal(overlayPreviewStatus('tabela', true, { ...layers, standings: true }).tone, 'live');
});

test('event screen is automatic and score is continuously live', () => {
    assert.equal(overlayPreviewStatus('eventos', true, layers).tone, 'automatic');
    assert.equal(overlayPreviewStatus('placar', true, layers).tone, 'live');
});

test('the scoreboard and the event banners can be hidden by the operator', () => {
    assert.equal(overlayPreviewStatus('placar', true, { ...layers, scoreboard: false }).tone, 'hidden');
    assert.equal(overlayPreviewStatus('eventos', true, { ...layers, lower_third: false }).tone, 'hidden');
    assert.equal(overlayPreviewStatus('placar', true, { ...layers, scoreboard: true }).tone, 'live');
    assert.equal(overlayPreviewStatus('eventos', true, { ...layers, lower_third: true }).tone, 'automatic');
});
