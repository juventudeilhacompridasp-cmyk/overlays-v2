// Projeção da fila offline da súmula (resources/js/lib/sheetProject.ts), em Node puro: `node --test tests/js`.
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/sheetProject.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'sheet-project-')), 'sheetProject.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { project, applyClockMark } = await import(pathToFileURL(out).href);

const HOME = 'home-entry';
const AWAY = 'away-entry';
const rules = { reentry: false, maxPlayerFouls: null, offsets: [0, 2700, 5400, 6300], scoreModel: 'goals' };

const team = (overrides = {}) => ({
    on_field: ['a', 'b', 'c'],
    available_bench: ['x', 'y'],
    expelled: [],
    fouled_out: [],
    player_fouls: {},
    yellows: {},
    substitutions_used: 0,
    ...overrides,
});

const base = () => ({
    home_id: HOME,
    away_id: AWAY,
    home_score: 0,
    away_score: 0,
    teams: { [HOME]: team(), [AWAY]: team({ on_field: ['p', 'q'], available_bench: ['r'] }) },
    clock: { state: 'not_started', period: null, base_seconds: 0, running_since_ms: null },
    finishing: false,
    voided: [],
    partial: false,
});

let n = 0;
const event = (payload, at = 1000) => ({ key: `k${n++}`, kind: 'event', payload, at, offline: true, status: 'pending', label: 'x' });
const item = (kind, payload, at = 1000) => ({ key: `k${n++}`, kind, payload, at, offline: true, status: 'pending', label: 'x' });

test('goals and own goals move the score of the right side', () => {
    const view = project(base(), [event({ type: 'goal', entry_id: HOME, person_id: 'a' }), event({ type: 'goal', entry_id: AWAY, person_id: 'p' }), event({ type: 'own_goal', entry_id: HOME, person_id: 'b' })], rules);

    assert.equal(view.home_score, 1);
    assert.equal(view.away_score, 2);
});

test('basketball points add 1, 2 and 3', () => {
    const view = project(
        base(),
        [event({ type: 'free_throw', entry_id: HOME, person_id: 'a' }), event({ type: 'field_goal', entry_id: HOME, person_id: 'a' }), event({ type: 'three_pointer', entry_id: AWAY, person_id: 'p' })],
        { ...rules, scoreModel: 'points' },
    );

    assert.deepEqual([view.home_score, view.away_score], [3, 3]);
});

test('a red card and a second yellow take the player off the field', () => {
    const view = project(
        base(),
        [event({ type: 'yellow_card', entry_id: HOME, person_id: 'a' }), event({ type: 'red_card', entry_id: HOME, person_id: 'b' })],
        rules,
    );
    assert.deepEqual(view.teams[HOME].on_field, ['a', 'c']);
    assert.deepEqual(view.teams[HOME].expelled, ['b']);
    assert.equal(view.teams[HOME].yellows.a, 1);

    const second = project(view, [event({ type: 'yellow_card', entry_id: HOME, person_id: 'a' })], rules);
    assert.deepEqual(second.teams[HOME].expelled.sort(), ['a', 'b']);
    assert.deepEqual(second.teams[HOME].on_field, ['c']);
});

test('a substitution swaps field and bench; with reentry the one who leaves can come back', () => {
    const sub = event({ type: 'substitution', entry_id: HOME, person_id: 'a', secondary_person_id: 'x' });

    const strict = project(base(), [sub], rules);
    assert.deepEqual(strict.teams[HOME].on_field, ['b', 'c', 'x']);
    assert.deepEqual(strict.teams[HOME].available_bench, ['y']);
    assert.equal(strict.teams[HOME].substitutions_used, 1);

    const volante = project(base(), [sub], { ...rules, reentry: true });
    assert.deepEqual(volante.teams[HOME].available_bench, ['y', 'a']);
});

test('the scorer of a goal can be the player who just came in on a queued substitution', () => {
    const view = project(base(), [event({ type: 'substitution', entry_id: HOME, person_id: 'a', secondary_person_id: 'x' }), event({ type: 'goal', entry_id: HOME, person_id: 'x' })], rules);

    assert.ok(view.teams[HOME].on_field.includes('x'));
    assert.equal(view.home_score, 1);
});

test('personal fouls eliminate at the limit and mark the view as partial', () => {
    const view = project(base(), [event({ type: 'foul', entry_id: HOME, person_id: 'a' }), event({ type: 'foul', entry_id: HOME, person_id: 'a' })], { ...rules, maxPlayerFouls: 2 });

    assert.equal(view.teams[HOME].player_fouls.a, 2);
    assert.deepEqual(view.teams[HOME].fouled_out, ['a']);
    assert.equal(view.partial, true);
});

test('the clock follows the periods and pauses like the server does', () => {
    const clock = { state: 'not_started', period: null, base_seconds: 0, running_since_ms: null };

    const first = applyClockMark(clock, 'start', 1, 10_000, rules.offsets);
    assert.deepEqual([first.state, first.period, first.base_seconds, first.running_since_ms], ['running', 1, 0, 10_000]);

    const paused = applyClockMark(first, 'pause', 1, 70_000, rules.offsets);
    assert.deepEqual([paused.state, paused.base_seconds, paused.running_since_ms], ['paused', 60, null]);

    const resumed = applyClockMark(paused, 'resume', 1, 100_000, rules.offsets);
    assert.deepEqual([resumed.state, resumed.running_since_ms], ['running', 100_000]);

    const ended = applyClockMark(resumed, 'end', 1, 160_000, rules.offsets);
    assert.deepEqual([ended.state, ended.base_seconds], ['stopped', 120]);

    const second = applyClockMark(ended, 'start', 2, 500_000, rules.offsets);
    assert.deepEqual([second.state, second.period, second.base_seconds], ['running', 2, 2700]);
});

test('marks that the server would refuse change nothing on screen', () => {
    const idle = { state: 'not_started', period: null, base_seconds: 0, running_since_ms: null };

    assert.equal(applyClockMark(idle, 'pause', 1, 1000, rules.offsets), idle);
    assert.equal(applyClockMark(idle, 'end', 1, 1000, rules.offsets), idle);
    assert.equal(applyClockMark(idle, 'resume', 1, 1000, rules.offsets), idle);
});

test('queued periods and clock pauses reach the projected clock', () => {
    const view = project(base(), [item('period', { start: true, period: 1 }, 5_000), item('clock', { pause: true }, 35_000)], rules);

    assert.deepEqual([view.clock.state, view.clock.base_seconds], ['paused', 30]);
});

test('finishing, voiding and rally points are flagged instead of guessed', () => {
    const view = project(base(), [item('finish', {}), item('void', { event_id: 'ev1' }), item('point', { side: 'home' })], rules);

    assert.equal(view.finishing, true);
    assert.deepEqual(view.voided, ['ev1']);
    assert.equal(view.partial, true);
    assert.equal(view.home_score, 0);
});

test('the projection never changes the data it received', () => {
    const original = base();
    const snapshot = JSON.stringify(original);

    project(original, [event({ type: 'goal', entry_id: HOME, person_id: 'a' }), event({ type: 'red_card', entry_id: HOME, person_id: 'b' })], rules);

    assert.equal(JSON.stringify(original), snapshot);
});

test('nothing in the queue means nothing changes', () => {
    assert.deepEqual(project(base(), [], rules), base());
});
