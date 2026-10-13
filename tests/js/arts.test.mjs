// Motor de artes (resources/js/lib/arts.ts), em Node puro: `node --test tests/js`.
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/arts.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'arts-')), 'arts.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { buildArt, dateLabel, timeLabel, phaseLabel, wrapWords } = await import(pathToFileURL(out).href);

const style = (overrides = {}) => {
    const base = {
        background: { mode: 'gradient', color: '#0b1220', color2: '#16243d', angle: 160, pattern: 'none', pattern_opacity: 0.08, shade: 0 },
        palette: { primary: '#10b981', secondary: '#38bdf8', text: '#ffffff', muted: '#94a3b8', accent: '#facc15' },
        type: { heading: 'display', body: 'sans', scale: 1, weight: 'bold', uppercase: true, spacing: 0.02 },
        layout: { variant: 'classic', align: 'center', margin: 6, frame: false, frame_width: 0.6, frame_color: '#ffffff', radius: 0 },
        crest: { show: true, size: 26, shape: 'circle', bg: '#ffffff', use_team_colors: false },
        elements: { competition: true, phase: true, date: true, time: true, venue: true, status: true, org_logo: true, footer: true },
        text: { headline: '', footer: '', hashtag: '' },
    };
    const merged = {};
    for (const group of Object.keys(base)) merged[group] = { ...base[group], ...(overrides[group] ?? {}) };
    return merged;
};

const side = (name, score = null, extra = {}) => ({ name, short: null, crest: null, color: null, score, penalties: null, ...extra });

const data = (overrides = {}) => ({
    kind: 'preview',
    competition: 'Copa Ilha',
    season: '2026',
    phase: 'Fase de grupos',
    group: 'Grupo A',
    round: 3,
    leg: null,
    date: '2026-10-14T16:30',
    venue: 'Estádio Municipal',
    status: 'scheduled',
    status_label: 'Agendado',
    decided: false,
    unit: null,
    home: side('Ilha FC'),
    away: side('Registro EC'),
    org: { name: 'Liga', logo: null },
    lineup: null,
    ...overrides,
});

const texts = (ops) => ops.filter((op) => op.t === 'text').map((op) => op.text);

const FORMATS = [
    [1080, 1080],
    [1080, 1350],
    [1080, 1920],
    [1920, 1080],
    [1200, 630],
    [300, 900],
];

test('formats the date, time and phase labels in Portuguese without time zones', () => {
    assert.equal(dateLabel('2026-10-14T16:30'), 'quarta, 14 out');
    assert.equal(dateLabel('2026-01-01T00:00'), 'quinta, 1 jan');
    assert.equal(dateLabel(null), null);
    assert.equal(timeLabel('2026-10-14T16:30'), '16h30');
    assert.equal(timeLabel('2026-10-14T09:00'), '9h');
    assert.equal(timeLabel('2026-10-14'), null);
    assert.equal(phaseLabel({ phase: 'Fase de grupos', group: 'Grupo A', round: 3, leg: 2 }), 'Fase de grupos · Grupo A · Rodada 3 · Volta');
    assert.equal(phaseLabel({ phase: 'Final', group: null, round: null, leg: null }), 'Final');
});

test('wraps the bench list by size', () => {
    assert.deepEqual(wrapWords(['12 Ana', '13 Bia', '14 Cris'], 40), ['12 Ana, 13 Bia, 14 Cris']);
    const lines = wrapWords(['12 Ana', '13 Bia', '14 Cris', '15 Dani'], 14);
    assert.ok(lines.length >= 2);
    assert.ok(lines.every((line) => line.length <= 14 + 8));
    assert.deepEqual(wrapWords([], 10), []);
});

test('preview shows the matchup, date, time and venue, starting with the background', () => {
    const ops = buildArt(style(), data(), 1080, 1080);
    const all = texts(ops);

    assert.equal(ops[0].t, 'background');
    for (const expected of ['Copa Ilha', 'Fase de grupos · Grupo A · Rodada 3', 'Jogo do dia', 'Ilha FC', 'Registro EC', 'quarta, 14 out', '16h30', 'Estádio Municipal']) {
        assert.ok(all.includes(expected), `faltou "${expected}" em ${JSON.stringify(all)}`);
    }
});

test('result shows the score, status and penalties', () => {
    const ops = buildArt(style(), data({ kind: 'result', status: 'finished', status_label: 'Encerrado', decided: true, home: side('Ilha FC', 2, { penalties: 4 }), away: side('Registro EC', 2, { penalties: 3 }) }), 1080, 1350);
    const all = texts(ops);

    assert.ok(all.includes('2 × 2'));
    assert.ok(all.includes('Fim de jogo'));
    assert.ok(all.includes('Encerrado'));
    assert.ok(all.includes('Pênaltis 4 × 3'));
});

test('a score that does not exist yet becomes a dash', () => {
    const all = texts(buildArt(style(), data({ kind: 'result' }), 1080, 1080));

    assert.ok(all.includes('– × –'));
});

test('custom texts replace the default headline and fill the footer', () => {
    const ops = buildArt(style({ text: { headline: 'Hoje tem decisão', footer: 'Apoio: Prefeitura', hashtag: '#CopaIlha' } }), data(), 1080, 1080);
    const all = texts(ops);

    assert.ok(all.includes('Hoje tem decisão'));
    assert.ok(!all.includes('Jogo do dia'));
    assert.ok(all.includes('Apoio: Prefeitura'));
    assert.ok(all.includes('#CopaIlha'));
});

test('elements can be turned off', () => {
    const off = { competition: false, phase: false, date: false, time: false, venue: false, status: false, org_logo: false, footer: false };
    const all = texts(buildArt(style({ elements: off, text: { footer: 'Rodapé' } }), data(), 1080, 1080));

    for (const hidden of ['Copa Ilha', 'quarta, 14 out', '16h30', 'Estádio Municipal', 'Rodapé']) {
        assert.ok(!all.includes(hidden), `não devia mostrar "${hidden}"`);
    }
    assert.ok(all.includes('Ilha FC'));
});

test('the organization logo only appears when the element is on and there is a logo', () => {
    const withLogo = data({ org: { name: 'Liga', logo: 'data:image/webp;base64,AAA' } });

    assert.equal(buildArt(style(), withLogo, 1080, 1080).filter((op) => op.t === 'image').length, 1);
    assert.equal(buildArt(style({ elements: { org_logo: false } }), withLogo, 1080, 1080).filter((op) => op.t === 'image').length, 0);
});

test('crests become images, or initials when the team has no crest, or nothing when hidden', () => {
    const crest = 'data:image/webp;base64,BBB';
    const images = (ops) => ops.filter((op) => op.t === 'image').length;

    assert.equal(images(buildArt(style(), data({ home: side('Ilha FC', null, { crest }), away: side('Registro EC', null, { crest }) }), 1080, 1080)), 2);
    assert.ok(texts(buildArt(style(), data(), 1080, 1080)).includes('IF'));
    assert.equal(images(buildArt(style({ crest: { show: false } }), data({ home: side('Ilha FC', null, { crest }) }), 1080, 1080)), 0);
});

test('the three variants draw different backgrounds for the same match', () => {
    const shapes = (variant) => buildArt(style({ layout: { variant } }), data(), 1080, 1080).filter((op) => op.t === 'rect');

    assert.equal(shapes('classic').length, 0);
    assert.equal(shapes('split').length, 2);
    assert.ok(shapes('poster').length >= 1);
});

test('split uses the team colors only when asked', () => {
    const split = (use) => buildArt(style({ layout: { variant: 'split' }, crest: { use_team_colors: use } }), data({ home: side('Ilha FC', null, { color: '#ff0000' }), away: side('Registro EC', null, { color: '#0000ff' }) }), 1080, 1080).filter((op) => op.t === 'rect');

    assert.deepEqual(split(true).map((op) => op.fill), ['#ff0000', '#0000ff']);
    assert.deepEqual(split(false).map((op) => op.fill), ['#10b981', '#38bdf8']);
});

test('the frame follows the option', () => {
    assert.equal(buildArt(style(), data(), 1080, 1080).some((op) => op.t === 'frame'), false);
    assert.equal(buildArt(style({ layout: { frame: true } }), data(), 1080, 1080).some((op) => op.t === 'frame'), true);
});

test('left alignment moves the header to the left margin', () => {
    const competition = (align) => buildArt(style({ layout: { align } }), data(), 1080, 1080).find((op) => op.t === 'text' && op.text === 'Copa Ilha');

    assert.equal(competition('left').align, 'left');
    assert.equal(competition('center').align, 'center');
    assert.ok(competition('left').x < competition('center').x);
});

test('everything stays inside the canvas for every format, kind and variant', () => {
    const player = (shirt, name) => ({ shirt, name, position: 'ZAG', captain: shirt === 1 });
    const lineupSide = { formation: '4-4-2', starters: Array.from({ length: 11 }, (_, i) => player(i + 1, `Jogador ${i + 1}`)), bench: Array.from({ length: 7 }, (_, i) => player(12 + i, `Reserva ${i}`)), coach: 'Técnico Teste' };
    const kinds = [data(), data({ kind: 'result', home: side('Ilha FC', 1), away: side('Registro EC', 0) }), data({ kind: 'lineup', lineup: { home: lineupSide, away: lineupSide } }), data({ kind: 'lineup', lineup: null })];

    for (const [W, H] of FORMATS) {
        for (const variant of ['classic', 'split', 'poster']) {
            for (const align of ['center', 'left']) {
                for (const item of kinds) {
                    const ops = buildArt(style({ layout: { variant, align, frame: true } }), item, W, H);

                    for (const op of ops) {
                        if (op.t === 'text') {
                            assert.ok(Number.isFinite(op.x) && Number.isFinite(op.y) && op.size > 0, `texto inválido ${JSON.stringify(op)}`);
                            assert.ok(op.x >= 0 && op.x <= W && op.y >= 0 && op.y <= H, `texto fora (${W}x${H} ${variant} ${item.kind}): ${op.text} @ ${op.x},${op.y}`);
                            assert.ok(op.maxW > 0, `largura máxima inválida: ${op.text}`);
                        }
                        if (op.t === 'image' || op.t === 'shape') {
                            assert.ok(op.w > 0 && op.h > 0 && op.x >= -1 && op.y >= -1 && op.x + op.w <= W + 1 && op.y + op.h <= H + 1, `imagem fora (${W}x${H} ${variant}): ${JSON.stringify(op)}`);
                        }
                    }
                }
            }
        }
    }
});

test('lineup lists the starters with their shirts and the coach, and warns when there is no lineup', () => {
    const lineupSide = { formation: '4-3-3', starters: [{ shirt: 1, name: 'Goleiro', position: 'GK', captain: true }, { shirt: 9, name: 'Artilheiro', position: 'CF', captain: false }], bench: [{ shirt: 12, name: 'Reserva', position: null, captain: false }], coach: 'Professor' };
    const all = texts(buildArt(style(), data({ kind: 'lineup', lineup: { home: lineupSide, away: lineupSide } }), 1080, 1350));

    assert.ok(all.includes('Escalação'));
    assert.ok(all.includes('Goleiro (C)'));
    assert.ok(all.includes('Artilheiro'));
    assert.ok(all.includes('Técnico: Professor'));
    assert.ok(all.includes('4-3-3'));
    assert.ok(all.some((line) => line.includes('12 Reserva')));
    assert.ok(texts(buildArt(style(), data({ kind: 'lineup', lineup: null }), 1080, 1350)).includes('Escalação ainda não divulgada'));
});

test('a bigger text scale makes the text bigger', () => {
    const size = (scale) => buildArt(style({ type: { scale } }), data(), 1080, 1080).find((op) => op.t === 'text' && op.text === 'Copa Ilha').size;

    assert.ok(size(1.5) > size(1));
    assert.ok(size(0.7) < size(1));
});
