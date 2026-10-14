import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/customBuilder.ts', import.meta.url), 'utf8').replace(/^import .*$/gm, '');
const out = join(mkdtempSync(join(tmpdir(), 'custom-builder-')), 'customBuilder.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { ANIMATIONS, TEMPLATES, animationFrames, elementBox, exitAnimation, exportOverlay, moveElement, newElement, resolveImage, resolveText, textStyle, totalDuration } = await import(out);

const ctx = (extra = {}) => ({
    home: { name: 'Leões FC', short: 'LEO', score: 2, logo: 'https://x/leao.png' }, away: { name: 'Tigres', short: 'TIG', score: 1, logo: null },
    clock: '35:12', period: '1º tempo', competition: 'Copa', division: 'Principal', stage: 'Grupos', venue: 'Ginásio', sponsor: { name: 'Padaria', logo: 'https://x/p.png' }, time: '19:30', ...extra,
});

test('the live data replaces the tokens and leaves unknown ones alone', () => {
    assert.equal(resolveText('{home.short} {home.score} × {away.score} {away.short}', ctx()), 'LEO 2 × 1 TIG');
    assert.equal(resolveText('{clock} · {period} · {competition} · {sponsor} · {time}', ctx()), '35:12 · 1º tempo · Copa · Padaria · 19:30');
    assert.equal(resolveText('{inexistente} {home.nada}', ctx()), '{inexistente} {home.nada}');
});

test('without a game the data is empty, never "undefined"', () => {
    const empty = ctx({ home: null, away: null, sponsor: null, clock: '', period: '' });

    assert.equal(resolveText('{home.name}|{home.score}|{sponsor}|{clock}', empty), '|||');
    assert.equal(resolveImage('token:home.logo', empty), null);
    assert.equal(resolveImage('token:sponsor', empty), null);
});

test('images come only from the game tokens or https', () => {
    assert.equal(resolveImage('token:home.logo', ctx()), 'https://x/leao.png');
    assert.equal(resolveImage('token:away.logo', ctx()), null);
    assert.equal(resolveImage('token:sponsor', ctx()), 'https://x/p.png');
    assert.equal(resolveImage('https://site.com/a.png', ctx()), 'https://site.com/a.png');
    assert.equal(resolveImage('http://site.com/a.png', ctx()), null);
    assert.equal(resolveImage('javascript:alert(1)', ctx()), null);
    assert.equal(resolveImage('', ctx()), null);
});

test('there are ten animations and every one except "none" has frames that start hidden', () => {
    assert.equal(ANIMATIONS.length, 10);
    assert.deepEqual(animationFrames('none'), []);
    for (const { key } of ANIMATIONS.filter((item) => item.key !== 'none')) {
        const frames = animationFrames(key);
        assert.ok(frames.length >= 2, key);
        const first = frames[0];
        assert.ok(first.opacity === 0 || first.clipPath !== undefined, key);
    }
});

test('the exit is the entrance backwards unless another animation is chosen', () => {
    assert.equal(exitAnimation({ animIn: 'zoom', animOut: 'same' }), 'zoom');
    assert.equal(exitAnimation({ animIn: 'zoom', animOut: 'fade' }), 'fade');
});

test('the total duration counts only visible elements with their delay', () => {
    assert.equal(totalDuration([{ delay: 100, duration: 600, visible: true }, { delay: 500, duration: 800, visible: true }, { delay: 9000, duration: 5000, visible: false }]), 1300);
    assert.equal(totalDuration([]), 0);
});

test('the box uses percentages, a gradient with two colours and the shape', () => {
    const box = elementBox({ ...newElement('shape'), x: 5, y: 6, w: 50, h: 20, fill: '#112233', fill2: '#445566', fillAngle: 90, fillOpacity: 100, shape: 'circle', rotation: 10, opacity: 50, borderWidth: 2, shadow: 'soft' });

    assert.equal(box.left, '5%');
    assert.equal(box.width, '50%');
    assert.equal(box.background, 'linear-gradient(90deg, #112233, #445566)');
    assert.equal(box.borderRadius, '50%');
    assert.equal(box.transform, 'rotate(10deg)');
    assert.equal(box.opacity, 0.5);
    assert.match(box.border, /2px solid/);
    assert.notEqual(box.boxShadow, 'none');
    assert.equal(elementBox({ ...newElement('shape'), fill: '#112233', fillOpacity: 50 }).background, '#11223380');
});

test('the text style follows alignment, uppercase and the marquee', () => {
    const style = textStyle({ ...newElement('text'), valign: 'bottom', align: 'center', transform: 'uppercase', marquee: true, size: 60, spacing: 20 }, 'Oswald');

    assert.equal(style.justifyContent, 'flex-end');
    assert.equal(style.textAlign, 'center');
    assert.equal(style.textTransform, 'uppercase');
    assert.equal(style.whiteSpace, 'nowrap');
    assert.equal(style.fontSize, 60);
    assert.equal(style.letterSpacing, '2em');
    assert.equal(style.fontFamily, 'Oswald');
});

test('new elements get unique ids and sensible defaults per type', () => {
    const a = newElement('text');
    const b = newElement('text', [a.id]);

    assert.notEqual(a.id, b.id);
    assert.equal(newElement('image').fit, 'contain');
    assert.equal(newElement('shape').fill, '#2563eb');
    assert.equal(a.text, 'Texto');
});

test('moving keeps the position inside the limits and rounds it', () => {
    assert.equal(moveElement({ ...newElement('text'), x: 10, y: 10 }, 5.123, -2.5).x, 15.12);
    assert.equal(moveElement({ ...newElement('text'), x: 199 }, 50, 0).x, 200);
    assert.equal(moveElement({ ...newElement('text'), y: -99 }, 0, -50).y, -100);
});

test('the export has the agreed format and every template builds valid elements', () => {
    const file = exportOverlay({ name: 'Meu', width: 800, height: 450, elements: [] });

    assert.equal(file.format, 'juventude-overlay');
    assert.equal(file.version, 1);
    assert.equal(file.overlay.name, 'Meu');
    assert.equal(TEMPLATES.length, 7);
    for (const template of TEMPLATES) {
        const elements = template.build();
        assert.ok(elements.length <= 60);
        assert.equal(new Set(elements.map((element) => element.id)).size, elements.length, template.key);
        for (const element of elements) assert.ok(['text', 'image', 'shape'].includes(element.type));
    }
});

test('table tokens resolve rows of the standings and stay literal when unknown', () => {
    const table = [
        { position: 1, team: 'Leões', points: 9, played: 3, won: 3, drawn: 0, lost: 0, goal_difference: 7 },
        { position: 2, team: 'Tigres', points: 4, played: 3, won: 1, drawn: 1, lost: 1, goal_difference: -1 },
    ];
    const withTable = { ...ctx(), table };
    assert.equal(resolveText('{table.1.pos}. {table.1.name} {table.1.points} pts ({table.1.gd})', withTable), '1. Leões 9 pts (7)');
    assert.equal(resolveText('{table.2.name} {table.2.won}-{table.2.drawn}-{table.2.lost}', withTable), 'Tigres 1-1-1');
    assert.equal(resolveText('[{table.5.name}]', withTable), '[]');
    assert.equal(resolveText('[{table.1.name}]', ctx()), '[]');
    assert.equal(resolveText('{table.1.xyz} {table.11.name}', withTable), '{table.1.xyz} {table.11.name}');
});
