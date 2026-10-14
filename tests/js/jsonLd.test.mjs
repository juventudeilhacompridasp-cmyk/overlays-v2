import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/jsonLd.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'jsonld-')), 'jsonLd.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { safeJsonLd } = await import(out);

test('the output can never close the script tag or open a comment, and still parses back to the same data', () => {
    const data = { name: '</script><script>alert(1)</script>', note: '<!-- x -->', amp: 'A & B', line: 'a b c', ok: 'João x Maria' };
    const text = safeJsonLd(data);

    assert.ok(!text.includes('</'));
    assert.ok(!text.includes('<!--'));
    assert.ok(!text.includes('<'));
    assert.ok(!text.includes(' ') && !text.includes(' '));
    assert.deepEqual(JSON.parse(text), data);
});

test('plain data is untouched', () => {
    assert.equal(safeJsonLd({ a: 1, b: ['x'] }), '{"a":1,"b":["x"]}');
});
