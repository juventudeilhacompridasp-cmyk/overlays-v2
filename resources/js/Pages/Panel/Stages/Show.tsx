import Modal from '@/Components/Modal';
import { Badge, Button, Card, Empty, Field, Input, PageHeader, Select } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { Option } from '@/types/panel';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface Team {
    id: string;
    name: string;
}

interface Game {
    id: string;
    number: number | null;
    round: number | null;
    group: string | null;
    home: Team | null;
    away: Team | null;
    home_score: number;
    away_score: number;
    status: string;
    status_label: string;
    decided: boolean;
    round_label: string | null;
    leg: number | null;
    legs: number;
    home_penalties: number | null;
    away_penalties: number | null;
    scheduled_at: string | null;
    venue: { id: string; name: string } | null;
}

interface Row {
    position: number;
    team: string;
    points: number;
    played: number;
    wins: number;
    draws: number;
    losses: number;
    goals_for: number;
    goals_against: number;
    goal_difference: number;
    yellow_cards: number;
    red_cards: number;
    tied: boolean;
}

interface Props {
    competition: { id: string; name: string };
    season: { id: string; name: string };
    division: { id: string; name: string };
    stage: { id: string; name: string; type: string; type_label: string };
    tables: { id: string | null; name: string; rows: Row[] }[];
    games: Game[];
    venues: { id: string; name: string }[];
    generation: { can_generate: boolean; confirmed_entries: number; can_clear: boolean };
    statuses: Option[];
}

/** "2026-05-10T16:30" → "10/05/2026 16:30" (sem fuso: é o horário marcado no local). */
const formatDateTime = (value: string | null) => {
    if (!value) return 'Sem data';
    const [date, time] = value.split('T');
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year} ${time ?? ''}`.trim();
};

const statusTone = (status: string) =>
    status === 'finished' ? 'green' : status === 'walkover' ? 'blue' : status === 'scheduled' ? 'gray' : status === 'postponed' ? 'amber' : 'red';

function GenerateCard({ stage, confirmedEntries }: { stage: Props['stage']; confirmedEntries: number }) {
    const { data, setData, post, processing, errors } = useForm({ double_round: false, groups: 2, legs: 1 });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('panel.stages.fixtures.store', stage.id));
    };

    return (
        <Card title="Gerar tabela de jogos">
            <form onSubmit={submit} className="space-y-4">
                <p className="text-sm text-gray-600">
                    {confirmedEntries} {confirmedEntries === 1 ? 'equipe confirmada' : 'equipes confirmadas'} nesta divisão. A tabela sai sem data e sem estádio; você agenda
                    depois, jogo a jogo.
                    {stage.type === 'groups' && ' As equipes são distribuídas nos grupos em serpentina, pela ordem de cabeça de chave.'}
                    {stage.type === 'knockout' && ' O chaveamento cruza as cabeças de chave (1ª x última...); com número ímpar de equipes, as melhores folgam na 1ª rodada.'}
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                    {stage.type === 'groups' && (
                        <Field label="Número de grupos" htmlFor="groups" error={errors.groups} hint="Cada grupo precisa de pelo menos duas equipes.">
                            <Input id="groups" type="number" min={1} max={16} value={data.groups} onChange={(e) => setData('groups', Number(e.target.value || 1))} />
                        </Field>
                    )}
                    {stage.type === 'knockout' ? (
                        <Field label="Jogos por confronto" htmlFor="legs" error={errors.legs} hint="Na ida e volta o vencedor sai pelo agregado de gols; empate no agregado vai aos pênaltis da volta.">
                            <Select id="legs" value={String(data.legs)} onChange={(e) => setData('legs', Number(e.target.value))}>
                                <option value="1">Jogo único</option>
                                <option value="2">Ida e volta</option>
                            </Select>
                        </Field>
                    ) : (
                    <Field label="Turnos" htmlFor="double_round">
                        <Select id="double_round" value={data.double_round ? '1' : '0'} onChange={(e) => setData('double_round', e.target.value === '1')}>
                            <option value="0">Turno único (todos se enfrentam uma vez)</option>
                            <option value="1">Ida e volta (turno e returno)</option>
                        </Select>
                    </Field>
                    )}
                </div>
                <Button type="submit" disabled={processing || confirmedEntries < 2}>
                    {stage.type === 'knockout' ? 'Gerar chaveamento' : 'Gerar tabela'}
                </Button>
                {confirmedEntries < 2 && <p className="text-sm text-amber-700">Confirme pelo menos duas equipes na divisão para gerar a tabela.</p>}
            </form>
        </Card>
    );
}

function ScheduleModal({ game, venues, statuses, onClose }: { game: Game; venues: Props['venues']; statuses: Option[]; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({
        scheduled_at: game.scheduled_at ?? '',
        venue_id: game.venue?.id ?? '',
        status: game.status,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('panel.games.update', game.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Modal show onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-semibold text-gray-900">
                    Agendar: {game.home?.name} x {game.away?.name}
                </h2>
                <Field label="Data e hora" htmlFor="scheduled_at" error={errors.scheduled_at}>
                    <Input id="scheduled_at" type="datetime-local" value={data.scheduled_at} onChange={(e) => setData('scheduled_at', e.target.value)} />
                </Field>
                <Field label="Estádio" htmlFor="venue_id" error={errors.venue_id}>
                    <Select id="venue_id" value={data.venue_id} onChange={(e) => setData('venue_id', e.target.value)}>
                        <option value="">Sem estádio definido</option>
                        {venues.map((venue) => (
                            <option key={venue.id} value={venue.id}>
                                {venue.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Situação" htmlFor="status" error={errors.status}>
                    <Select id="status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                        {statuses.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Salvar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function ResultModal({ game, onClose }: { game: Game; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({ home_score: '0', away_score: '0', home_penalties: '', away_penalties: '' });
    const level = data.home_score === data.away_score;
    const serverError = (usePage().props.flash as { error?: string } | undefined)?.error;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('panel.games.result', game.id), {
            preserveScroll: true,
            // O servidor responde com redirect + mensagem de erro (regra violada): nesse caso o modal fica aberto.
            onSuccess: (page) => {
                if (!(page.props.flash as { error?: string } | undefined)?.error) onClose();
            },
        });
    };

    const walkover = (winner: 'home' | 'away') =>
        router.post(route('panel.games.walkover', game.id), { winner }, { preserveScroll: true, onSuccess: onClose });

    return (
        <Modal show onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-semibold text-gray-900">Lançar resultado</h2>
                {serverError && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{serverError}</p>}
                <div className="grid grid-cols-[1fr_auto_1fr] items-end gap-3">
                    <Field label={game.home?.name ?? 'Mandante'} htmlFor="home_score" error={errors.home_score}>
                        <Input id="home_score" type="number" min={0} max={99} value={data.home_score} onChange={(e) => setData('home_score', e.target.value)} autoFocus />
                    </Field>
                    <span className="pb-2 text-lg font-semibold text-gray-400">x</span>
                    <Field label={game.away?.name ?? 'Visitante'} htmlFor="away_score" error={errors.away_score}>
                        <Input id="away_score" type="number" min={0} max={99} value={data.away_score} onChange={(e) => setData('away_score', e.target.value)} />
                    </Field>
                </div>
                {game.round_label && level && (
                    <div className="grid grid-cols-[1fr_auto_1fr] items-end gap-3 rounded-md bg-amber-50 p-3">
                        <Field label="Pênaltis convertidos" htmlFor="home_penalties" error={errors.home_penalties}>
                            <Input id="home_penalties" type="number" min={0} max={30} value={data.home_penalties} onChange={(e) => setData('home_penalties', e.target.value)} />
                        </Field>
                        <span className="pb-2 text-lg font-semibold text-gray-400">x</span>
                        <Field label=" " htmlFor="away_penalties" error={errors.away_penalties}>
                            <Input id="away_penalties" type="number" min={0} max={30} value={data.away_penalties} onChange={(e) => setData('away_penalties', e.target.value)} />
                        </Field>
                        <p className="col-span-3 text-xs text-amber-800">Mata-mata empatado: informe a disputa de pênaltis se este for o jogo que decide o confronto.</p>
                    </div>
                )}
                <p className="text-xs text-gray-500">Só o placar final. Gols e cartões por atleta serão lançados na súmula, em uma próxima etapa.</p>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Lançar resultado
                    </Button>
                </div>
                <div className="border-t border-gray-100 pt-4">
                    <p className="mb-2 text-sm font-medium text-gray-700">Ou registrar W.O.</p>
                    <div className="flex flex-wrap gap-2">
                        <Button small variant="secondary" onClick={() => walkover('home')}>
                            W.O. para {game.home?.name}
                        </Button>
                        <Button small variant="secondary" onClick={() => walkover('away')}>
                            W.O. para {game.away?.name}
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

function GameRow({ game, onSchedule, onResult }: { game: Game; onSchedule: () => void; onResult: () => void }) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 py-3">
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2 text-sm">
                    {game.number && <span className="text-gray-400">#{game.number}</span>}
                    {game.group && <Badge tone="blue">{game.group}</Badge>}
                    {game.legs > 1 && game.leg && <Badge tone="blue">{game.leg === 1 ? 'Ida' : 'Volta'}</Badge>}
                    <Badge tone={statusTone(game.status)}>{game.status_label}</Badge>
                </div>
                <div className="mt-1 flex items-center gap-3 font-medium text-gray-900">
                    <span className="flex-1 text-right">{game.home?.name ?? 'A definir'}</span>
                    <span className="min-w-14 rounded bg-gray-100 px-2 py-0.5 text-center tabular-nums">
                        {game.decided ? `${game.home_score} x ${game.away_score}` : 'x'}
                    </span>
                    {game.decided && game.home_penalties !== null && game.away_penalties !== null && (
                        <span className="text-xs font-normal text-gray-500">
                            ({game.home_penalties} x {game.away_penalties} nos pênaltis)
                        </span>
                    )}
                    <span className="flex-1">{game.away?.name ?? 'A definir'}</span>
                </div>
                <div className="mt-1 text-center text-xs text-gray-500">
                    {formatDateTime(game.scheduled_at)}
                    {game.venue && ` · ${game.venue.name}`}
                </div>
            </div>
            <div className="flex gap-2">
                {game.decided ? (
                    <>
                    {game.home && game.away && (
                        <Link href={route('panel.games.sheet', game.id)} className="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200">
                            Súmula
                        </Link>
                    )}
                    <Button
                        small
                        variant="secondary"
                        onClick={() => {
                            if (window.confirm('Reabrir este jogo? Ele sai da classificação até o resultado ser lançado de novo.')) {
                                router.post(route('panel.games.reopen', game.id), {}, { preserveScroll: true });
                            }
                        }}
                    >
                        Reabrir
                    </Button>
                    </>
                ) : (
                    <>
                        {game.home && game.away && (
                            <Link href={route('panel.games.sheet', game.id)} className="inline-flex items-center rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100">
                                Súmula
                            </Link>
                        )}
                        <Button small variant="secondary" onClick={onSchedule}>
                            Agendar
                        </Button>
                        <Button small onClick={onResult} disabled={!game.home || !game.away}>
                            Resultado
                        </Button>
                    </>
                )}
            </div>
        </li>
    );
}

export default function Show({ competition, season, division, stage, tables, games, venues, generation, statuses }: Props) {
    const [scheduling, setScheduling] = useState<Game | null>(null);
    const [recording, setRecording] = useState<Game | null>(null);

    const rounds = Array.from(new Set(games.map((game) => game.round ?? 0))).sort((a, b) => a - b);
    const hasTie = tables.some((table) => table.rows.some((row) => row.tied && row.played > 0));

    return (
        <PanelLayout>
            <Head title={`${stage.name} · ${division.name}`} />
            <PageHeader
                title={stage.name}
                subtitle={`${stage.type_label} · ${division.name}`}
                breadcrumbs={[
                    { label: 'Competições', href: route('panel.competitions.index') },
                    { label: competition.name, href: route('panel.competitions.show', competition.id) },
                    { label: season.name, href: route('panel.seasons.show', season.id) },
                    { label: division.name, href: route('panel.divisions.show', division.id) },
                    { label: stage.name },
                ]}
                actions={
                    generation.can_clear && (
                        <Button
                            variant="danger"
                            onClick={() => {
                                if (window.confirm('Limpar a tabela de jogos desta fase? Os jogos (e os grupos gerados) serão apagados e você poderá gerar de novo.')) {
                                    router.delete(route('panel.stages.fixtures.destroy', stage.id), { preserveScroll: true });
                                }
                            }}
                        >
                            Limpar tabela
                        </Button>
                    )
                }
            />

            <div className="space-y-6">
                {generation.can_generate && <GenerateCard stage={stage} confirmedEntries={generation.confirmed_entries} />}

                {tables.map((table) => (
                    <Card key={table.id ?? 'single'} title={stage.type === 'groups' ? `Classificação: ${table.name}` : 'Classificação'}>
                        {table.rows.length === 0 ? (
                            <Empty title="Sem equipes nesta classificação." />
                        ) : (
                            <div className="-m-5 overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                        <tr>
                                            <th className="px-3 py-2 text-left">#</th>
                                            <th className="px-3 py-2 text-left">Equipe</th>
                                            {['Pts', 'J', 'V', 'E', 'D', 'GP', 'GC', 'SG', 'CA', 'CV'].map((label) => (
                                                <th key={label} className="px-3 py-2 text-center">
                                                    {label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {table.rows.map((row) => (
                                            <tr key={row.team}>
                                                <td className="px-3 py-2 text-gray-500">{row.position}</td>
                                                <td className="px-3 py-2 font-medium text-gray-900">
                                                    {row.team}
                                                    {row.tied && row.played > 0 && <span className="ml-1 text-amber-600" title="Empate que só o sorteio resolve">*</span>}
                                                </td>
                                                <td className="px-3 py-2 text-center font-semibold">{row.points}</td>
                                                <td className="px-3 py-2 text-center">{row.played}</td>
                                                <td className="px-3 py-2 text-center">{row.wins}</td>
                                                <td className="px-3 py-2 text-center">{row.draws}</td>
                                                <td className="px-3 py-2 text-center">{row.losses}</td>
                                                <td className="px-3 py-2 text-center">{row.goals_for}</td>
                                                <td className="px-3 py-2 text-center">{row.goals_against}</td>
                                                <td className="px-3 py-2 text-center">{row.goal_difference}</td>
                                                <td className="px-3 py-2 text-center">{row.yellow_cards}</td>
                                                <td className="px-3 py-2 text-center">{row.red_cards}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                ))}
                {hasTie && <p className="text-xs text-amber-700">* Empate que nenhum critério do regulamento resolve: a decisão é por sorteio.</p>}

                {games.length > 0 && (
                    <Card title={`Jogos (${games.length})`}>
                        <div className="space-y-6">
                            {rounds.map((round) => (
                                <div key={round}>
                                    <h3 className="mb-1 text-sm font-semibold uppercase tracking-wide text-gray-500">{round > 0 ? (games.find((game) => game.round === round)?.round_label ?? `Rodada ${round}`) : 'Jogos'}</h3>
                                    <ul className="divide-y divide-gray-100">
                                        {games
                                            .filter((game) => (game.round ?? 0) === round)
                                            .map((game) => (
                                                <GameRow key={game.id} game={game} onSchedule={() => setScheduling(game)} onResult={() => setRecording(game)} />
                                            ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>

            {scheduling && <ScheduleModal key={scheduling.id} game={scheduling} venues={venues} statuses={statuses} onClose={() => setScheduling(null)} />}
            {recording && <ResultModal key={recording.id} game={recording} onClose={() => setRecording(null)} />}
        </PanelLayout>
    );
}
