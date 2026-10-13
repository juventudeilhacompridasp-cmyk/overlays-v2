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
const { buildArt, dateLabel, timeLabel, phaseLabel, wrapWords, dayHeading } = await import(pathToFileURL(out).href);

const style = (overrides = {}) => {
    const base = {
        background: { mode: 'gradient', color: '#0b1220', color2: '#16243d', angle: 160, pattern: 'none', pattern_opacity: 0.08, shade: 0, image: null, image_fit: 'cover', image_opacity: 1, tint: '#0b3b8c', tint_opacity: 0 },
        logo: { asset: null, size: 8, position: 'corner' },
        palette: { primary: '#10b981', secondary: '#38bdf8', text: '#ffffff', muted: '#94a3b8', accent: '#facc15' },
        type: { heading: 'display', body: 'sans', scale: 1, weight: 'bold', uppercase: true, spacing: 0.02 },
        layout: { variant: 'classic', align: 'center', margin: 6, frame: false, frame_width: 0.6, frame_color: '#ffffff', radius: 0 },
        crest: { show: true, size: 26, shape: 'circle', bg: '#ffffff', use_team_colors: false },
        elements: { competition: true, phase: true, date: true, time: true, venue: true, status: true, org_logo: true, footer: true, broadcast: false },
        broadcast: { platform: 'youtube', label: 'Transmissão ao vivo', channel: '', badge: null },
        text: { title: '', subtitle: '', headline: '', credit: '', footer: '', hashtag: '' },
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
    division: 'Primeira divisão',
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
    scope: 'day',
    games: [],
    ...overrides,
});

const game = (date, home, away, extra = {}) => ({ id: `${home}-${away}`, date, venue: 'Campo Municipal', status: 'scheduled', status_label: 'Agendado', decided: false, home: side(home), away: side(away), ...extra });

const texts = (ops) => ops.filter((op) => op.t === 'text').map((op) => op.text);

const FORMATS = [
    [1080, 1080],
    [1080, 1350],
    [1080, 1920],
    [1920, 1080],
    [1200, 630],
    [300, 900],
    [1080, 1440],
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
    const roundGames = [
        game('2026-10-09T18:45', 'Vila Muniz', 'Boa Vista'),
        game('2026-10-09T20:45', 'Sacolão Kiko', 'Inhuguvira', { status: 'in_progress', home: side('Sacolão Kiko', 1), away: side('Inhuguvira', 0) }),
        game('2026-10-10T18:45', 'Bola +1', 'Vila Adriana', { decided: true, status: 'finished', home: side('Bola +1', 2), away: side('Vila Adriana', 2) }),
        game('2026-10-10T20:45', 'SEI', 'Sacolão Kiko Júnior', { venue: 'Outro campo' }),
    ];
    const kinds = [
        data(),
        data({ kind: 'result', home: side('Ilha FC', 1), away: side('Registro EC', 0) }),
        data({ kind: 'lineup', lineup: { home: lineupSide, away: lineupSide } }),
        data({ kind: 'lineup', lineup: null }),
        data({ kind: 'round', games: roundGames }),
        data({ kind: 'round', games: roundGames.slice(0, 1) }),
        data({ kind: 'round', games: [] }),
        data({ kind: 'cover' }),
    ];

    for (const [W, H] of FORMATS) {
        for (const variant of ['classic', 'split', 'poster']) {
            for (const align of ['center', 'left']) {
                for (const item of kinds) {
                    const ops = buildArt(style({ layout: { variant, align, frame: true }, elements: { broadcast: true }, text: { subtitle: 'Quartas de final', credit: 'Narração: Fulano', footer: 'Rodapé', hashtag: '#Copa' }, broadcast: { channel: 'Canal da Liga' } }), item, W, H);

                    for (const op of ops) {
                        if (op.t === 'text') {
                            assert.ok(Number.isFinite(op.x) && Number.isFinite(op.y) && op.size > 0, `texto inválido ${JSON.stringify(op)}`);
                            assert.ok(op.x >= 0 && op.x <= W && op.y >= 0 && op.y <= H, `texto fora (${W}x${H} ${variant} ${item.kind}): ${op.text} @ ${op.x},${op.y}`);
                            assert.ok(op.maxW > 0, `largura máxima inválida: ${op.text}`);
                        }
                        if (op.t === 'poly') {
                            assert.ok(op.points.length >= 6 && op.points.every((value, index) => Number.isFinite(value) && value >= -1 && value <= (index % 2 === 0 ? W : H) + 1), `polígono fora: ${JSON.stringify(op)}`);
                        }
                        if (op.t === 'rect') {
                            assert.ok(op.w > 0 && op.h > 0 && Number.isFinite(op.x) && Number.isFinite(op.y), `retângulo inválido: ${JSON.stringify(op)}`);
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

test('day heading is weekday plus day/month', () => {
    assert.equal(dayHeading('2026-10-09T18:45'), 'sexta — 09/10');
    assert.equal(dayHeading('2026-10-10T20:45'), 'sábado — 10/10');
    assert.equal(dayHeading(null), null);
});

test('round lists every game with its time, grouped by day, and the single venue once', () => {
    const games = [
        game('2026-10-09T18:45', 'Vila Muniz', 'Boa Vista'),
        game('2026-10-09T20:45', 'Sacolão Kiko', 'Inhuguvira'),
        game('2026-10-10T18:45', 'Bola +1', 'Vila Adriana'),
    ];
    const all = texts(buildArt(style(), data({ kind: 'round', games }), 1080, 1440));

    for (const expected of ['Jogos do dia', 'sexta — 09/10', 'sábado — 10/10', '18h45', '20h45', 'Vila Muniz', 'Boa Vista', 'Sacolão Kiko', 'Inhuguvira', 'Bola +1', 'Vila Adriana']) {
        assert.ok(all.includes(expected), `faltou "${expected}" em ${JSON.stringify(all)}`);
    }
    assert.equal(all.filter((text) => text === 'Campo Municipal').length, 1);
    assert.equal(all.filter((text) => text.startsWith('sexta')).length, 1);
});

test('round titles itself by the round when the scope is the round, and says so when there are no games', () => {
    assert.ok(texts(buildArt(style(), data({ kind: 'round', scope: 'round', round: 3, games: [game('2026-10-09T18:45', 'A', 'B')] }), 1080, 1440)).includes('Rodada 3'));
    assert.ok(texts(buildArt(style(), data({ kind: 'round', games: [] }), 1080, 1440)).includes('Nenhum jogo encontrado'));
});

test('round shows scores for decided and live games, and marks the live ones', () => {
    const games = [game('2026-10-09T18:45', 'A', 'B', { decided: true, status: 'finished', home: side('A', 3), away: side('B', 1) }), game('2026-10-09T20:45', 'C', 'D', { status: 'in_progress', home: side('C', 0), away: side('D', 0) }), game('2026-10-09T21:45', 'E', 'F')];
    const all = texts(buildArt(style(), data({ kind: 'round', games }), 1080, 1920));

    assert.ok(all.includes('3 × 1'));
    assert.ok(all.includes('0 × 0'));
    assert.ok(all.includes('×'));
    assert.ok(all.includes('Ao vivo'));
});

test('round variants differ: list has no panels, panels fill each game, posters stack the names', () => {
    const games = [game('2026-10-09T18:45', 'Vila Muniz', 'Boa Vista'), game('2026-10-09T20:45', 'Sacolão Kiko', 'Inhuguvira')];
    const rects = (variant) => buildArt(style({ layout: { variant } }), data({ kind: 'round', games }), 1080, 1920).filter((op) => op.t === 'rect');

    assert.equal(rects('classic').length, 0);
    assert.ok(rects('split').length >= 4); // um painel e uma etiqueta de horário por jogo (+ faixa do local)
    assert.equal(rects('poster').filter((op) => op.h > 5 * 10).length, 0);
});

test('the broadcast footer shows the platform badge, the label and the channel only when turned on', () => {
    const on = style({ elements: { broadcast: true }, broadcast: { platform: 'youtube', label: 'Transmissão ao vivo', channel: 'Juventude EC' } });
    const withBroadcast = buildArt(on, data(), 1080, 1350);

    assert.ok(texts(withBroadcast).includes('YouTube'));
    assert.ok(texts(withBroadcast).includes('Transmissão ao vivo'));
    assert.ok(texts(withBroadcast).includes('Juventude EC'));
    assert.ok(withBroadcast.some((op) => op.t === 'poly'));

    const off = texts(buildArt(style(), data(), 1080, 1350));
    assert.ok(!off.includes('Transmissão ao vivo'));

    const noBadge = buildArt(style({ elements: { broadcast: true }, broadcast: { platform: 'none', label: 'Ao vivo no canal', channel: '' } }), data(), 1080, 1350);
    assert.ok(texts(noBadge).includes('Ao vivo no canal'));
    assert.ok(!noBadge.some((op) => op.t === 'poly'));
});

test('the broadcast footer pushes the content up instead of covering it', () => {
    const lowest = (ops) => Math.max(...ops.filter((op) => op.t === 'text' && ['Estádio Municipal', '16h30'].includes(op.text)).map((op) => op.y));

    assert.ok(lowest(buildArt(style({ elements: { broadcast: true } }), data(), 1080, 1350)) < lowest(buildArt(style(), data(), 1080, 1350)));
});

test('cover uses the title override, the division, the subtitle, the headline and the credit', () => {
    const ops = buildArt(style({ text: { title: 'Cajati', subtitle: 'Quartas de final', headline: 'Jogos ao vivo!', credit: 'Narração: Izaú' } }), data({ kind: 'cover' }), 1080, 1440);
    const all = texts(ops);

    for (const expected of ['Primeira divisão', 'Cajati', 'Quartas de final', 'Jogos ao vivo!', 'Narração: Izaú']) {
        assert.ok(all.includes(expected), `faltou "${expected}" em ${JSON.stringify(all)}`);
    }
    assert.ok(!all.includes('Jogo do dia'));
    assert.ok(!all.includes('Copa Ilha'));
});

test('cover falls back to the competition name and the season', () => {
    const all = texts(buildArt(style(), data({ kind: 'cover', division: '' }), 1080, 1440));

    assert.ok(all.includes('Copa Ilha'));
    assert.ok(all.includes('2026'));
});

test('title and subtitle overrides also apply to the header of the match arts', () => {
    const all = texts(buildArt(style({ text: { title: 'Cajati', subtitle: 'Quartas de final' } }), data(), 1080, 1080));

    assert.ok(all.includes('Cajati'));
    assert.ok(all.includes('Quartas de final'));
    assert.ok(!all.includes('Copa Ilha'));
});

test('background image and tint come right after the base fill, before the texture', () => {
    const assets = { A1: '/painel/artes/imagens/A1/arquivo' };
    const ops = buildArt(style({ background: { image: 'A1', image_fit: 'contain', image_opacity: 0.8, tint_opacity: 0.5 } }), data(), 1080, 1350, assets);

    assert.deepEqual(ops.slice(0, 4).map((op) => op.t), ['background', 'bgimage', 'rect', 'texture']);
    assert.equal(ops[1].src, assets.A1);
    assert.equal(ops[1].fit, 'contain');
    assert.equal(ops[2].alpha, 0.5);
});

test('a background image that is no longer available is dropped', () => {
    const ops = buildArt(style({ background: { image: 'GONE' } }), data(), 1080, 1350, {});

    assert.ok(!ops.some((op) => op.t === 'bgimage'));
    assert.deepEqual(ops.slice(0, 2).map((op) => op.t), ['background', 'texture']);
});

test('the chosen logo replaces the organization logo', () => {
    const ops = buildArt(style({ logo: { asset: 'L1', size: 12 } }), data(), 1080, 1350, { L1: '/logo.webp' });
    const logo = ops.find((op) => op.t === 'image' && op.src === '/logo.webp');

    assert.ok(logo);
    assert.ok(logo.w > 0);
});
