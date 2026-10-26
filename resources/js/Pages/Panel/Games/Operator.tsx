import { Badge, Card, Empty, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { Head, useForm } from '@inertiajs/react';

interface TeamInfo {
    name: string;
    starters: number;
    bench: number;
    confirmed: boolean;
}

interface Props {
    game: {
        id: string;
        home: TeamInfo;
        away: TeamInfo;
        home_score: number;
        away_score: number;
        status: string;
        status_label: string;
        open: boolean;
        live: boolean;
        scheduled_at: string | null;
        venue: string | null;
        competition: string;
        stage: string;
        knockout: boolean;
        quick_result: boolean;
    };
    closure: { closed: boolean; can_close: boolean; by: string | null; at: string | null; signatures: number; required: number; notes: string | null };
    officials: { role: string; name: string }[];
    events: { id: string; label: string; minute: number | null; period: number; team: string | null; person: string | null }[];
    history: { id: string; at: string; actor: string; role: string | null; summary: string; outcome: 'ok' | 'refused' | 'failed'; changes: Record<string, [unknown, unknown]> }[];
    links: { remote: string | null; broadcast: string | null; reports: boolean; activity: string | null };
}

const TONE = { ok: 'green', refused: 'amber', failed: 'red' } as const;
const OUTCOME = { ok: 'Feito', refused: 'Recusado', failed: 'Erro' } as const;
const show = (value: unknown): string => (value === null || value === undefined || value === '' ? '—' : String(value));

function TeamLineup({ team }: { team: TeamInfo }) {
    return (
        <li className="flex items-center justify-between gap-3 text-sm">
            <span className="font-medium text-gray-900">{team.name}</span>
            <span className="flex flex-wrap items-center justify-end gap-2 text-gray-600">
                {team.starters} titulares · {team.bench} reservas
                {team.confirmed ? <Badge tone="green">Confirmada pelo gestor</Badge> : team.starters > 0 ? <Badge tone="blue">Escalada</Badge> : <Badge tone="amber">Sem escalação</Badge>}
            </span>
        </li>
    );
}

function SheetClosing({ gameId, closure, canOperate }: { gameId: string; closure: Props['closure']; canOperate: boolean }) {
    const { data, setData, post, processing } = useForm({ notes: '' });
    const missing = Math.max(0, closure.required - closure.signatures);

    return (
        <Card title="Fechamento da súmula">
            {closure.closed ? (
                <div className="space-y-1 text-sm">
                    <Badge tone="green">Súmula fechada</Badge>
                    <p className="text-gray-600">
                        Por {closure.by}{closure.at ? ` em ${new Date(closure.at).toLocaleString('pt-BR')}` : ''} · {closure.signatures} assinatura(s) confirmada(s).
                    </p>
                    {closure.notes && <p className="text-gray-700">{closure.notes}</p>}
                </div>
            ) : closure.can_close ? (
                <form
                    className="space-y-3 text-sm"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(route('panel.games.sheet.close', gameId), { preserveScroll: true });
                    }}
                >
                    <p className="text-gray-600">
                        Assinaturas confirmadas da súmula: <strong>{closure.signatures}</strong>{closure.required > 0 ? ` de ${closure.required} exigidas` : ' (nenhuma exigida)'}.
                        {missing > 0 && <span className="text-amber-700"> Faltam {missing}.</span>}
                    </p>
                    <textarea className="block w-full rounded-md border-gray-300 text-sm" rows={2} maxLength={500} placeholder="Observação do fechamento (opcional)" value={data.notes} onChange={(event) => setData('notes', event.target.value)} disabled={!canOperate} />
                    {canOperate && (
                        <button type="submit" disabled={processing || missing > 0} className="inline-flex min-h-11 items-center rounded-md bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700 disabled:opacity-50">
                            Fechar súmula
                        </button>
                    )}
                </form>
            ) : (
                <p className="text-sm text-gray-500">A súmula só pode ser fechada depois que o jogo for encerrado.</p>
            )}
        </Card>
    );
}

export default function Operator({ game, closure, officials, events, history, links }: Props) {
    const canOperate = useCan('operate-games');

    return (
        <PanelLayout>
            <Head title={`Operador — ${game.home.name} x ${game.away.name}`} />
            <PageHeader
                title="Painel do operador"
                subtitle={`${game.competition} · ${game.stage}`}
                actions={
                    <LinkButton href={route('panel.mesario.games')} variant="secondary">
                        Jogos do campeonato
                    </LinkButton>
                }
            />

            <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div className="flex items-center justify-between gap-2 text-xs text-gray-500">
                    <span>
                        {game.scheduled_at ? new Date(game.scheduled_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'Sem horário'}
                        {game.venue ? ` · ${game.venue}` : ''}
                    </span>
                    <Badge tone={game.live ? 'red' : game.open ? 'amber' : 'green'}>{game.live ? 'Ao vivo' : game.status_label}</Badge>
                </div>
                <div className="mt-3 flex items-center justify-between gap-3 text-base font-semibold sm:text-lg">
                    <span className="flex-1 text-right">{game.home.name}</span>
                    <span className="rounded bg-neutral-900 px-4 py-2 text-2xl font-bold tabular-nums text-white">{game.live || !game.open ? `${game.home_score} x ${game.away_score}` : 'x'}</span>
                    <span className="flex-1">{game.away.name}</span>
                </div>
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    <LinkButton href={route('panel.games.sheet', game.id)}>{canOperate && game.open ? 'Abrir súmula' : 'Ver súmula'}</LinkButton>
                    {links.remote && (
                        <LinkButton href={links.remote} variant="secondary">
                            Controle da transmissão
                        </LinkButton>
                    )}
                    {links.broadcast && (
                        <LinkButton href={links.broadcast} variant="secondary">
                            Transmissão
                        </LinkButton>
                    )}
                    {links.reports && (
                        <>
                            <a href={route('panel.games.reports.lineup', game.id)} className="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:min-h-0">
                                Escalação (PDF)
                            </a>
                            <a href={route('panel.games.reports.sheet', game.id)} className="inline-flex min-h-11 items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:min-h-0">
                                Súmula oficial (PDF)
                            </a>
                        </>
                    )}
                </div>
                {game.quick_result && canOperate && game.open && <p className="mt-3 text-center text-xs text-gray-500">O resultado rápido está disponível no app do mesário.</p>}
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card title="Escalações">
                    <ul className="space-y-2">
                        <TeamLineup team={game.home} />
                        <TeamLineup team={game.away} />
                    </ul>
                </Card>

                <SheetClosing gameId={game.id} closure={closure} canOperate={canOperate} />

                <Card title="Mesa e arbitragem">
                    {officials.length === 0 ? (
                        <p className="text-sm text-gray-500">Ninguém escalado para este jogo.</p>
                    ) : (
                        <ul className="space-y-1 text-sm">
                            {officials.map((official) => (
                                <li key={`${official.role}-${official.name}`} className="flex justify-between gap-3">
                                    <span className="text-gray-600">{official.role}</span>
                                    <span className="font-medium text-gray-900">{official.name}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Últimos lances">
                    {events.length === 0 ? (
                        <p className="text-sm text-gray-500">Nenhum lance lançado ainda.</p>
                    ) : (
                        <ul className="space-y-2 text-sm" data-testid="operator-events">
                            {events.map((event) => (
                                <li key={event.id} className="flex flex-wrap justify-between gap-2">
                                    <span className="font-medium text-gray-900">
                                        {event.label}
                                        {event.minute !== null ? ` · ${event.minute}'` : ''}
                                    </span>
                                    <span className="text-gray-600">{[event.person, event.team].filter(Boolean).join(' · ')}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card title="Alterações recentes">
                    {history.length === 0 ? (
                        <Empty title="Sem alterações registradas.">As ações feitas neste jogo aparecem aqui, com quem fez.</Empty>
                    ) : (
                        <ul className="space-y-3 text-sm" data-testid="operator-history">
                            {history.map((entry) => (
                                <li key={entry.id}>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="font-medium text-gray-900">{entry.summary}</span>
                                        <Badge tone={TONE[entry.outcome]}>{OUTCOME[entry.outcome]}</Badge>
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        {entry.actor}
                                        {entry.role ? ` (${entry.role})` : ''} · {new Date(entry.at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' })}
                                    </div>
                                    {Object.entries(entry.changes).map(([field, pair]) => (
                                        <div key={field} className="text-xs text-gray-600">
                                            <code>{field}</code>: {show(pair[0])} → <strong>{show(pair[1])}</strong>
                                        </div>
                                    ))}
                                </li>
                            ))}
                        </ul>
                    )}
                    {links.activity && (
                        <div className="mt-3">
                            <a href={links.activity} className="text-sm font-medium text-emerald-700 hover:underline">
                                Ver o registro completo deste jogo
                            </a>
                        </div>
                    )}
                </Card>
            </div>
        </PanelLayout>
    );
}
