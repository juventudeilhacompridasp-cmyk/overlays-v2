import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/landingMetrics.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'metrics-')), 'landingMetrics.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { SECTIONS, csrfFromCookie, deviceOf, mayTrack, privacySignal, readConsent, shouldAsk, sourceOf, visitEvents } = await import(out);

test('consentimento: só "yes" e "no" valem', () => {
    assert.equal(readConsent('yes'), 'yes');
    assert.equal(readConsent('no'), 'no');
    for (const raw of [null, undefined, '', 'true', 'YES', 'sim']) assert.equal(readConsent(raw), null);
});

test('origem em 4 grupos, sem guardar a referência', () => {
    assert.equal(sourceOf('', 'site.test'), 'direct');
    assert.equal(sourceOf('   ', 'site.test'), 'direct');
    assert.equal(sourceOf('https://site.test/outra', 'site.test'), 'direct');
    assert.equal(sourceOf('https://www.site.test/', 'site.test'), 'direct');
    assert.equal(sourceOf('https://www.google.com.br/search?q=x', 'site.test'), 'search');
    assert.equal(sourceOf('https://duckduckgo.com/', 'site.test'), 'search');
    assert.equal(sourceOf('https://l.instagram.com/?u=x', 'site.test'), 'social');
    assert.equal(sourceOf('https://t.co/abc', 'site.test'), 'social');
    assert.equal(sourceOf('https://wa.me/55', 'site.test'), 'social');
    assert.equal(sourceOf('https://blog.exemplo.com/post', 'site.test'), 'other');
    assert.equal(sourceOf('isto não é uma url', 'site.test'), 'other');
    assert.equal(sourceOf('https://evilsite.test/', 'site.test'), 'other');
});

test('aparelho pela largura', () => {
    assert.equal(deviceOf(390), 'mobile');
    assert.equal(deviceOf(767), 'mobile');
    assert.equal(deviceOf(768), 'desktop');
    assert.equal(deviceOf(1920), 'desktop');
});

test('Do Not Track e Global Privacy Control', () => {
    assert.equal(privacySignal({ doNotTrack: '1' }), true);
    assert.equal(privacySignal({ doNotTrack: 'yes' }), true);
    assert.equal(privacySignal({ globalPrivacyControl: true }), true);
    assert.equal(privacySignal({ doNotTrack: '0' }), false);
    assert.equal(privacySignal({ doNotTrack: null, globalPrivacyControl: false }), false);
    assert.equal(privacySignal({}), false);
});

test('só pergunta e só mede nas condições certas', () => {
    const base = { enabled: true, consent: null, signal: false };

    assert.equal(shouldAsk(base), true);
    assert.equal(mayTrack(base), false);
    assert.equal(shouldAsk({ ...base, consent: 'yes' }), false);
    assert.equal(mayTrack({ ...base, consent: 'yes' }), true);
    assert.equal(mayTrack({ ...base, consent: 'no' }), false);
    assert.equal(shouldAsk({ ...base, enabled: false }), false);
    assert.equal(mayTrack({ ...base, consent: 'yes', enabled: false }), false);
    assert.equal(shouldAsk({ ...base, signal: true }), false);
    assert.equal(mayTrack({ ...base, consent: 'yes', signal: true }), false);
});

test('eventos da visita e seções da lista fechada', () => {
    assert.deepEqual(visitEvents('https://www.google.com/', 'site.test', 1366), [
        { metric: 'visit', dimension: '' },
        { metric: 'source', dimension: 'search' },
        { metric: 'device', dimension: 'desktop' },
    ]);
    assert.deepEqual([...SECTIONS], ['galeria', 'pacotes', 'faq', 'contato']);
});

test('token XSRF do cookie', () => {
    assert.equal(csrfFromCookie('a=1; XSRF-TOKEN=abc%3D%3D; b=2'), 'abc==');
    assert.equal(csrfFromCookie('a=1'), null);
    assert.equal(csrfFromCookie(''), null);
});
