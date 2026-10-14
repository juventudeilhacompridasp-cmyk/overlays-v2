import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/obsChampionship.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'obs-champ-')), 'obsChampionship.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const lib = await import(out);

const match = (id, status, kickoff, round = 'Rodada 1', home = 1, away = 2) => ({
    id, round, status, kickoff_at: kickoff, home_team_id: `t${home}`, away_team_id: `t${away}`, home_name: `Casa ${home}`, away_name: `Fora ${away}`,
    home_score: status === 'scheduled' ? null : 1, away_score: status === 'scheduled' ? null : 0, venue: null,
});
const bundle = {
    championship: { name: 'Copa', season: '2026', division: 'Livre', logo: null, champion_id: null },
    divisions: [],
    teams: [{ id: 't1', name: 'Casa 1', short: 'CAS', logo: null, color: '#ff0000' }],
    standings: [{ position: 1, team_id: 't1', name: 'Casa 1', played: 1, won: 1, drawn: 0, lost: 0, gd: 1, points: 3 }],
    groups: [{ group: 'A', table: [{ position: 1, team_id: 't2', name: 'G', played: 0, won: 0, drawn: 0, lost: 0, gd: 0, points: 0 }] }],
    matches: [
        match('a', 'finished', '2026-05-01T10:00'), match('b', 'live', '2026-05-02T10:00'), match('c', 'scheduled', '2026-05-04T10:00', 'Rodada 2'),
        match('d', 'scheduled', '2026-05-03T10:00', 'Rodada 2'), match('e', 'cancelled', '2026-05-05T10:00'),
    ],
    scorers: [{ name: 'Zé', team_name: 'Casa 1', team_id: 't1', goals: 3 }],
    cards: null,
};

test('defaults come from the view and invalid values fall back', () => {
    const cfg = lib.obsConfig('', 'standings');
    assert.equal(cfg.pos, 'cl'); assert.equal(cfg.width, 760); assert.equal(cfg.rows, 8); assert.equal(cfg.style, 'broadcast');
    const bad = lib.obsConfig('?style=xx&anim=yy&scale=9999&rows=-3&accent=zzz&cols=weird&width=10', 'fixtures');
    assert.equal(bad.style, 'broadcast'); assert.equal(bad.anim, 'rise'); assert.equal(bad.scale, 200); assert.equal(bad.rows, 1);
    assert.equal(bad.accent, ''); assert.equal(bad.cols, 'std'); assert.equal(bad.width, 360);
    assert.equal(lib.obsConfig('', 'inexistente').view, 'standings');
    assert.equal(lib.obsConfig('?cols=full', 'standings').width, 920);
});

test('the url keeps only what differs from the defaults and round-trips', () => {
    const cfg = lib.obsConfig('?style=neon&accent=%2300e5ff&rows=3&c=copa&logos=0', 'results');
    const query = lib.obsQuery(cfg);
    assert.equal(query, 'c=copa&style=neon&logos=0&accent=00e5ff&rows=3');
    assert.deepEqual(lib.obsConfig(`?${query}`, 'results'), cfg);
    assert.equal(lib.obsUrl('/overlay/tok/campeonato', lib.obsConfig('', 'ticker')), '/overlay/tok/campeonato/ticker');
});

test('each view picks and orders its items', () => {
    const cfg = (view, extra = '') => lib.obsConfig(extra, view);
    assert.deepEqual(lib.obsItems(bundle, cfg('fixtures')).map((m) => m.id), ['b', 'd', 'c']);
    assert.deepEqual(lib.obsItems(bundle, cfg('results')).map((m) => m.id), ['b', 'a']);
    assert.deepEqual(lib.obsItems(bundle, cfg('results', '?round=Rodada%201')).map((m) => m.id), ['a', 'b']);
    assert.equal(lib.obsItems(bundle, cfg('standings')).length, 1);
    assert.equal(lib.obsItems(bundle, cfg('standings', '?group=A'))[0].team_id, 't2');
    assert.deepEqual(lib.obsItems(bundle, cfg('discipline')), []);
    assert.equal(lib.obsItems(bundle, cfg('scorers'))[0].goals, 3);
});

test('the ticker mixes recent and upcoming games and cancelled ones never show', () => {
    const items = lib.obsItems(bundle, lib.obsConfig('?rows=4', 'ticker'));
    assert.deepEqual(items.map((m) => m.id), ['a', 'b', 'd', 'c']);
    assert.ok(!items.some((m) => m.id === 'e'));
});

test('pages and the featured game', () => {
    assert.equal(lib.obsPageCount(bundle, lib.obsConfig('?rows=1', 'fixtures')), 3);
    assert.equal(lib.obsPageCount(bundle, lib.obsConfig('', 'ticker')), 1);
    assert.equal(lib.obsFeaturedMatch(bundle, lib.obsConfig('', 'matchcard')).id, 'b');
    assert.equal(lib.obsFeaturedMatch(bundle, lib.obsConfig('?match=c', 'matchcard')).id, 'c');
    assert.equal(lib.obsFeaturedMatch({ ...bundle, matches: [] }, lib.obsConfig('', 'matchcard')), null);
});

test('kickoff and team fallback', () => {
    assert.deepEqual(lib.obsKickoff(match('x', 'scheduled', '2026-05-03T19:30')), { day: '03/05', time: '19:30' });
    assert.deepEqual(lib.obsKickoff(match('x', 'scheduled', null)), { day: 'A DEFINIR', time: '--:--' });
    assert.equal(lib.obsTeam(bundle, 'nope', 'Visitante').short, 'VIS');
});

test('templates reset and apply style options, ignoring anything that is not a style key', () => {
    const base = lib.obsConfig('?accent=ff0000&c=copa&bw=5', 'standings');
    const neon = lib.obsApplyStyle(base, lib.OBS_TEMPLATES[2].options);
    assert.equal(neon.style, 'neon'); assert.equal(neon.accent, '#00e5ff'); assert.equal(neon.c, 'copa'); assert.equal(neon.view, 'standings');
    const clean = lib.obsApplyStyle(base, { style: 'glass', view: 'ticker', rows: 99, anim: 'nope' });
    assert.equal(clean.view, 'standings'); assert.equal(clean.rows, 8); assert.equal(clean.accent, ''); assert.equal(clean.bw, 0); assert.equal(clean.anim, 'rise');
    assert.deepEqual(Object.keys(lib.obsStylePayload(base)), [...lib.OBS_STYLE_KEYS]);
});
