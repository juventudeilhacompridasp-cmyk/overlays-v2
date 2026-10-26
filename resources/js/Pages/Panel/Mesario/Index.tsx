import { Badge, Button, Empty, Input, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { useInstallPrompt } from '@/lib/pwa';
import { useSheetQueue } from '@/lib/sheetQueue';
import { Head } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

interface MesarioGame {
    id: string;
    home: string;
    away: string;
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
    quick_result: boolean;
    knockout: boolean;
    sport: { label: string };
}

function QuickResult({ game, userId }: { game: MesarioGame; userId: number | string }) {
    const [home, setHome] = useState(game.home_score.toString());
    const [away, setAway] = useState(game.away_score.toString());
    const [queued, setQueued] = useState(false);
    const { enqueue } = useSheetQueue({ gameId: game.id, userId, skewMs: 0, enabled: true });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (window.confirm(`Encerrar ${game.home} ${home} x ${away} ${game.away}?`)) {
            enqueue('quick_result', { home_score: Number(home), away_score: Number(away) }, `Resultado rápido — ${game.home} x ${game.away}`);
            setQueued(true);
        }
    };

    return (
        <form onSubmit={submit} className="mt-3 flex items-center gap-2" aria-label="Resultado rápido">
            <Input type="number" inputMode="numeric" min={0} max={999} value={home} onChange={(e) => setHome(e.target.value)} className="w-20 text-center text-lg" aria-label={`Placar de ${game.home}`} />
            <span className="text-gray-400">x</span>
            <Input type="number" inputMode="numeric" min={0} max={999} value={away} onChange={(e) => setAway(e.target.value)} className="w-20 text-center text-lg" aria-label={`Placar de ${game.away}`} />
            <Button type="submit" variant="secondary">
                {queued ? 'Resultado salvo na fila' : 'Encerrar'}
            </Button>
        </form>
    );
}

type Tab = 'all' | 'live' | 'next' | 'done';

const TABS: { key: Tab; label: string }[] = [
    { key: 'all', label: 'Todos' },
    { key: 'live', label: 'Ao vivo' },
    { key: 'next', label: 'Próximos' },
    { key: 'done', label: 'Encerrados' },
];

const tabOf = (game: MesarioGame): Tab => (game.live ? 'live' : game.open ? 'next' : 'done');

/** Sem acento e sem caixa, para a busca achar "São" digitando "sao". */
const plain = (value: string) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

export default function Index({ games, user_id }: { games: MesarioGame[]; user_id: number | string }) {
    const canOperate = useCan('operate-games');
    const { available, install } = useInstallPrompt();
    const [tab, setTab] = useState<Tab>('all');
    const [query, setQuery] = useState('');
    const counts = useMemo(() => ({ all: games.length, live: games.filter((g) => g.live).length, next: games.filter((g) => !g.live && g.open).length, done: games.filter((g) => !g.open).length }), [games]);
    const shown = useMemo(() => {
        const words = plain(query).split(/\s+/).filter(Boolean);

        return games.filter((game) => (tab === 'all' || tabOf(game) === tab) && words.every((word) => plain(`${game.home} ${game.away} ${game.competition} ${game.stage} ${game.venue ?? ''}`).includes(word)));
    }, [games, tab, query]);

    return (
        <PanelLayout>
            <Head title="Mesário" />
            <PageHeader
                title="Mesário"
                subtitle="Jogos ao vivo, de hoje e dos próximos dias. Toque para abrir a súmula."
                actions={
                    <div className="flex flex-wrap gap-2">
                        <LinkButton href={route('panel.mesario.games')} variant="secondary">
                            Jogos do campeonato
                        </LinkButton>
                        {available && (
                            <Button variant="secondary" onClick={install}>
                                Instalar app
                            </Button>
                        )}
                    </div>
                }
            />
            {games.length > 0 && (
                <div className="mb-4 space-y-3">
                    <div className="flex flex-wrap gap-2" role="tablist" aria-label="Situação dos jogos">
                        {TABS.map((entry) => (
                            <button
                                key={entry.key}
                                type="button"
                                role="tab"
                                aria-selected={tab === entry.key}
                                onClick={() => setTab(entry.key)}
                                className={`min-h-11 rounded-full px-4 text-sm font-medium ring-1 sm:min-h-0 sm:py-1.5 ${tab === entry.key ? 'bg-emerald-600 text-white ring-emerald-600' : 'bg-white text-gray-700 ring-gray-300 hover:bg-gray-50'}`}
                            >
                                {entry.label} ({counts[entry.key]})
                            </button>
                        ))}
                    </div>
                    <Input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar equipe, competição ou local" aria-label="Buscar jogo" />
                </div>
            )}
            {games.length === 0 ? (
                <Empty title="Nenhum jogo por agora.">Quando houver jogos ao vivo, hoje ou nos próximos dois dias, eles aparecem aqui.</Empty>
            ) : shown.length === 0 ? (
                <Empty title="Nenhum jogo nesta seleção.">Troque a aba ou limpe a busca.</Empty>
            ) : (
                <ul className="space-y-3" data-testid="mesario-games">
                    {shown.map((game) => (
                        <li key={game.id} className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                            <div className="flex items-center justify-between gap-2 text-xs text-gray-500">
                                <span className="truncate">
                                    {game.competition} · {game.stage}
                                </span>
                                <div className="flex items-center gap-2">
                                    <Badge tone="blue">{game.sport.label}</Badge>
                                    <Badge tone={game.live ? 'red' : game.open ? 'amber' : 'green'}>{game.live ? 'Ao vivo' : game.status_label}</Badge>
                                </div>
                            </div>
                            <div className="mt-2 flex items-center justify-between gap-3 text-base font-semibold">
                                <span className="flex-1 text-right">{game.home}</span>
                                <span className="rounded bg-neutral-900 px-3 py-1 text-xl font-bold tabular-nums text-white">
                                    {game.live || !game.open ? `${game.home_score} x ${game.away_score}` : 'x'}
                                </span>
                                <span className="flex-1">{game.away}</span>
                            </div>
                            <div className="mt-1 text-center text-xs text-gray-500">
                                {game.scheduled_at ?? 'Sem horário'}
                                {game.venue ? ` · ${game.venue}` : ''}
                            </div>
                            <div className="mt-3 flex flex-wrap justify-center gap-2">
                                <LinkButton href={route('panel.games.sheet', game.id)}>{canOperate && game.open ? 'Abrir súmula' : 'Ver súmula'}</LinkButton>
                                <LinkButton href={route('panel.games.operator', game.id)} variant="secondary">
                                    Painel do operador
                                </LinkButton>
                            </div>
                            {canOperate && game.open && game.quick_result && !game.knockout && <QuickResult game={game} userId={user_id} />}
                        </li>
                    ))}
                </ul>
            )}
        </PanelLayout>
    );
}
