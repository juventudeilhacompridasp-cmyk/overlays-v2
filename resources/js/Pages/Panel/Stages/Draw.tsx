import { Button, Card, Empty, Field, Input, LinkButton, Logo, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import {
    GroupsBoard,
    KnockoutBoard,
    POOL,
    bestOf,
    bracketClashes,
    bracketOf,
    resizeBracket,
    bracketProblems,
    clearBracket,
    clearGroups,
    emptyBracket,
    emptyGroups,
    emptyLeague,
    groupClashes,
    groupsPayload,
    groupsProblems,
    leaguePayload,
    leagueProblems,
    locationOf,
    moveToBracketPool,
    moveToGroup,
    moveToSlot,
    moveLeagueToken,
    normalizeCity,
    pairsPayload,
    potGroups,
    randomBracket,
    randomGroups,
    renameGroup,
    resizeGroups,
    roundNames,
    seededBracket,
    slotIndexOf,
    snakeGroups,
} from '@/lib/draw';
import { DndContext, DragEndEvent, DragOverlay, DragStartEvent, KeyboardSensor, MouseSensor, TouchSensor, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

interface TeamInfo {
    id: string;
    name: string;
    short_name: string | null;
    logo_url: string | null;
    color: string | null;
    seed: number | null;
    city: string | null;
}

interface Props {
    competition: { id: string; name: string };
    season: { id: string; name: string };
    division: { id: string; name: string };
    stage: { id: string; name: string; type: 'league' | 'groups' | 'knockout'; type_label: string };
    entries: TeamInfo[];
    blocked: string | null;
    supported: boolean;
}

/** Equipe arrastável. No celular (ponteiro "grosso") aparece também um seletor "Mover para…" como alternativa ao arrasto. */
function Chip({ team, options, value, onMove }: { team: TeamInfo; options: { value: string; label: string }[]; value: string; onMove: (target: string) => void }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: team.id });

    return (
        <div ref={setNodeRef} className={`flex min-h-11 items-center gap-2 rounded-lg bg-white px-2.5 py-1.5 text-sm shadow-sm ring-1 ring-gray-200 ${isDragging ? 'opacity-40' : ''}`}>
            <button type="button" {...listeners} {...attributes} aria-label={`Arrastar ${team.name}`} className="flex min-w-0 flex-1 cursor-grab items-center gap-2 text-left active:cursor-grabbing">
                <Logo src={team.logo_url} name={team.name} size={24} color={team.color} />
                <span className="truncate font-medium text-gray-900">{team.name}</span>
                {team.seed !== null && <span className="shrink-0 text-xs text-gray-400">#{team.seed}</span>}
            </button>
            <select
                aria-label={`Mover ${team.name} para`}
                value={value}
                onChange={(event) => onMove(event.target.value)}
                className="hidden max-w-[8.5rem] rounded-md border-gray-300 py-1 pl-2 pr-7 text-xs text-gray-700 [@media(pointer:coarse)]:block"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function Zone({ id, children, className = '', label }: { id: string; children: ReactNode; className?: string; label: string }) {
    const { setNodeRef, isOver } = useDroppable({ id });

    return (
        <div ref={setNodeRef} role="group" aria-label={label} className={`rounded-lg border-2 border-dashed p-2 transition ${isOver ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 bg-gray-50'} ${className}`}>
            {children}
        </div>
    );
}

function LeagueSlot({ token, team }: { token: string; team: TeamInfo }) {
    const { setNodeRef, listeners, attributes, isDragging } = useDraggable({ id: token });
    const { setNodeRef: setDropRef, isOver } = useDroppable({ id: token });

    return (
        <div ref={setDropRef} className={`rounded-md p-0.5 transition ${isOver ? 'bg-emerald-100' : ''}`}>
            <div ref={setNodeRef} {...listeners} {...attributes} className={`flex min-h-11 items-center gap-2 rounded-md bg-white px-2 text-sm ring-1 ring-gray-200 ${isDragging ? 'opacity-40' : ''}`}>
                <span className="cursor-grab text-gray-400" aria-hidden="true">⋮⋮</span>
                <Logo src={team.logo_url} name={team.name} size={22} color={team.color} />
                <span className="truncate font-medium text-gray-900">{team.name}</span>
            </div>
        </div>
    );
}

export default function Draw({ competition, season, division, stage, entries, blocked, supported }: Props) {
    const byId = useMemo(() => new Map(entries.map((team) => [team.id, team])), [entries]);
    const ids = useMemo(() => entries.map((team) => team.id), [entries]);
    const isKnockout = stage.type === 'knockout';
    const isLeague = stage.type === 'league';

    const [groups, setGroups] = useState<GroupsBoard>(() => emptyGroups(ids, Math.min(Math.max(1, Math.floor(ids.length / 2)), ids.length >= 4 ? 2 : 1)));
    const [bracket, setBracket] = useState<KnockoutBoard>(() => emptyBracket(ids));
    const [league, setLeague] = useState(() => emptyLeague(ids));
    const [doubleRound, setDoubleRound] = useState(false);
    const [legs, setLegs] = useState(1);
    const [teamCount, setTeamCount] = useState(ids.length);
    const [active, setActive] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const cities = useMemo(() => new Map(entries.map((team) => [team.id, normalizeCity(team.city)])), [entries]);
    const cityOf = (id: string) => cities.get(id) ?? '';
    const hasSharedCities = useMemo(() => {
        const seen = new Set<string>();
        return entries.some((team) => {
            const city = normalizeCity(team.city);
            if (city === '') return false;
            if (seen.has(city)) return true;
            seen.add(city);
            return false;
        });
    }, [entries]);
    const [separateCities, setSeparateCities] = useState(true);

    /** Roda o sorteio; com a opção ligada tenta várias vezes e fica com o que tem menos equipes da mesma cidade juntas. */
    const draw = <T,>(make: () => T, conflicts: (candidate: T) => number): T => (separateCities && hasSharedCities ? bestOf(300, make, conflicts) : make());
    const clashes = isKnockout ? bracketClashes(bracket, cityOf) : isLeague ? [] : groupClashes(groups, cityOf);
    const nameOf = (id: string) => byId.get(id)?.name ?? id;

    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 6 } }),
        // No toque o arrasto começa com um toque longo, para a página continuar rolando normalmente.
        useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 8 } }),
        useSensor(KeyboardSensor),
    );

    const partial = isKnockout && teamCount < ids.length;
    const problems = isKnockout ? bracketProblems(bracket, partial) : isLeague ? leagueProblems(league, ids, doubleRound) : groupsProblems(groups);
    const pairs = bracket.slots.length / 2;

    const move = (teamId: string, target: string) => {
        if (isKnockout) {
            setBracket((board) => (target === POOL ? moveToBracketPool(board, teamId) : moveToSlot(board, teamId, Number(target.replace('slot-', '')))));
        } else {
            setGroups((board) => moveToGroup(board, teamId, target));
        }
    };

    const onDragEnd = (event: DragEndEvent) => {
        setActive(null);
        if (event.over) {
            if (isLeague) setLeague((board) => moveLeagueToken(board, String(event.active.id), String(event.over?.id)));
            else move(String(event.active.id), String(event.over.id));
        }
    };

    const save = () => {
        if (problems.length > 0 || saving) return;
        if (!window.confirm('Salvar o sorteio e gerar a tabela de jogos? Para refazer, será preciso limpar a tabela da fase.')) return;

        setSaving(true);
        const options = { preserveScroll: true, onFinish: () => setSaving(false) };

        if (isKnockout) router.post(route('panel.stages.draw.store', stage.id), { legs, partial, pairs: pairsPayload(bracket) }, options);
        else if (isLeague) router.post(route('panel.stages.draw.store', stage.id), { double_round: doubleRound, pairs: leaguePayload(league) }, options);
        else router.post(route('panel.stages.draw.store', stage.id), { double_round: doubleRound, groups: groupsPayload(groups) }, options);
    };

    const groupOptions = [{ value: POOL, label: 'Sem grupo' }, ...groups.groups.map((group) => ({ value: group.id, label: group.name || 'Grupo' }))];
    const slotOptions = [
        { value: POOL, label: 'Fora da chave' },
        ...bracket.slots.map((_, index) => ({ value: `slot-${index}`, label: `Confronto ${Math.floor(index / 2) + 1} · lado ${index % 2 === 0 ? 'A' : 'B'}` })),
    ];

    const header = (
        <PageHeader
            title={`Sorteio e chaves · ${stage.name}`}
            subtitle={`${stage.type_label} · ${division.name} · ${season.name}`}
            breadcrumbs={[
                { label: 'Competições', href: route('panel.competitions.index') },
                { label: competition.name, href: route('panel.competitions.show', competition.id) },
                { label: season.name, href: route('panel.seasons.show', season.id) },
                { label: division.name, href: route('panel.divisions.show', division.id) },
                { label: stage.name, href: route('panel.stages.show', stage.id) },
                { label: 'Sorteio' },
            ]}
        />
    );

    if (blocked || !supported || entries.length < 2) {
        return (
            <PanelLayout>
                <Head title="Sorteio e chaves" />
                {header}
                <Card>
                    <Empty title={blocked ?? (!supported ? 'Esta fase não aceita sorteio.' : 'Confirme pelo menos duas equipes na divisão.')}>
                        Volte à fase para continuar.
                    </Empty>
                    <div className="mt-4">
                        <LinkButton href={route('panel.stages.show', stage.id)} variant="secondary">
                            Voltar à fase
                        </LinkButton>
                    </div>
                </Card>
            </PanelLayout>
        );
    }

    const pool = isKnockout ? bracket.pool : groups.pool;

    const poolZone = (
        <Card title={`Equipes ${isKnockout ? 'fora da chave' : 'sem grupo'} (${pool.length})`}>
            <Zone id={POOL} label="Equipes ainda não posicionadas" className="min-h-16">
                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                    {pool.map((id) => (
                        <Chip key={id} team={byId.get(id)!} options={isKnockout ? slotOptions : groupOptions} value={POOL} onMove={(target) => move(id, target)} />
                    ))}
                    {pool.length === 0 && <p className="p-2 text-sm text-gray-500">Todas as equipes já estão posicionadas.</p>}
                </div>
            </Zone>
        </Card>
    );

    const actions = (
        <div className="sticky bottom-0 z-10 -mx-4 mt-4 border-t border-gray-200 bg-white/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:rounded-lg sm:border sm:px-4">
            {problems.length > 0 && (
                <ul className="mb-2 list-disc space-y-0.5 pl-5 text-xs text-amber-700" data-testid="draw-problems">
                    {problems.map((problem) => (
                        <li key={problem}>{problem}</li>
                    ))}
                </ul>
            )}
            {clashes.length > 0 && (
                <ul className="mb-2 list-disc space-y-0.5 pl-5 text-xs text-amber-700" data-testid="draw-clashes">
                    {clashes.map((clash) => (
                        <li key={`${clash.where}-${clash.city}`}>
                            Mesma cidade ({clash.city}) juntas {isKnockout ? `no confronto ${Number(clash.where) + 1}` : `no ${groups.groups.find((group) => group.id === clash.where)?.name ?? 'grupo'}`}: {clash.teams.map(nameOf).join(' e ')}. Pode salvar assim, é só um aviso.
                        </li>
                    ))}
                </ul>
            )}
            {hasSharedCities && (
                <label className="mb-2 flex min-h-11 items-center gap-2 text-sm text-gray-700 sm:min-h-0">
                    <input type="checkbox" checked={separateCities} onChange={(event) => setSeparateCities(event.target.checked)} />
                    Evitar equipes da mesma cidade juntas nos sorteios
                </label>
            )}
            <div className="flex flex-wrap items-center gap-2">
                {isKnockout ? (
                    <>
                        <Button variant="secondary" onClick={() => setBracket(draw(() => bracketOf(ids, teamCount, randomBracket), (candidate) => bracketClashes(candidate, cityOf).length))}>
                            Sortear
                        </Button>
                        <Button variant="secondary" onClick={() => setBracket(bracketOf(ids, teamCount, seededBracket))}>
                            Por cabeças de chave
                        </Button>
                        <Button variant="ghost" onClick={() => setBracket(clearBracket(bracket))}>
                            Limpar
                        </Button>
                    </>
                ) : isLeague ? (
                    <Button variant="ghost" onClick={() => setLeague(emptyLeague(ids, doubleRound))}>
                        Restaurar tabela padrão
                    </Button>
                ) : (
                    <>
                        <Button variant="secondary" onClick={() => setGroups(draw(() => randomGroups(groups, ids), (candidate) => groupClashes(candidate, cityOf).length))}>
                            Sortear
                        </Button>
                        <Button variant="secondary" onClick={() => setGroups(draw(() => potGroups(groups, ids), (candidate) => groupClashes(candidate, cityOf).length))}>
                            Sorteio por potes
                        </Button>
                        <Button variant="secondary" onClick={() => setGroups(snakeGroups(groups, ids))}>
                            Serpentina
                        </Button>
                        <Button variant="ghost" onClick={() => setGroups(clearGroups(groups))}>
                            Limpar
                        </Button>
                    </>
                )}
                <Button className="ml-auto" disabled={problems.length > 0 || saving} onClick={save}>
                    Salvar sorteio e gerar jogos
                </Button>
            </div>
        </div>
    );

    return (
        <PanelLayout>
            <Head title="Sorteio e chaves" />
            {header}

            <DndContext sensors={sensors} onDragStart={(event: DragStartEvent) => setActive(String(event.active.id))} onDragCancel={() => setActive(null)} onDragEnd={onDragEnd}>
                <p className="mb-4 text-sm text-gray-600">
                    Arraste as equipes (no celular, segure e arraste, ou use “Mover para…”) ou use um sorteio e ajuste depois. Serve também para sorteios feitos fora da plataforma: monte do jeito que foi sorteado. Nada é gravado até você salvar.
                </p>

                <div className="grid gap-4 xl:grid-cols-[18rem_1fr]">
                    {!isLeague && poolZone}

                    {isKnockout ? (
                        <div className="space-y-4">
                            <Card title="Como serão os jogos">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Jogos por confronto" htmlFor="legs">
                                        <Select id="legs" value={String(legs)} onChange={(event) => setLegs(Number(event.target.value))}>
                                            <option value="1">Jogo único</option>
                                            <option value="2">Ida e volta</option>
                                        </Select>
                                    </Field>
                                    <Field label="Equipes na chave" htmlFor="team-count" hint="Use menos que o total quando só parte das equipes avança (ex.: os classificados dos grupos).">
                                        <Select
                                            id="team-count"
                                            value={String(teamCount)}
                                            onChange={(event) => {
                                                const count = Number(event.target.value);
                                                setTeamCount(count);
                                                setBracket((board) => resizeBracket(board, count));
                                            }}
                                        >
                                            {Array.from({ length: ids.length - 1 }, (_, index) => ids.length - index).map((count) => (
                                                <option key={count} value={count}>
                                                    {count === ids.length ? `Todas (${count})` : `${count} equipes`}
                                                </option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <p className="self-end text-sm text-gray-600">
                                        {pairs} confronto(s) na 1ª rodada, depois {roundNames(pairs).slice(1).join(' → ') || '—'}. Vaga vazia = folga: a equipe passa direto.
                                    </p>
                                </div>
                            </Card>
                            <div className="grid gap-3 sm:grid-cols-2" data-testid="draw-bracket">
                                {Array.from({ length: pairs }, (_, pair) => (
                                    <Card key={pair} title={`${roundNames(pairs)[0]} · confronto ${pair + 1}`}>
                                        <div className="space-y-2">
                                            {[0, 1].map((side) => {
                                                const index = pair * 2 + side;
                                                const id = bracket.slots[index];
                                                const other = bracket.slots[pair * 2 + (1 - side)];

                                                return (
                                                    <Zone key={side} id={`slot-${index}`} label={`Confronto ${pair + 1}, lado ${side === 0 ? 'A' : 'B'}`} className="min-h-14">
                                                        {id ? (
                                                            <Chip team={byId.get(id)!} options={slotOptions} value={`slot-${slotIndexOf(bracket, id)}`} onMove={(target) => move(id, target)} />
                                                        ) : (
                                                            <p className="flex min-h-11 items-center px-2 text-sm text-gray-400">{other ? 'Folga: arraste uma equipe para jogar aqui' : 'Arraste uma equipe'}</p>
                                                        )}
                                                    </Zone>
                                                );
                                            })}
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    ) : isLeague ? (
                        <div className="space-y-4">
                            <Card title="Como serão os jogos">
                                <Field label="Turnos" htmlFor="league-double">
                                    <Select id="league-double" value={doubleRound ? '1' : '0'} onChange={(event) => {
                                        const next = event.target.value === '1';
                                        setDoubleRound(next);
                                        setLeague(emptyLeague(ids, next));
                                    }}>
                                        <option value="0">Turno único</option>
                                        <option value="1">Ida e volta (turno e returno)</option>
                                    </Select>
                                </Field>
                                <p className="mt-2 text-sm text-gray-600">Arraste uma equipe para outra vaga para trocar os confrontos. A ordem das rodadas e o mando ficam exatamente como você montar.</p>
                            </Card>
                            <div className="grid gap-3 md:grid-cols-2 2xl:grid-cols-3" data-testid="draw-league">
                                {league.rounds.map((round, roundIndex) => (
                                    <Card key={round.id} title={`Rodada ${roundIndex + 1}`}>
                                        <div className="space-y-2">
                                            {round.games.map((game, gameIndex) => (
                                                <div key={`${round.id}-${gameIndex}`} className="rounded-lg border border-gray-200 bg-gray-50 p-2">
                                                    <div className="mb-1 text-xs font-medium text-gray-500">Jogo {gameIndex + 1}</div>
                                                    {[0, 1].map((side) => {
                                                        const id = game[side as 0 | 1];
                                                        const token = `round-${roundIndex}-${gameIndex}-${side}`;
                                                        return (
                                                            <LeagueSlot key={token} token={token} team={byId.get(id)!} />
                                                        );
                                                    })}
                                                    <div className="mt-1 text-center text-xs text-gray-400">x</div>
                                                </div>
                                            ))}
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <Card title="Grupos">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Número de grupos" htmlFor="group-count">
                                        <div className="flex items-center gap-2">
                                            <Button variant="secondary" aria-label="Menos um grupo" onClick={() => setGroups(resizeGroups(groups, groups.groups.length - 1))} disabled={groups.groups.length <= 1}>
                                                −
                                            </Button>
                                            <span id="group-count" className="w-10 text-center text-lg font-semibold tabular-nums">
                                                {groups.groups.length}
                                            </span>
                                            <Button variant="secondary" aria-label="Mais um grupo" onClick={() => setGroups(resizeGroups(groups, groups.groups.length + 1))} disabled={groups.groups.length >= Math.min(16, Math.floor(ids.length / 2))}>
                                                +
                                            </Button>
                                        </div>
                                    </Field>
                                    <Field label="Turnos" htmlFor="double">
                                        <Select id="double" value={doubleRound ? '1' : '0'} onChange={(event) => setDoubleRound(event.target.value === '1')}>
                                            <option value="0">Turno único</option>
                                            <option value="1">Ida e volta (turno e returno)</option>
                                        </Select>
                                    </Field>
                                </div>
                            </Card>
                            <div className="grid gap-3 md:grid-cols-2 2xl:grid-cols-3" data-testid="draw-groups">
                                {groups.groups.map((group) => (
                                    <Card key={group.id}>
                                        <Input aria-label={`Nome do ${group.name}`} value={group.name} maxLength={40} onChange={(event) => setGroups(renameGroup(groups, group.id, event.target.value))} className="mb-2 font-semibold" />
                                        <Zone id={group.id} label={group.name || 'Grupo'} className="min-h-24">
                                            <div className="grid gap-2">
                                                {group.teams.map((id) => (
                                                    <Chip key={id} team={byId.get(id)!} options={groupOptions} value={locationOf(groups, id)} onMove={(target) => move(id, target)} />
                                                ))}
                                                {group.teams.length === 0 && <p className="p-2 text-sm text-gray-400">Arraste as equipes para cá</p>}
                                            </div>
                                        </Zone>
                                        <p className="mt-1 text-xs text-gray-500">{group.teams.length} equipe(s)</p>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {actions}

                <DragOverlay>
                    {active && byId.get(active) ? (
                        <div className="flex min-h-11 items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-sm font-medium shadow-lg ring-2 ring-emerald-500">
                            <Logo src={byId.get(active)!.logo_url} name={byId.get(active)!.name} size={24} color={byId.get(active)!.color} />
                            {byId.get(active)!.name}
                        </div>
                    ) : null}
                </DragOverlay>
            </DndContext>
        </PanelLayout>
    );
}
