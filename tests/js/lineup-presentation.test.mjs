import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/lineupPresentation.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'lineup-presentation-')), 'lineup-presentation.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { lineupPresentationPlan } = await import(pathToFileURL(out).href);

test('a sequência começa na lista e só prepara a transição quando existe ao menos um esquema válido', () => {
    assert.deepEqual(lineupPresentationPlan('sequence', true, true), {
        initialPhase: 'list',
        transitionsToFormation: true,
        renderList: true,
        renderFormation: true,
        homeUsesFormation: true,
        awayUsesFormation: true,
    });

    assert.deepEqual(lineupPresentationPlan('sequence', false, false), {
        initialPhase: 'list',
        transitionsToFormation: false,
        renderList: true,
        renderFormation: false,
        homeUsesFormation: false,
        awayUsesFormation: false,
    });
});

test('o esquema usa fallback em lista apenas para a equipe sem posições completas', () => {
    const plan = lineupPresentationPlan('formation', true, false);

    assert.equal(plan.initialPhase, 'formation');
    assert.equal(plan.renderList, false);
    assert.equal(plan.renderFormation, true);
    assert.equal(plan.homeUsesFormation, true);
    assert.equal(plan.awayUsesFormation, false);
});

test('o modo tático sem qualquer formação válida volta para a lista e o modo lista não monta campos ocultos', () => {
    const fallback = lineupPresentationPlan('formation', false, false);
    assert.equal(fallback.initialPhase, 'list');
    assert.equal(fallback.renderList, true);
    assert.equal(fallback.renderFormation, false);

    const list = lineupPresentationPlan('list', true, true);
    assert.equal(list.initialPhase, 'list');
    assert.equal(list.renderList, true);
    assert.equal(list.renderFormation, false);
});
