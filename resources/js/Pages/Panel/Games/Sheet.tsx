import { Badge, Button, Card, Field, Input, Logo, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { GameClock, formatClock, periodLabel, shownSeconds, useGameClock } from '@/lib/clock';
import { Option } from '@/types/panel';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

interface Person {
    id: string;
    name: string;
    shirt_number: number | null;
    role: string;
    role_label: string;
    is_athlete: boolean;
    suspended_games: number;
}

interface Team {
    id: string;
    name: string;
    logo: string | null;
    roster: Person[];
    lineup: { starters: string[]; bench: string[]; staff: string[] };
    has_lineup: boolean;
    tactic: { formation: string | null; confirmed: boolean };
    has_events: boolean;
    on_field: string[];
    available_bench: string[];
    expelled: string[];
    substitutions_used: number;
    windows_used: number;
    fouled_out: string[];
    player_fouls: Record<string, number>;
}

interface SheetEvent {
    id: string;
    type: string;
    entry_id: string | null;
    person: string | null;
    secondary_person: string | null;
    period: number;
    minute: number | null;
    quick: boolean;
    voidable: boolean;
}

interface Props {
    game: {
        id: string;
        status: string;
        status_label: string;
        open: boolean;
        home_score: number;
        away_score: number;
        knockout: boolean;
        home_penalties: number | null;
        away_penalties: number | null;
        stage_id: string;
        stage: string;
        division: string;
        division_id: string;
        season: string;
        season_id: string;
        competition: string;
        competition_id: string;
    };
    home: Team | null;
    away: Team | null;
    events: SheetEvent[];
    limits: { starters: number; bench: number; staff: number; substitutions: number; windows: number };
    event_types: Option[];
    sport: string;
    score_model: string;
    quarters: { period: number; home: number; away: number }[] | null;
    sets: SetScore[] | null;
    sets_to_win: number;
    fouls: { home: number; away: number; limit: number; period: number } | null;
    clock: GameClock & { server_time_ms: number; max_period: number };
    public_url: string;
}

export interface SetScore {
    home: number;
    away: number;
    winner: 'home' | 'away' | null;
    finished: boolean;
}

type Slot = '' | 'starter' | 'bench' | 'staff';

const typeIcon: Record<string, string> = {
    goal: '⚽',
    own_goal: '⚽ (contra)',
    yellow_card: '🟨',
    red_card: '🟥',
    blue_card: '🟦',
    substitution: '🔁',
    period_start: '▶️',
    period_end: '⏸️',
    foul: '🚫',
    point: '🏐',
    free_throw: '🏀 (1)',
    field_goal: '🏀 (2)',
    three_pointer: '🏀 (3)',
    clock_pause: '⏱️⏸️',
    clock_resume: '⏱️▶️',
};

function personName(team: Team, id: string): string {
    const person = team.roster.find((row) => row.id === id);
    return person ? `${person.shirt_number ? `${person.shirt_number} · ` : ''}${person.name}` : id;
}

function LineupEditor({ game, team, limits, locked, onSaved }: { game: Props['game']; team: Team; limits: Props['limits']; locked: boolean; onSaved: () => void }) {
    const initial = useMemo(() => {
        const slots: Record<string, Slot> = {};
        team.lineup.starters.forEach((id) => (slots[id] = 'starter'));
        team.lineup.bench.forEach((id) => (slots[id] = 'bench'));
        team.lineup.staff.forEach((id) => (slots[id] = 'staff'));
        return slots;
    }, [team.lineup]);

    const [slots, setSlots] = useState<Record<string, Slot>>(initial);
    const [saving, setSaving] = useState(false);

    const ids = (slot: Slot) => team.roster.filter((person) => slots[person.id] === slot).map((person) => person.id);
    const counts = { starter: ids('starter').length, bench: ids('bench').length, staff: ids('staff').length };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSaving(true);
        router.put(
            route('panel.games.lineup', [game.id, team.id]),
            { starters: ids('starter'), bench: ids('bench'), staff: ids('staff') },
            { preserveScroll: true, onSuccess: (page) => {
                    if (!(page.props.flash as { error?: string } | undefined)?.error) {
                        onSaved();
                    }
                },
                onFinish: () => setSaving(false) },
        );
    };

    if (team.roster.length === 0) {
        return <p className="text-sm text-gray-500">O elenco desta equipe está vazio. Cadastre atletas na inscrição antes de montar a escalação.</p>;
    }

    return (
        <form onSubmit={submit} className="space-y-3">
            <div className="flex flex-wrap gap-2 text-xs">
                <Badge tone={counts.starter > limits.starters ? 'red' : 'green'}>
                    Titulares {counts.starter}/{limits.starters}
                </Badge>
                <Badge tone={counts.bench > limits.bench ? 'red' : 'blue'}>
                    Reservas {counts.bench}/{limits.bench}
                </Badge>
                <Badge tone={counts.staff > limits.staff ? 'red' : 'gray'}>
                    Comissão {counts.staff}/{limits.staff}
                </Badge>
            </div>
            <ul className="divide-y divide-gray-100 rounded-md border border-gray-200">
                {team.roster.map((person) => {
                    const blocked = person.is_athlete && person.suspended_games > 0;
                    return (
                        <li key={person.id} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                            <div className="min-w-0">
                                <span className="mr-2 inline-block w-6 text-right tabular-nums text-gray-400">{person.shirt_number ?? ''}</span>
                                <span className={blocked ? 'text-gray-400 line-through' : 'text-gray-900'}>{person.name}</span>
                                {!person.is_athlete && <span className="ml-2 text-xs text-gray-500">{person.role_label}</span>}
                                {blocked && (
                                    <span className="ml-2">
                                        <Badge tone="red">Suspenso ({person.suspended_games})</Badge>
                                    </span>
                                )}
                            </div>
                            <Select
                                aria-label={`Função de ${person.name} na súmula`}
                                className="!w-32"
                                value={blocked ? '' : (slots[person.id] ?? '')}
                                disabled={locked || blocked}
                                onChange={(event) => setSlots({ ...slots, [person.id]: event.target.value as Slot })}
                            >
                                <option value="">—</option>
                                {person.is_athlete ? (
                                    <>
                                        <option value="starter">Titular</option>
                                        <option value="bench">Reserva</option>
                                    </>
                                ) : (
                                    <option value="staff">Comissão</option>
                                )}
                            </Select>
                        </li>
                    );
                })}
            </ul>
            {!locked && (
                <Button type="submit" disabled={saving}>
                    Salvar escalação
                </Button>
            )}
        </form>
    );
}

function ClockCard({ game, clock, publicUrl, sport }: { game: Props['game']; clock: Props['clock']; publicUrl: string; sport: string }) {
    const skew = useMemo(() => Date.now() - clock.server_time_ms, [clock.server_time_ms]);
    const seconds = shownSeconds(clock, useGameClock(clock, skew));
    const post = (action: 'start' | 'end', period: number) =>
        router.post(route('panel.games.periods.store', game.id), { action, period }, { preserveScroll: true });
    const pause = (value: boolean) => router.post(route('panel.games.clock.pause', game.id), { pause: value }, { preserveScroll: true });

    const next = clock.period === null ? 1 : clock.period + 1;
    const live = clock.state === 'running' || clock.state === 'paused';

    return (
        <Card title="Cronômetro">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="text-3xl font-bold tabular-nums" data-testid="panel-clock">
                        {formatClock(seconds)}
                    </div>
                    <div className="text-sm text-gray-500">
                        {clock.state === 'not_started' && 'Não iniciado'}
                        {clock.state === 'running' && `${periodLabel(clock.period, sport)} em andamento`}
                        {clock.state === 'paused' && `${periodLabel(clock.period, sport)} · relógio parado`}
                        {clock.state === 'stopped' && `${periodLabel(clock.period, sport)} encerrado`}
                    </div>
                </div>
                {game.open && (
                    <div className="flex flex-wrap gap-2">
                        {clock.pausable && clock.state === 'running' && <Button onClick={() => pause(true)}>Parar relógio</Button>}
                        {clock.pausable && clock.state === 'paused' && <Button onClick={() => pause(false)}>Retomar relógio</Button>}
                        {live && clock.period !== null && (
                            <Button variant="secondary" onClick={() => post('end', clock.period as number)}>
                                Encerrar {periodLabel(clock.period, sport)}
                            </Button>
                        )}
                        {!live && next <= clock.max_period && <Button onClick={() => post('start', next)}>Iniciar {periodLabel(next, sport)}</Button>}
                    </div>
                )}
            </div>
            <p className="mt-3 text-xs text-gray-500">
                Placar público:{' '}
                <a href={publicUrl} target="_blank" rel="noreferrer" className="text-emerald-700 underline">
                    {publicUrl}
                </a>
            </p>
        </Card>
    );
}

/** Basquete: toque rápido por atleta em quadra (+1, +2, +3 e falta). Quem foi eliminado por faltas não pontua nem comete falta. */
function BasketPad({ game, team, period }: { game: Props['game']; team: Team; period: number }) {
    const send = (type: string, personId: string) =>
        router.post(route('panel.games.events.store', game.id), { type, entry_id: team.id, person_id: personId, period }, { preserveScroll: true });

    return (
        <Card title={team.name}>
            <ul className="divide-y divide-gray-100" data-testid={`basket-pad-${team.id}`}>
                {team.on_field.map((id) => {
                    const out = team.fouled_out.includes(id);
                    const fouls = team.player_fouls[id] ?? 0;

                    return (
                        <li key={id} className="flex items-center justify-between gap-2 py-2 text-sm">
                            <span className={out ? 'text-gray-400 line-through' : ''}>
                                {personName(team, id)} <span className="ml-1 text-xs text-gray-500">F{fouls}</span>
                                {out && <span className="ml-1 text-xs text-red-600">eliminado</span>}
                            </span>
                            {game.open && (
                                <span className="flex gap-1">
                                    {[['free_throw', '+1'], ['field_goal', '+2'], ['three_pointer', '+3']].map(([type, label]) => (
                                        <Button key={type} small disabled={out} onClick={() => send(type, id)}>
                                            {label}
                                        </Button>
                                    ))}
                                    <Button small variant="secondary" disabled={out} onClick={() => send('foul', id)}>
                                        Falta
                                    </Button>
                                </span>
                            )}
                        </li>
                    );
                })}
                {team.on_field.length === 0 && <li className="py-2 text-sm text-gray-500">Salve a escalação para ver os atletas em quadra.</li>}
            </ul>
        </Card>
    );
}

function PointsCard({ game, sets, home, away, lastPoint }: { game: Props['game']; sets: SetScore[]; home: Team; away: Team; lastPoint: SheetEvent | undefined }) {
    const current = sets.length > 0 && !sets[sets.length - 1].finished ? sets[sets.length - 1] : null;
    const add = (side: 'home' | 'away') => router.post(route('panel.games.points.store', game.id), { side }, { preserveScroll: true });

    return (
        <Card title={current ? `Set ${sets.length} em andamento` : sets.length > 0 ? 'Partida encerrada' : 'Pontos'}>
            <div className="grid grid-cols-2 gap-4 text-center" data-testid="panel-points">
                {([['home', home], ['away', away]] as const).map(([side, team]) => (
                    <div key={side} className="space-y-2">
                        <div className="text-sm text-gray-500">{team.name}</div>
                        <div className="text-5xl font-bold tabular-nums" data-testid={`set-points-${side}`}>
                            {current ? current[side] : 0}
                        </div>
                        {game.open && (
                            <Button className="w-full py-3 text-base" onClick={() => add(side)} data-testid={`point-${side}`}>
                                +1 ponto
                            </Button>
                        )}
                    </div>
                ))}
            </div>
            {game.open && lastPoint && (
                <div className="mt-3 text-center">
                    <Button variant="secondary" small onClick={() => router.post(route('panel.games.events.void', [game.id, lastPoint.id]), {}, { preserveScroll: true })}>
                        Desfazer último ponto
                    </Button>
                </div>
            )}
            {sets.some((set) => set.finished) && (
                <ul className="mt-4 flex flex-wrap justify-center gap-2 text-sm tabular-nums">
                    {sets.filter((set) => set.finished).map((set, index) => (
                        <li key={index} className="rounded bg-gray-100 px-2 py-1">
                            Set {index + 1}: {set.home} x {set.away}
                        </li>
                    ))}
                </ul>
            )}
            <p className="mt-3 text-xs text-gray-500">Cada toque lança um ponto de rally; o set termina sozinho ao atingir a pontuação com a vantagem mínima. Só o último ponto pode ser desfeito.</p>
        </Card>
    );
}

function ShootoutCard({ game, home, away }: { game: Props['game']; home: Team; away: Team }) {
    const [homePens, setHomePens] = useState(game.home_penalties?.toString() ?? '');
    const [awayPens, setAwayPens] = useState(game.away_penalties?.toString() ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(route('panel.games.shootout', game.id), { home_penalties: homePens === '' ? null : homePens, away_penalties: awayPens === '' ? null : awayPens }, { preserveScroll: true });
    };

    return (
        <Card title="Disputa de pênaltis">
            <form onSubmit={submit} className="flex flex-wrap items-end gap-3" aria-label="Disputa de pênaltis">
                <Field label={`${home.name} (convertidos)`}>
                    <Input type="number" min={0} max={30} value={homePens} onChange={(event) => setHomePens(event.target.value)} />
                </Field>
                <Field label={`${away.name} (convertidos)`}>
                    <Input type="number" min={0} max={30} value={awayPens} onChange={(event) => setAwayPens(event.target.value)} />
                </Field>
                <Button type="submit">Salvar pênaltis</Button>
                <p className="w-full text-xs text-gray-500">Só no jogo que decide o confronto empatado. Deixe em branco e salve para remover a disputa.</p>
            </form>
        </Card>
    );
}

function EventForm({ game, home, away, eventTypes }: { game: Props['game']; home: Team; away: Team; eventTypes: Option[] }) {
    const [type, setType] = useState('goal');
    const [entryId, setEntryId] = useState(home.id);
    const [personId, setPersonId] = useState('');
    const [secondaryId, setSecondaryId] = useState('');
    const [period, setPeriod] = useState('1');
    const [minute, setMinute] = useState('');
    const [sending, setSending] = useState(false);

    const team = entryId === home.id ? home : away;
    const isSubstitution = type === 'substitution';
    const isCard = ['yellow_card', 'red_card', 'blue_card'].includes(type);

    const outsideField = (id: string) => !team.on_field.includes(id);
    const candidates = team.roster.filter((person) => {
        if (isCard) {
            return !team.expelled.includes(person.id) && (!outsideField(person.id) || team.available_bench.includes(person.id) || team.lineup.staff.includes(person.id));
        }
        return team.on_field.includes(person.id);
    });
    const incoming = team.roster.filter((person) => team.available_bench.includes(person.id));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setSending(true);
        router.post(
            route('panel.games.events.store', game.id),
            { type, entry_id: entryId, person_id: personId, secondary_person_id: isSubstitution ? secondaryId : null, period, minute: minute === '' ? null : minute },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPersonId('');
                    setSecondaryId('');
                },
                onFinish: () => setSending(false),
            },
        );
    };

    const label = (person: Person) => `${person.shirt_number ? `${person.shirt_number} · ` : ''}${person.name}`;

    return (
        <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-6" aria-label="Novo lançamento">
            <Field label="Tipo">
                <Select value={type} onChange={(event) => { setType(event.target.value); setPersonId(''); }}>
                    {eventTypes.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            </Field>
            <Field label="Equipe">
                <Select value={entryId} onChange={(event) => { setEntryId(event.target.value); setPersonId(''); setSecondaryId(''); }}>
                    <option value={home.id}>{home.name}</option>
                    <option value={away.id}>{away.name}</option>
                </Select>
            </Field>
            <Field label={isSubstitution ? 'Quem sai' : 'Pessoa'}>
                <Select value={personId} onChange={(event) => setPersonId(event.target.value)} required>
                    <option value="">Selecione…</option>
                    {candidates.map((person) => (
                        <option key={person.id} value={person.id}>
                            {label(person)}
                        </option>
                    ))}
                </Select>
            </Field>
            {isSubstitution && (
                <Field label="Quem entra">
                    <Select value={secondaryId} onChange={(event) => setSecondaryId(event.target.value)} required>
                        <option value="">Selecione…</option>
                        {incoming.map((person) => (
                            <option key={person.id} value={person.id}>
                                {label(person)}
                            </option>
                        ))}
                    </Select>
                </Field>
            )}
            <Field label="Tempo">
                <Select value={period} onChange={(event) => setPeriod(event.target.value)}>
                    <option value="1">1º tempo</option>
                    <option value="2">2º tempo</option>
                    <option value="3">Prorrogação 1</option>
                    <option value="4">Prorrogação 2</option>
                </Select>
            </Field>
            <Field label="Minuto">
                <Input type="number" min={0} max={150} value={minute} onChange={(event) => setMinute(event.target.value)} />
            </Field>
            <div className="flex items-end">
                <Button type="submit" disabled={sending || !personId}>
                    Lançar
                </Button>
            </div>
        </form>
    );
}

function TeamCard({ game, team, limits }: { game: Props['game']; team: Team; limits: Props['limits'] }) {
    const [editing, setEditing] = useState(!team.has_lineup);

    return (
        <Card
            title={team.name}
            actions={
                team.has_lineup && game.open ? (
                    <Button small variant="secondary" onClick={() => setEditing(!editing)}>
                        {editing ? 'Fechar' : 'Escalação'}
                    </Button>
                ) : undefined
            }
        >
            {(team.tactic.confirmed || team.tactic.formation) && (
                <div className="mb-3 flex flex-wrap gap-2 text-xs">
                    {team.tactic.confirmed && <Badge tone="green">Escalação confirmada pelo gestor</Badge>}
                    {team.tactic.formation && <Badge tone="blue">Formação {team.tactic.formation}</Badge>}
                </div>
            )}
            {editing ? (
                <LineupEditor game={game} team={team} limits={limits} locked={!game.open || team.has_events} onSaved={() => setEditing(false)} />
            ) : (
                <div className="space-y-3 text-sm">
                    <div>
                        <div className="mb-1 text-xs font-semibold uppercase text-gray-500">Em campo</div>
                        <ul className="flex flex-wrap gap-1">
                            {team.on_field.map((id) => (
                                <li key={id}><Badge tone="green">{personName(team, id)}</Badge></li>
                            ))}
                        </ul>
                    </div>
                    <div>
                        <div className="mb-1 text-xs font-semibold uppercase text-gray-500">Banco</div>
                        <ul className="flex flex-wrap gap-1">
                            {team.available_bench.map((id) => (
                                <li key={id}><Badge tone="blue">{personName(team, id)}</Badge></li>
                            ))}
                            {team.available_bench.length === 0 && <li className="text-gray-400">—</li>}
                        </ul>
                    </div>
                    {team.expelled.length > 0 && (
                        <div>
                            <div className="mb-1 text-xs font-semibold uppercase text-gray-500">Expulsos</div>
                            <ul className="flex flex-wrap gap-1">
                                {team.expelled.map((id) => (
                                    <li key={id}><Badge tone="red">{personName(team, id)}</Badge></li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <p className="text-xs text-gray-500">
                        Substituições {team.substitutions_used}/{limits.substitutions} · atos {team.windows_used}/{limits.windows}
                    </p>
                </div>
            )}
        </Card>
    );
}

export default function Sheet({ game: serverGame, home, away, events: serverEvents, limits, event_types, sport, score_model, quarters, sets, fouls, clock, public_url }: Props) {
    // Quem não opera jogos só consulta a súmula: o servidor também recusa as ações, aqui só se escondem os controles.
    const canOperate = useCan('operate-games');
    const game = canOperate ? serverGame : { ...serverGame, open: false };
    const events = canOperate ? serverEvents : serverEvents.map((event) => ({ ...event, voidable: false }));
    const teamOf = (entryId: string | null) => (entryId === null ? null : home && entryId === home.id ? home : away);

    return (
        <PanelLayout>
            <Head title="Súmula" />
            <PageHeader
                title={`${home?.name ?? 'A definir'} x ${away?.name ?? 'A definir'}`}
                subtitle={`${game.stage} · ${game.division}`}
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: game.competition, href: route('panel.competitions.show', game.competition_id) },
                    { label: game.season, href: route('panel.seasons.show', game.season_id) },
                    { label: game.division, href: route('panel.divisions.show', game.division_id) },
                    { label: game.stage, href: route('panel.stages.show', game.stage_id) },
                    { label: 'Súmula' },
                ]}
                actions={
                    game.open && home && away ? (
                        <Button
                            onClick={() => {
                                if (window.confirm('Encerrar o jogo? O placar atual passa a valer na classificação.')) {
                                    router.post(route('panel.games.finish', game.id), {}, { preserveScroll: true });
                                }
                            }}
                        >
                            Encerrar jogo
                        </Button>
                    ) : undefined
                }
            />

            <div className="mb-6 flex items-center justify-center gap-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <span className="flex flex-1 items-center justify-end gap-3 text-lg font-semibold">
                    {home?.name}
                    {home && <Logo src={home.logo} name={home.name} size={40} />}
                </span>
                <span className="rounded bg-neutral-900 px-4 py-2 text-3xl font-bold tabular-nums text-white" data-testid="scoreboard">
                    {game.home_score} x {game.away_score}
                </span>
                <span className="flex flex-1 items-center gap-3 text-lg font-semibold">
                    {away && <Logo src={away.logo} name={away.name} size={40} />}
                    {away?.name}
                </span>
            </div>
            <div className="-mt-4 mb-6 text-center">
                <Badge tone={game.open ? 'amber' : 'green'}>{game.status_label}</Badge>
            </div>

            {!home || !away ? (
                <Card>
                    <p className="text-sm text-gray-600">O jogo ainda não tem os dois confrontantes definidos.</p>
                </Card>
            ) : (
                <div className="space-y-6">
                    {score_model === 'sets' ? (
                        <PointsCard game={game} sets={sets ?? []} home={home} away={away} lastPoint={[...events].reverse().find((event) => event.type === 'point' && event.voidable)} />
                    ) : (
                        <ClockCard game={game} clock={clock} publicUrl={public_url} sport={sport} />
                    )}
                    {score_model === 'points' && quarters && quarters.length > 0 && (
                        <p className="text-center text-sm tabular-nums text-gray-600" data-testid="panel-quarters">
                            {quarters.map((quarter) => `${periodLabel(quarter.period, sport)}: ${quarter.home}-${quarter.away}`).join(' · ')}
                        </p>
                    )}
                    {score_model === 'points' && home.has_lineup && away.has_lineup && (
                        <div className="grid gap-6 lg:grid-cols-2">
                            <BasketPad game={game} team={home} period={clock.period ?? 1} />
                            <BasketPad game={game} team={away} period={clock.period ?? 1} />
                        </div>
                    )}
                    {fouls && (
                        <Card title={`Faltas acumuladas (${fouls.period}º tempo${fouls.period === 2 ? ' e prorrogação' : ''})`}>
                            <div className="flex items-center justify-around text-center" data-testid="panel-fouls">
                                {[
                                    { name: home.name, value: fouls.home },
                                    { name: away.name, value: fouls.away },
                                ].map((side) => (
                                    <div key={side.name}>
                                        <div className={`text-3xl font-bold tabular-nums ${side.value >= fouls.limit ? 'text-amber-600' : ''}`}>{side.value}</div>
                                        <div className="text-sm text-gray-500">{side.name}</div>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-2 text-xs text-gray-500">A partir da {fouls.limit}ª falta acumulada no tempo, cada nova falta é tiro livre direto sem barreira. Lance as faltas pelo formulário de lançamentos.</p>
                        </Card>
                    )}

                    <div className="grid gap-6 lg:grid-cols-2">
                        <TeamCard game={game} team={home} limits={limits} />
                        <TeamCard game={game} team={away} limits={limits} />
                    </div>

                    {game.open && game.knockout && score_model !== 'sets' && <ShootoutCard game={game} home={home} away={away} />}

                    {game.open && home.has_lineup && away.has_lineup && (
                        <Card title="Novo lançamento">
                            <EventForm game={game} home={home} away={away} eventTypes={event_types} />
                        </Card>
                    )}
                    {game.open && !(home.has_lineup && away.has_lineup) && (
                        <p className="text-sm text-gray-500">Salve a escalação das duas equipes para liberar os lançamentos.</p>
                    )}

                    <Card title={`Lançamentos (${events.length})`}>
                        {events.length === 0 ? (
                            <p className="text-sm text-gray-500">Nenhum lançamento ainda.</p>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {events.map((event) => {
                                    const team = teamOf(event.entry_id);
                                    return (
                                        <li key={event.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                                            <div>
                                                <span className="mr-2 inline-block w-14 text-gray-400">
                                                    {event.minute !== null ? `${event.minute}'` : '—'} · {event.period}T
                                                </span>
                                                <span className="mr-2">{typeIcon[event.type] ?? event.type}</span>
                                                <strong>{event.type === 'point' ? 'Ponto' : event.type === 'clock_pause' ? 'Relógio parado' : event.type === 'clock_resume' ? 'Relógio em andamento' : event.type === 'period_start' || event.type === 'period_end' ? `${event.type === 'period_start' ? 'Início do' : 'Fim do'} ${periodLabel(event.period, sport)}` : (event.person ?? 'Anônimo')}</strong>
                                                {event.secondary_person && <span> ⇄ {event.secondary_person}</span>}
                                                {team && <span className="ml-2 text-gray-500">({team.name})</span>}
                                                {event.quick && <span className="ml-2"><Badge>Resultado rápido</Badge></span>}
                                            </div>
                                            {event.voidable && (
                                                <Button
                                                    small
                                                    variant="danger"
                                                    onClick={() => {
                                                        if (window.confirm('Anular este lançamento?')) {
                                                            router.post(route('panel.games.events.void', [game.id, event.id]), {}, { preserveScroll: true });
                                                        }
                                                    }}
                                                >
                                                    Anular
                                                </Button>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Card>
                </div>
            )}
        </PanelLayout>
    );
}
