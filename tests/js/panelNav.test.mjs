import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import ts from 'typescript';

const source = readFileSync(new URL('../../resources/js/lib/panelNav.ts', import.meta.url), 'utf8');
const out = join(mkdtempSync(join(tmpdir(), 'nav-')), 'panelNav.mjs');
writeFileSync(out, ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.ES2022, target: ts.ScriptTarget.ES2022 } }).outputText);
const { activeGroupOf, buildNav, groupOpen } = await import(out);

const FEATURES = ['overlays', 'arts', 'embeds', 'reports', 'online_registration', 'market'];
const ctx = (over = {}) => ({ role: 'viewer', platformAdmin: false, features: FEATURES, can: { competitions: false, operate: false, users: false, market: false }, ...over });
const labels = (menu, id) => menu.groups.find((g) => g.id === id)?.items.map((i) => i.label);

test('consulta vê só o básico, sem administração nem plataforma', () => {
    const menu = buildNav(ctx());

    assert.deepEqual(menu.top.map((i) => i.label), ['Painel']);
    assert.deepEqual(labels(menu, 'competition'), ['Competições', 'Equipes', 'Atletas e comissão', 'Estádios']);
    assert.deepEqual(labels(menu, 'broadcast'), ['Transmissão', 'Artes']);
    assert.equal(menu.groups.some((g) => g.area !== 'main'), false);
    assert.deepEqual(menu.bottom.map((i) => i.route), ['panel.help.index']);
});

test('organizador ganha inscrições, relatórios e widgets; operador ganha o mesário', () => {
    const menu = buildNav(ctx({ can: { competitions: true, operate: true, users: false, market: false } }));

    assert.deepEqual(menu.top.map((i) => i.label), ['Painel', 'Mesário']);
    assert.deepEqual(labels(menu, 'competition'), ['Competições', 'Inscrições', 'Equipes', 'Atletas e comissão', 'Estádios', 'Documentos a vencer', 'Árbitros e mesários', 'Relatórios']);
    assert.deepEqual(labels(menu, 'broadcast'), ['Transmissão', 'Artes', 'Widgets']);
});

test('a administração é um grupo separado só de quem gerencia usuários', () => {
    const menu = buildNav(ctx({ can: { competitions: true, operate: true, users: true, market: true } }));
    const admin = menu.groups.find((g) => g.id === 'admin');

    assert.equal(admin.area, 'admin');
    assert.deepEqual(admin.items.map((i) => i.label), ['Usuários', 'Perfis de acesso', 'Auditoria de acesso', 'Portal público', 'Comunicações', 'Notas da ajuda', 'Parâmetros', 'Termos e privacidade']);
    assert.ok(labels(menu, 'competition').includes('Mercado de jogadores'));
    assert.deepEqual(menu.groups.map((g) => g.id), ['competition', 'broadcast', 'admin']);
});

test('Super Admin vê o grupo Plataforma; sem papel só tem plataforma e ajuda da plataforma', () => {
    const menu = buildNav(ctx({ platformAdmin: true }));

    assert.deepEqual(labels(menu, 'platform'), ['Organizações', 'Status page', 'Contatos da landing', 'Medição da landing', 'Documentação das APIs']);

    const only = buildNav({ role: null, platformAdmin: true, features: [], can: { competitions: false, operate: false, users: false, market: false } });

    assert.deepEqual(only.bottom.map((i) => i.route), ['platform.help.index']);
    assert.deepEqual(buildNav({ role: null, platformAdmin: false, features: [], can: { competitions: false, operate: false, users: false, market: false } }).bottom, []);
});

test('recursos fora do pacote somem e grupo vazio não aparece', () => {
    const menu = buildNav(ctx({ features: [], can: { competitions: true, operate: false, users: false, market: false } }));

    assert.deepEqual(labels(menu, 'competition'), ['Competições', 'Equipes', 'Atletas e comissão', 'Estádios', 'Documentos a vencer', 'Árbitros e mesários']);
    assert.equal(menu.groups.some((g) => g.id === 'broadcast'), false);
});

test('gestor e atleta têm menu simples, sem grupos', () => {
    const manager = buildNav(ctx({ role: 'manager', features: [] }));

    assert.deepEqual(manager.top.map((i) => i.label), ['Início', 'Jogos e escalações']);
    assert.equal(manager.groups.length, 0);
    assert.deepEqual(manager.bottom.map((i) => i.route), ['manager.help.index']);
    assert.deepEqual(buildNav(ctx({ role: 'manager' })).top.map((i) => i.label), ['Início', 'Jogos e escalações', 'Mercado de jogadores']);
    assert.deepEqual(buildNav(ctx({ role: 'athlete' })).top.map((i) => i.route), ['athlete.dashboard']);
});

test('abertura dos grupos: escolha salva, padrão e grupo da página atual', () => {
    const menu = buildNav(ctx({ platformAdmin: true, can: { competitions: true, operate: false, users: true, market: false } }));
    const [competition, , admin] = menu.groups;

    assert.equal(groupOpen(competition, {}, null), true);
    assert.equal(groupOpen(admin, {}, null), false);
    assert.equal(groupOpen(competition, { competition: false }, null), false);
    assert.equal(groupOpen(admin, { admin: false }, 'admin'), true);
    assert.equal(activeGroupOf(menu, (match) => match === 'panel.users.*'), 'admin');
    assert.equal(activeGroupOf(menu, (match) => match === 'panel.dashboard'), null);
});

test('cartões do início repetem o menu, sem o próprio início, e todos têm frase', async () => {
    const { quickSections } = await import(out);
    const menu = buildNav(ctx({ role: 'admin', can: { competitions: true, operate: true, users: true, market: false } }));
    const sections = quickSections(menu);
    const routes = sections.flatMap((section) => section.items.map((entry) => entry.route));

    assert.equal(routes.includes('panel.dashboard'), false);
    assert.equal(routes.includes('panel.teams.index'), true);
    assert.equal(routes.includes('panel.help.index'), true);
    assert.equal(sections.every((section) => section.items.every((entry) => entry.hint !== '')), true);
    assert.deepEqual(sections.map((section) => section.area).includes('admin'), true);
    assert.equal(quickSections(buildNav(ctx({ role: 'manager' }))).flatMap((section) => section.items).some((entry) => entry.route === 'manager.dashboard'), false);
});
