import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/draw.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'draw-')), 'draw.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const d = await import(out);

const ids = (n) => Array.from({ length: n }, (_, i) => `t${i + 1}`);
const sorted = (list) => [...list].sort();
/** Gerador previsível (LCG) para os sorteios. */
const seeded = (seed) => () => ((seed = (seed * 1664525 + 1013904223) % 4294967296) / 4294967296);
const allTeams = (board) => [...board.pool, ...board.groups.flatMap((g) => g.teams)].sort();

test('grupos nascem vazios e todas as equipes ficam sem grupo', () => {
    const board = d.emptyGroups(ids(6), 2);

    assert.deepEqual(board.groups.map((g) => g.name), ['Grupo A', 'Grupo B']);
    assert.equal(board.pool.length, 6);
    assert.deepEqual(d.groupsProblems(board).length > 0, true);
});

test('pontos corridos monta todas as rodadas e troca equipes por arrasto', () => {
    let board = d.emptyLeague(ids(4));

    assert.equal(board.rounds.length, 3);
    assert.equal(d.leagueProblems(board, ids(4), false).length, 0);
    const first = board.rounds[0].games[0][0];
    const second = board.rounds[1].games[0][0];
    board = d.moveLeagueToken(board, 'round-0-0-0', 'round-1-0-0');
    assert.equal(board.rounds[0].games[0][0], second);
    assert.equal(board.rounds[1].games[0][0], first);
    assert.deepEqual(d.leagueProblems(board, ids(4), false), []);
});

test('pontos corridos em ida e volta duplica os confrontos invertendo o mando', () => {
    const board = d.emptyLeague(ids(3), true);

    assert.equal(board.rounds.length, 6);
    assert.equal(board.rounds[3].games[0][0], board.rounds[0].games[0][1]);
    assert.deepEqual(d.leaguePayload(board).length, 6);
    assert.deepEqual(d.leagueProblems(board, ids(3), true), []);
});

test('arrastar move a equipe entre o pool e os grupos sem duplicar', () => {
    let board = d.emptyGroups(ids(4), 2);
    board = d.moveToGroup(board, 't1', 'g0');
    board = d.moveToGroup(board, 't2', 'g0');
    board = d.moveToGroup(board, 't1', 'g1');

    assert.deepEqual(board.groups[0].teams, ['t2']);
    assert.deepEqual(board.groups[1].teams, ['t1']);
    assert.deepEqual(allTeams(board), ids(4));
    assert.equal(d.locationOf(board, 't1'), 'g1');
    assert.equal(d.locationOf(board, 't3'), 'pool');
    board = d.moveToGroup(board, 't1', 'pool');
    assert.equal(board.pool.includes('t1'), true);
    assert.equal(d.moveToGroup(board, 't1', 'inexistente'), board);
});

test('posição de inserção dentro do grupo', () => {
    let board = d.emptyGroups(ids(3), 1);
    board = d.moveToGroup(board, 't1', 'g0');
    board = d.moveToGroup(board, 't2', 'g0');
    board = d.moveToGroup(board, 't3', 'g0', 0);

    assert.deepEqual(board.groups[0].teams, ['t3', 't1', 't2']);
});

test('mudar o número de grupos devolve as equipes dos grupos removidos ao pool', () => {
    let board = d.snakeGroups(d.emptyGroups(ids(6), 3), ids(6));
    board = d.resizeGroups(board, 2);

    assert.equal(board.groups.length, 2);
    assert.deepEqual(allTeams(board), ids(6));
    assert.equal(board.pool.length, 2);
    assert.equal(d.resizeGroups(board, 99).groups.length, 16);
    assert.equal(d.resizeGroups(board, 0).groups.length, 1);
});

test('serpentina segue a ordem de cabeça de chave', () => {
    const board = d.snakeGroups(d.emptyGroups(ids(8), 2), ids(8));

    assert.deepEqual(board.groups[0].teams, ['t1', 't4', 't5', 't8']);
    assert.deepEqual(board.groups[1].teams, ['t2', 't3', 't6', 't7']);
    assert.deepEqual(d.groupsProblems(board), []);
});

test('sorteio simples equilibra os grupos e usa todas as equipes', () => {
    const board = d.randomGroups(d.emptyGroups(ids(10), 3), ids(10), seeded(7));

    assert.deepEqual(allTeams(board), sorted(ids(10)));
    assert.deepEqual(board.groups.map((g) => g.teams.length).sort((a, b) => a - b), [3, 3, 4]);
    assert.equal(board.pool.length, 0);
});

test('sorteio por potes coloca um de cada pote em cada grupo', () => {
    const board = d.potGroups(d.emptyGroups(ids(8), 4), ids(8), seeded(3));

    for (const group of board.groups) {
        assert.equal(group.teams.length, 2);
        const potOf = (id) => Math.floor((Number(id.slice(1)) - 1) / 4);
        assert.notEqual(potOf(group.teams[0]), potOf(group.teams[1]));
    }
    assert.deepEqual(allTeams(board), ids(8));
});

test('problemas: grupo pequeno, nome repetido e vazio', () => {
    let board = d.snakeGroups(d.emptyGroups(ids(4), 2), ids(4));
    board = d.renameGroup(board, 'g1', ' grupo a ');
    assert.match(d.groupsProblems(board).join(' '), /repetido/);
    board = d.renameGroup(board, 'g1', '');
    assert.match(d.groupsProblems(board).join(' '), /nome/);
    board = d.renameGroup(d.moveToGroup(d.snakeGroups(d.emptyGroups(ids(4), 2), ids(4)), 't1', 'pool'), 'g1', 'Final');
    assert.match(d.groupsProblems(board).join(' '), /sem grupo/);
    assert.deepEqual(d.groupsPayload(d.snakeGroups(d.emptyGroups(ids(4), 2), ids(4)))[0], { name: 'Grupo A', entries: ['t1', 't4'] });
});

test('o chaveamento comporta as equipes na menor potência de 2', () => {
    assert.equal(d.firstRoundPairs(2), 1);
    assert.equal(d.firstRoundPairs(5), 4);
    assert.equal(d.firstRoundPairs(8), 4);
    assert.equal(d.firstRoundPairs(9), 8);
    assert.equal(d.emptyBracket(ids(6)).slots.length, 8);
});

test('mover para uma vaga ocupada troca as duas equipes de lugar', () => {
    let board = d.emptyBracket(ids(4));
    board = d.moveToSlot(board, 't1', 0);
    board = d.moveToSlot(board, 't2', 1);
    board = d.moveToSlot(board, 't1', 1);

    assert.deepEqual(board.slots, ['t2', 't1', null, null]);
    board = d.moveToSlot(board, 't3', 0);
    assert.deepEqual(board.slots.slice(0, 2), ['t3', 't1']);
    assert.equal(board.pool.includes('t2'), true);
    assert.equal(d.slotIndexOf(board, 't3'), 0);
    board = d.moveToBracketPool(board, 't3');
    assert.equal(board.slots[0], null);
    assert.equal(board.pool.includes('t3'), true);
    assert.equal(d.moveToSlot(board, 't1', 99), board);
});

test('chave por cabeças de chave é igual à do servidor (8 equipes e com folgas)', () => {
    assert.deepEqual(d.seededBracket(ids(8)).slots, ['t1', 't8', 't4', 't5', 't2', 't7', 't3', 't6']);

    const six = d.seededBracket(ids(6));
    assert.deepEqual(six.slots, ['t1', null, 't4', 't5', 't2', null, 't3', 't6']);
    assert.deepEqual(d.bracketProblems(six), []);
});

test('sorteio da chave nunca deixa dois vazios no mesmo confronto', () => {
    for (let seed = 1; seed <= 40; seed++) {
        for (const count of [3, 5, 6, 7, 9, 12]) {
            const board = d.randomBracket(ids(count), seeded(seed));

            assert.deepEqual(d.bracketProblems(board), [], `${count} equipes, semente ${seed}`);
            assert.deepEqual(board.slots.filter(Boolean).sort(), sorted(ids(count)));
        }
    }
});

test('problemas da chave e payload dos confrontos', () => {
    let board = d.emptyBracket(ids(4));
    assert.match(d.bracketProblems(board).join(' '), /fora da chave/);
    board = d.seededBracket(ids(4));
    assert.deepEqual(d.pairsPayload(board), [['t1', 't4'], ['t2', 't3']]);
    board = d.clearBracket(board);
    assert.equal(board.pool.length, 4);
    assert.match(d.bracketProblems(board).join(' '), /sem equipes/);
});

test('nomes das rodadas', () => {
    assert.deepEqual(d.roundNames(1), ['Final']);
    assert.deepEqual(d.roundNames(2), ['Semifinal', 'Final']);
    assert.deepEqual(d.roundNames(8), ['16 avos de final', 'Oitavas de final', 'Quartas de final', 'Semifinal', 'Final'].slice(1));
    assert.deepEqual(d.roundNames(4), ['Quartas de final', 'Semifinal', 'Final']);
});

test('equipes da mesma cidade no mesmo grupo ou no mesmo confronto são detectadas', () => {
    const cities = { t1: 'registro', t2: 'registro', t3: 'iguape', t4: '' , t5: '', t6: 'iguape' };
    const cityOf = (id) => cities[id] ?? '';
    let board = d.emptyGroups(ids(6), 2);
    for (const [id, target] of [['t1', 'g0'], ['t2', 'g0'], ['t3', 'g1'], ['t4', 'g1'], ['t5', 'g1'], ['t6', 'g0']]) board = d.moveToGroup(board, id, target);

    const clashes = d.groupClashes(board, cityOf);
    assert.deepEqual(clashes.map((c) => [c.where, c.city, c.teams]), [['g0', 'registro', ['t1', 't2']]]);
    assert.equal(d.groupClashes(d.emptyGroups(ids(4), 2), cityOf).length, 0);

    const bracket = d.seededBracket(ids(4));
    assert.deepEqual(d.bracketClashes(bracket, (id) => ({ t1: 'x', t4: 'x' })[id] ?? '').map((c) => c.teams), [['t1', 't4']]);
    assert.equal(d.normalizeCity('  Registro '), 'registro');
    assert.equal(d.normalizeCity(null), '');
});

test('o sorteio com restrição acha uma distribuição sem conflito quando existe', () => {
    // 4 cidades com 2 equipes cada, 2 grupos de 4: sempre dá para separar.
    const cities = Object.fromEntries(ids(8).map((id, i) => [id, `cidade${Math.floor(i / 2)}`]));
    const cityOf = (id) => cities[id];
    const rng = seeded(11);

    const board = d.bestOf(300, () => d.randomGroups(d.emptyGroups(ids(8), 2), ids(8), rng), (b) => d.groupClashes(b, cityOf).length);
    assert.equal(d.groupClashes(board, cityOf).length, 0);
    assert.deepEqual(allTeams(board), sorted(ids(8)));

    const bracket = d.bestOf(300, () => d.randomBracket(ids(8), rng), (b) => d.bracketClashes(b, cityOf).length);
    assert.equal(d.bracketClashes(bracket, cityOf).length, 0);

    // Sem solução possível devolve o melhor que achou, sem travar.
    const same = d.bestOf(5, () => d.randomGroups(d.emptyGroups(ids(4), 2), ids(4), rng), (b) => d.groupClashes(b, () => 'x').length);
    assert.equal(d.groupClashes(same, () => 'x').length, 2);
});

test('aponta conflitos de clube e patrocinador sem alterar o contrato da cidade', () => {
    const board = { pool: [], groups: [{ id: 'g0', name: 'Grupo A', teams: ['t1', 't2', 't3'] }] };
    const club = (id) => ({ t1: 'clube-a', t2: 'clube-a' })[id] ?? '';
    const sponsor = (id) => ({ t2: 'marca-x', t3: 'marca-x' })[id] ?? '';
    const clashes = d.groupClashes(board, () => '', club, sponsor);

    assert.deepEqual(clashes.map((item) => [item.criterion, item.city, item.teams]), [
        ['clube', 'clube-a', ['t1', 't2']],
        ['patrocinador', 'marca-x', ['t2', 't3']],
    ]);
});

test('resizeBracket encolhe a chave e devolve ao pool quem perdeu o lugar', () => {
    const board = d.seededBracket(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);
    const smaller = d.resizeBracket(board, 4);

    assert.equal(smaller.slots.length, 4);
    assert.equal(sorted([...smaller.pool, ...smaller.slots.filter(Boolean)]).join(''), 'abcdefgh');
    assert.equal(d.resizeBracket(smaller, 4), smaller);
});

test('bracketOf usa só as primeiras equipes e deixa as outras fora', () => {
    const board = d.bracketOf(['a', 'b', 'c', 'd', 'e'], 4, d.seededBracket);

    assert.equal(board.slots.length, 4);
    assert.deepEqual(board.pool, ['e']);
    assert.equal(d.bracketProblems(board, true).length, 0);
    assert.ok(d.bracketProblems(board, false).length > 0);
});

test('bracketProblems parcial exige ao menos duas equipes', () => {
    assert.ok(d.bracketProblems({ pool: ['a', 'b'], slots: [null, null] }, true).length > 0);
});
