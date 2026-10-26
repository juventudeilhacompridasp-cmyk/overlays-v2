import Modal from '@/Components/Modal';
import { Badge, Button, Card, Empty, Field, Input, LinkButton, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { NO_DAY, conflictText, formatDay, groupByDay, isDay, moveToDay, timeOf, withTime } from '@/lib/schedule';
import { DndContext, DragEndEvent, DragOverlay, DragStartEvent, KeyboardSensor, MouseSensor, TouchSensor, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

interface GameItem {
    id: string;
    number: number | null;
    round: number | null;
    group: string | null;
    home: string | null;
    away: string | null;
    status: string;
    status_label: string;
    movable: boolean;
    scheduled_at: string | null;
    venue_id: string | null;
    venue: string | null;
    conflicts: { kind: 'venue' | 'team'; with: string; label: string }[];
}

interface Props {
    competition: { id: string; name: string };
    season: { id: string; name: string };
    division: { id: string; name: string };
    stage: { id: string; name: string };
    games: GameItem[];
    venues: { id: string; name: string }[];
    rules: { venue_minutes: number; team_rest_minutes: number };
}

const title = (game: GameItem) => `${game.home ?? 'A definir'} x ${game.away ?? 'A definir'}`;

function GameChip({ game, onEdit }: { game: GameItem; onEdit: (game: GameItem) => void }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: game.id, disabled: !game.movable });
    const conflict = game.conflicts.length > 0;

    return (
        <div
            ref={setNodeRef}
            className={`rounded-lg bg-white p-2.5 text-sm shadow-sm ring-1 ${conflict ? 'ring-red-300' : 'ring-gray-200'} ${isDragging ? 'opacity-40' : ''}`}
            data-testid="schedule-game"
        >
            <div className="flex items-start justify-between gap-2">
                <button type="button" {...(game.movable ? { ...listeners, ...attributes } : {})} disabled={!game.movable} aria-label={`Arrastar ${title(game)}`} className={`min-w-0 flex-1 text-left ${game.movable ? 'cursor-grab active:cursor-grabbing' : 'cursor-default'}`}>
                    <span className="block truncate font-medium text-gray-900">{title(game)}</span>
                    <span className="block text-xs text-gray-500">
                        {timeOf(game.scheduled_at) || 'Sem horário'}
                        {game.venue ? ` · ${game.venue}` : ' · sem estádio'}
                        {game.round ? ` · rodada ${game.round}` : ''}
                        {game.group ? ` · ${game.group}` : ''}
                    </span>
                </button>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    {!game.movable && <Badge tone="gray">{game.status_label}</Badge>}
                    {game.movable && (
                        <Button small variant="secondary" onClick={() => onEdit(game)} aria-label={`Editar horário e estádio de ${title(game)}`}>
                            Editar
                        </Button>
                    )}
                </div>
            </div>
            {conflict && (
                <ul className="mt-1.5 space-y-0.5 text-xs text-red-700" data-testid="schedule-conflicts">
                    {game.conflicts.map((item) => (
                        <li key={`${item.kind}-${item.with}`}>⚠ {conflictText(item)}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function DayCard({ day, games, onEdit }: { day: string; games: GameItem[]; onEdit: (game: GameItem) => void }) {
    const { setNodeRef, isOver } = useDroppable({ id: `day-${day}` });

    return (
        <section ref={setNodeRef} aria-label={formatDay(day)} className={`rounded-xl border-2 border-dashed p-3 transition ${isOver ? 'border-emerald-500 bg-emerald-50' : 'border-gray-200 bg-gray-50'}`}>
            <h2 className="mb-2 flex items-baseline justify-between gap-2 text-sm font-semibold text-gray-800">
                {formatDay(day)}
                <span className="text-xs font-normal text-gray-500">{games.length} jogo(s)</span>
            </h2>
            <div className="space-y-2">
                {games.map((game) => (
                    <GameChip key={game.id} game={game} onEdit={onEdit} />
                ))}
                {games.length === 0 && <p className="py-3 text-center text-xs text-gray-400">{day === NO_DAY ? 'Todos os jogos têm data.' : 'Arraste jogos para este dia'}</p>}
            </div>
        </section>
    );
}

function EditModal({ game, venues, onClose, onSave }: { game: GameItem; venues: Props['venues']; onClose: () => void; onSave: (game: GameItem, change: { scheduled_at: string | null; venue_id: string | null }) => void }) {
    const [day, setDay] = useState(game.scheduled_at ? game.scheduled_at.slice(0, 10) : '');
    const [time, setTime] = useState(timeOf(game.scheduled_at));
    const [venue, setVenue] = useState(game.venue_id ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const scheduled = day === '' ? null : withTime(`${day}T00:00`, time || '16:00');
        onSave(game, { scheduled_at: scheduled, venue_id: venue === '' ? null : venue });
    };

    return (
        <Modal show onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-semibold text-gray-900">{title(game)}</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Dia" htmlFor="day">
                        <Input id="day" type="date" value={day} onChange={(event) => setDay(event.target.value)} />
                    </Field>
                    <Field label="Horário" htmlFor="time">
                        <Input id="time" type="time" value={time} onChange={(event) => setTime(event.target.value)} disabled={day === ''} />
                    </Field>
                </div>
                <Field label="Estádio" htmlFor="venue">
                    <Select id="venue" value={venue} onChange={(event) => setVenue(event.target.value)}>
                        <option value="">Sem estádio definido</option>
                        {venues.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit">Salvar</Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Schedule({ competition, season, division, stage, games, venues, rules }: Props) {
    const [extraDays, setExtraDays] = useState<string[]>([]);
    const [newDay, setNewDay] = useState('');
    const [active, setActive] = useState<string | null>(null);
    const [editing, setEditing] = useState<GameItem | null>(null);

    const sensors = useSensors(useSensor(MouseSensor, { activationConstraint: { distance: 6 } }), useSensor(TouchSensor, { activationConstraint: { delay: 180, tolerance: 8 } }), useSensor(KeyboardSensor));
    const days = useMemo(() => groupByDay(games, extraDays), [games, extraDays]);
    const byId = useMemo(() => new Map(games.map((game) => [game.id, game])), [games]);
    const conflicting = games.filter((game) => game.conflicts.length > 0).length;

    const save = (game: GameItem, change: { scheduled_at: string | null; venue_id: string | null }, done?: () => void) => {
        router.patch(route('panel.games.update', game.id), { ...change, status: game.status }, { preserveScroll: true, onSuccess: done });
    };

    const onDragEnd = (event: DragEndEvent) => {
        setActive(null);
        const game = byId.get(String(event.active.id));
        const target = event.over ? String(event.over.id).replace('day-', '') : null;

        if (!game || target === null || (target === NO_DAY ? game.scheduled_at === null : game.scheduled_at?.startsWith(target))) return;

        save(game, { scheduled_at: moveToDay(game.scheduled_at, target), venue_id: game.venue_id });
    };

    const addDay = () => {
        if (!isDay(newDay)) return;
        setExtraDays((current) => [...new Set([...current, newDay])]);
        setNewDay('');
    };

    return (
        <PanelLayout>
            <Head title="Agenda dos jogos" />
            <PageHeader
                title={`Agenda · ${stage.name}`}
                subtitle={`${division.name} · ${season.name}`}
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: competition.name, href: route('panel.competitions.show', competition.id) },
                    { label: season.name, href: route('panel.seasons.show', season.id) },
                    { label: division.name, href: route('panel.divisions.show', division.id) },
                    { label: stage.name, href: route('panel.stages.show', stage.id) },
                    { label: 'Agenda' },
                ]}
                actions={
                    <LinkButton href={route('panel.stages.show', stage.id)} variant="secondary">
                        Voltar à fase
                    </LinkButton>
                }
            />

            {games.length === 0 ? (
                <Card>
                    <Empty title="Esta fase ainda não tem jogos.">Gere a tabela, sorteie ou importe os confrontos na fase para montar a agenda.</Empty>
                </Card>
            ) : (
                <DndContext sensors={sensors} onDragStart={(event: DragStartEvent) => setActive(String(event.active.id))} onDragCancel={() => setActive(null)} onDragEnd={onDragEnd}>
                    <p className="mb-3 text-sm text-gray-600">
                        Arraste um jogo para outro dia (o horário se mantém; no celular, segure e arraste) ou use <strong>Editar</strong> para mudar horário e estádio. Cada mudança é salva na hora. Aviso de conflito: mesmo estádio com menos de {rules.venue_minutes / 60} h entre jogos ou a mesma equipe com menos de {rules.team_rest_minutes / 60} h de descanso, contando jogos de todas as fases e divisões.
                    </p>
                    <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-200">
                        <Field label="Adicionar um dia à agenda" htmlFor="new-day">
                            <Input id="new-day" type="date" value={newDay} onChange={(event) => setNewDay(event.target.value)} />
                        </Field>
                        <Button variant="secondary" onClick={addDay} disabled={!isDay(newDay)}>
                            Adicionar dia
                        </Button>
                        <p className={`ml-auto text-sm font-medium ${conflicting > 0 ? 'text-red-700' : 'text-emerald-700'}`} data-testid="schedule-summary">
                            {conflicting > 0 ? `${conflicting} jogo(s) com conflito` : 'Nenhum conflito de agenda'}
                        </p>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {days.map((group) => (
                            <DayCard key={group.day} day={group.day} games={group.games} onEdit={setEditing} />
                        ))}
                    </div>
                    <DragOverlay>
                        {active && byId.get(active) ? <div className="rounded-lg bg-white px-3 py-2 text-sm font-medium shadow-lg ring-2 ring-emerald-500">{title(byId.get(active)!)}</div> : null}
                    </DragOverlay>
                </DndContext>
            )}

            {editing && <EditModal key={editing.id} game={editing} venues={venues} onClose={() => setEditing(null)} onSave={(game, change) => save(game, change, () => setEditing(null))} />}
        </PanelLayout>
    );
}
