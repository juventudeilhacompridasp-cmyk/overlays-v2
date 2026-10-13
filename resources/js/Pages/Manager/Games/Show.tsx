import { Badge, Button, Card, Field, LinkButton, Logo, PageHeader, Select, Textarea } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { GameRow } from './GameRow';
import Pitch, { Formation } from './Pitch';

interface Person {
    id: string;
    name: string;
    shirt_number: number | null;
    is_athlete: boolean;
    role_label: string;
    suspended_games: number;
}

interface LineupData {
    formation?: string | null;
    starters: string[];
    bench: string[];
    staff: string[];
    positions: Record<string, string>;
    captain: string | null;
}

interface OpponentLine {
    shirt: number | null;
    name: string;
    position: string | null;
    captain: boolean;
}

interface Props {
    game: GameRow & { lineup: 'confirmed' | 'draft' | 'missing' };
    sport: string;
    formations: Formation[];
    limits: { starters: number; bench: number; staff: number };
    roster: Person[];
    lineup: LineupData;
    tactic: { formation: string | null; notes: string | null; confirmed_at: string | null };
    window: { closed_reason: string | null; deadline: string | null };
    previous: (LineupData & { formation: string | null }) | null;
    opponent: { team: string; formation: string | null; starters: OpponentLine[]; bench: OpponentLine[] } | null;
}

const statusTone = { confirmed: 'green', draft: 'amber', missing: 'gray' } as const;
const statusLabel = { confirmed: 'Escalação confirmada', draft: 'Rascunho salvo', missing: 'Sem escalação' } as const;

export default function Show({ game, sport, formations, limits, roster, lineup, tactic, window, previous, opponent }: Props) {
    const locked = window.closed_reason !== null;
    const people = useMemo(() => new Map(roster.map((person) => [person.id, person])), [roster]);
    const athletes = roster.filter((person) => person.is_athlete);
    const staffList = roster.filter((person) => !person.is_athlete);

    const [formationCode, setFormationCode] = useState(tactic.formation ?? formations[0].code);
    const [positions, setPositions] = useState<Record<string, string>>(() => {
        // vaga => pessoa (o servidor guarda pessoa => vaga)
        const bySlot: Record<string, string> = {};
        Object.entries(lineup.positions).forEach(([personId, slot]) => (bySlot[slot] = personId));
        return bySlot;
    });
    const [extraStarters, setExtraStarters] = useState<string[]>(() => lineup.starters.filter((id) => !lineup.positions[id]));
    const [bench, setBench] = useState<string[]>(lineup.bench.filter((id) => !lineup.positions[id]));
    const [staff, setStaff] = useState<string[]>(lineup.staff);
    const [captain, setCaptain] = useState<string>(lineup.captain ?? '');
    const [notes, setNotes] = useState(tactic.notes ?? '');
    const [active, setActive] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    const formation = formations.find((item) => item.code === formationCode) ?? formations[0];
    const fieldSlots = formation.slots.filter((slot) => !slot.bench);
    const benchSlots = formation.slots.filter((slot) => slot.bench);

    const slotOf = (personId: string) => Object.entries(positions).find(([, id]) => id === personId)?.[0];
    const starters = [...fieldSlots.map((slot) => positions[slot.key]).filter(Boolean), ...extraStarters];
    const liberoIds = benchSlots.map((slot) => positions[slot.key]).filter(Boolean);
    const benchAll = [...bench, ...liberoIds];
    const used = new Set([...starters, ...benchAll]);

    const eligible = (person: Person) => person.suspended_games === 0;

    const assign = (slotKey: string, personId: string) => {
        setPositions((current) => {
            const next = { ...current };
            Object.keys(next).forEach((key) => next[key] === personId && delete next[key]);
            if (personId) next[slotKey] = personId;
            else delete next[slotKey];
            return next;
        });
        if (personId) {
            setBench((current) => current.filter((id) => id !== personId));
            setExtraStarters((current) => current.filter((id) => id !== personId));
        }
    };

    const changeFormation = (code: string) => {
        // Trocar a formação mantém os titulares, redistribuídos nas vagas na mesma ordem (campo primeiro).
        const next = formations.find((item) => item.code === code) ?? formation;
        const people = fieldSlots.map((slot) => positions[slot.key]).filter(Boolean);
        const slots = next.slots.filter((slot) => !slot.bench);
        const rebuilt: Record<string, string> = {};
        people.slice(0, slots.length).forEach((id, index) => (rebuilt[slots[index].key] = id));
        const overflow = people.slice(slots.length);
        next.slots.filter((slot) => slot.bench).forEach((slot) => positions[slot.key] && (rebuilt[slot.key] = positions[slot.key]));
        setPositions(rebuilt);
        setExtraStarters((current) => [...current, ...overflow]);
        setFormationCode(code);
        setActive(null);
    };

    const toggleBench = (personId: string) => {
        if (slotOf(personId)) return;
        setExtraStarters((current) => current.filter((id) => id !== personId));
        setBench((current) => (current.includes(personId) ? current.filter((id) => id !== personId) : [...current, personId]));
    };

    const toggleStaff = (personId: string) => setStaff((current) => (current.includes(personId) ? current.filter((id) => id !== personId) : [...current, personId]));

    const reuse = () => {
        if (!previous) return;
        const known = new Set(roster.filter(eligible).map((person) => person.id));
        const code = formations.some((item) => item.code === previous.formation) ? (previous.formation as string) : formationCode;
        const target = formations.find((item) => item.code === code) ?? formation;
        const rebuilt: Record<string, string> = {};
        Object.entries(previous.positions).forEach(([personId, slot]) => {
            if (known.has(personId) && target.slots.some((item) => item.key === slot)) rebuilt[slot] = personId;
        });
        setFormationCode(code);
        setPositions(rebuilt);
        setExtraStarters(previous.starters.filter((id) => known.has(id) && !previous.positions[id]));
        setBench(previous.bench.filter((id) => known.has(id) && !previous.positions[id]));
        setStaff(previous.staff.filter((id) => people.has(id)));
        setCaptain(previous.captain && known.has(previous.captain) ? previous.captain : '');
    };

    const counts = { starters: starters.length, bench: benchAll.length, staff: staff.length };
    const lineupPeople = [...starters, ...benchAll];

    const submit = (confirm: boolean) => {
        const positionsOut: Record<string, string> = {};
        Object.entries(positions).forEach(([slot, personId]) => (positionsOut[personId] = slot));

        setSaving(true);
        router.put(
            route('manager.games.lineup', game.id),
            { formation: formationCode, starters, bench: benchAll, staff, positions: positionsOut, captain: captain || null, notes, confirm },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const label = (id: string) => {
        const person = people.get(id);
        return person ? `${person.shirt_number ?? '–'} · ${person.name}` : id;
    };

    return (
        <PanelLayout>
            <Head title={`${game.my_team} x ${game.opponent ?? 'A definir'}`} />
            <PageHeader
                title={`${game.my_team} x ${game.opponent ?? 'A definir'}`}
                subtitle={`${game.competition} · ${game.stage}`}
                breadcrumbs={[{ label: 'Jogos', href: route('manager.games.index') }, { label: 'Escalação' }]}
                actions={<LinkButton variant="secondary" href={route('manager.games.index')}>Voltar</LinkButton>}
            />

            <Card className="mb-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Logo src={game.my_logo} name={game.my_team} size={48} />
                        <div className="text-2xl font-bold tabular-nums text-gray-900">{game.decided ? `${game.my_score} x ${game.opponent_score}` : 'x'}</div>
                        <Logo src={game.opponent_logo} name={game.opponent ?? '?'} size={48} />
                    </div>
                    <div className="text-sm text-gray-600">
                        <Badge tone={game.decided ? 'gray' : 'blue'}>{game.status_label}</Badge>
                        <div className="mt-2">{game.scheduled_at ?? 'Horário a definir'}</div>
                        {game.venue && <div>{game.venue}</div>}
                        <div>{game.home ? 'Você é o mandante' : 'Você é o visitante'}</div>
                    </div>
                </div>
            </Card>

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Badge tone={statusTone[game.lineup]}>{statusLabel[game.lineup]}</Badge>
                {tactic.confirmed_at && <span className="text-sm text-gray-500">Confirmada em {tactic.confirmed_at}</span>}
                {!locked && window.deadline && <span className="text-sm text-gray-500">Você pode alterar até {window.deadline}.</span>}
            </div>
            {locked && (
                <div role="status" className="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                    {window.closed_reason}
                </div>
            )}

            {roster.length === 0 ? (
                <Card>
                    <p className="text-sm text-gray-500">O elenco desta equipe está vazio. Peça ao organizador para concluir o cadastro dos atletas.</p>
                </Card>
            ) : (
                <div className="space-y-6">
                    <Card title="Esquema tático">
                        <div className="grid gap-6 lg:grid-cols-[minmax(0,22rem)_1fr]">
                            <div>
                                <Field label="Formação" htmlFor="formation">
                                    <Select id="formation" value={formationCode} disabled={locked} onChange={(event) => changeFormation(event.target.value)}>
                                        {formations.map((item) => (
                                            <option key={item.code} value={item.code}>
                                                {item.label}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Pitch
                                    sport={sport}
                                    formation={formation}
                                    occupants={positions}
                                    label={(id) => people.get(id)?.name ?? ''}
                                    shirt={(id) => people.get(id)?.shirt_number ?? null}
                                    captain={captain}
                                    active={active}
                                    onSelect={setActive}
                                />
                            </div>

                            <div>
                                <div className="mb-3 flex flex-wrap gap-2 text-xs">
                                    <Badge tone={counts.starters > limits.starters ? 'red' : counts.starters === fieldSlots.length ? 'green' : 'amber'}>
                                        Titulares {counts.starters}/{fieldSlots.length}
                                    </Badge>
                                    <Badge tone={counts.bench > limits.bench ? 'red' : 'blue'}>
                                        Reservas {counts.bench}/{limits.bench}
                                    </Badge>
                                    <Badge tone={counts.staff > limits.staff ? 'red' : 'gray'}>
                                        Comissão {counts.staff}/{limits.staff}
                                    </Badge>
                                </div>

                                <ul className="divide-y divide-gray-100 rounded-md border border-gray-200">
                                    {formation.slots.map((slot) => (
                                        <li key={slot.key} className={`flex items-center justify-between gap-3 px-3 py-2 text-sm ${active === slot.key ? 'bg-emerald-50' : ''}`}>
                                            <label htmlFor={`slot-${slot.key}`} className="min-w-0 text-gray-700">
                                                <span className="mr-2 inline-block w-9 text-xs font-semibold uppercase text-gray-400">{slot.abbr}</span>
                                                {slot.label}
                                                {slot.bench && <span className="ml-1 text-xs text-gray-400">(reserva)</span>}
                                            </label>
                                            <Select
                                                id={`slot-${slot.key}`}
                                                className="!w-56"
                                                value={positions[slot.key] ?? ''}
                                                disabled={locked}
                                                onFocus={() => setActive(slot.key)}
                                                onChange={(event) => assign(slot.key, event.target.value)}
                                            >
                                                <option value="">—</option>
                                                {athletes
                                                    .filter((person) => eligible(person) && (person.id === positions[slot.key] || !used.has(person.id) || (!slot.bench && bench.includes(person.id))))
                                                    .map((person) => (
                                                        <option key={person.id} value={person.id}>
                                                            {person.shirt_number ?? '–'} · {person.name}
                                                        </option>
                                                    ))}
                                            </Select>
                                        </li>
                                    ))}
                                </ul>
                                {extraStarters.length > 0 && (
                                    <p className="mt-2 text-xs text-amber-700">
                                        {extraStarters.length} titular(es) sem posição nesta formação: {extraStarters.map((id) => people.get(id)?.name).join(', ')}. Escolha uma posição para eles.
                                    </p>
                                )}
                            </div>
                        </div>
                    </Card>

                    <Card title="Banco de reservas e comissão">
                        <div className="grid gap-6 md:grid-cols-2">
                            <div>
                                <h3 className="mb-2 text-sm font-semibold text-gray-900">Reservas</h3>
                                <ul className="divide-y divide-gray-100 rounded-md border border-gray-200">
                                    {athletes.map((person) => {
                                        const starter = starters.includes(person.id) || liberoIds.includes(person.id);
                                        return (
                                            <li key={person.id} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                                <span className={eligible(person) ? 'text-gray-900' : 'text-gray-400 line-through'}>
                                                    {person.shirt_number ?? '–'} · {person.name}
                                                    {!eligible(person) && (
                                                        <span className="ml-2 no-underline">
                                                            <Badge tone="red">Suspenso ({person.suspended_games})</Badge>
                                                        </span>
                                                    )}
                                                </span>
                                                {starter ? (
                                                    <Badge tone="green">{liberoIds.includes(person.id) ? 'Líbero' : 'Titular'}</Badge>
                                                ) : (
                                                    <label className="flex items-center gap-2 text-gray-600">
                                                        <input
                                                            type="checkbox"
                                                            className="rounded border-gray-300 text-emerald-600"
                                                            checked={bench.includes(person.id)}
                                                            disabled={locked || !eligible(person)}
                                                            onChange={() => toggleBench(person.id)}
                                                        />
                                                        Reserva
                                                    </label>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                            <div>
                                <h3 className="mb-2 text-sm font-semibold text-gray-900">Comissão técnica</h3>
                                {staffList.length === 0 ? (
                                    <p className="text-sm text-gray-500">Nenhum membro da comissão cadastrado.</p>
                                ) : (
                                    <ul className="divide-y divide-gray-100 rounded-md border border-gray-200">
                                        {staffList.map((person) => (
                                            <li key={person.id} className="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                                <span className="text-gray-900">
                                                    {person.name} <span className="text-xs text-gray-500">{person.role_label}</span>
                                                </span>
                                                <label className="flex items-center gap-2 text-gray-600">
                                                    <input
                                                        type="checkbox"
                                                        className="rounded border-gray-300 text-emerald-600"
                                                        checked={staff.includes(person.id)}
                                                        disabled={locked}
                                                        onChange={() => toggleStaff(person.id)}
                                                    />
                                                    No jogo
                                                </label>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </div>
                    </Card>

                    <Card title="Capitão e instruções">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field label="Capitão" htmlFor="captain" hint="Escolha entre os titulares e reservas.">
                                <Select id="captain" value={captain} disabled={locked} onChange={(event) => setCaptain(event.target.value)}>
                                    <option value="">—</option>
                                    {lineupPeople.map((id) => (
                                        <option key={id} value={id}>
                                            {label(id)}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label="Instruções da equipe (privadas)" htmlFor="notes" hint="Só a sua equipe vê. Não aparece para o adversário, na transmissão nem no portal.">
                                <Textarea id="notes" rows={4} maxLength={1000} value={notes} disabled={locked} onChange={(event) => setNotes(event.target.value)} />
                            </Field>
                        </div>
                    </Card>

                    {!locked && (
                        <div className="flex flex-wrap items-center gap-2">
                            <Button disabled={saving} onClick={() => submit(true)}>
                                Confirmar escalação
                            </Button>
                            <Button variant="secondary" disabled={saving} onClick={() => submit(false)}>
                                Salvar rascunho
                            </Button>
                            {previous && (
                                <Button variant="ghost" disabled={saving} onClick={reuse}>
                                    Repetir a última escalação
                                </Button>
                            )}
                            <span className="text-xs text-gray-500">Para confirmar: todas as posições preenchidas e um capitão.</span>
                        </div>
                    )}
                </div>
            )}

            {opponent && (
                <Card title={`Escalação de ${opponent.team}`} className="mt-6">
                    {opponent.formation && <p className="mb-2 text-sm text-gray-600">Formação: {opponent.formation}</p>}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <OpponentList title="Titulares" lines={opponent.starters} />
                        <OpponentList title="Reservas" lines={opponent.bench} />
                    </div>
                </Card>
            )}
        </PanelLayout>
    );
}

function OpponentList({ title, lines }: { title: string; lines: OpponentLine[] }) {
    return (
        <div>
            <h3 className="mb-1 text-sm font-semibold text-gray-900">{title}</h3>
            <ul className="text-sm text-gray-700">
                {lines.map((line, index) => (
                    <li key={index}>
                        {line.shirt ?? '–'} · {line.name}
                        {line.position && <span className="ml-1 text-xs text-gray-500">{line.position}</span>}
                        {line.captain && <span className="ml-1 text-xs font-semibold text-emerald-700">(C)</span>}
                    </li>
                ))}
            </ul>
        </div>
    );
}
