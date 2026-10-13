import { Badge, Button, Empty, Input, LinkButton, PageHeader } from '@/Components/Panel/ui';
import PanelLayout from '@/Layouts/PanelLayout';
import { useCan } from '@/lib/can';
import { useInstallPrompt } from '@/lib/pwa';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

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
}

function QuickResult({ game }: { game: MesarioGame }) {
    const [home, setHome] = useState(game.home_score.toString());
    const [away, setAway] = useState(game.away_score.toString());

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (window.confirm(`Encerrar ${game.home} ${home} x ${away} ${game.away}?`)) {
            router.post(route('panel.games.result', game.id), { home_score: home, away_score: away }, { preserveScroll: true });
        }
    };

    return (
        <form onSubmit={submit} className="mt-3 flex items-center gap-2" aria-label="Resultado rápido">
            <Input type="number" inputMode="numeric" min={0} max={999} value={home} onChange={(e) => setHome(e.target.value)} className="w-20 text-center text-lg" aria-label={`Placar de ${game.home}`} />
            <span className="text-gray-400">x</span>
            <Input type="number" inputMode="numeric" min={0} max={999} value={away} onChange={(e) => setAway(e.target.value)} className="w-20 text-center text-lg" aria-label={`Placar de ${game.away}`} />
            <Button type="submit" variant="secondary">
                Encerrar
            </Button>
        </form>
    );
}

export default function Index({ games }: { games: MesarioGame[] }) {
    const canOperate = useCan('operate-games');
    const { available, install } = useInstallPrompt();

    return (
        <PanelLayout>
            <Head title="Mesário" />
            <PageHeader
                title="Mesário"
                subtitle="Jogos ao vivo, de hoje e dos próximos dias. Toque para abrir a súmula."
                actions={
                    available ? (
                        <Button variant="secondary" onClick={install}>
                            Instalar app
                        </Button>
                    ) : undefined
                }
            />
            {games.length === 0 ? (
                <Empty title="Nenhum jogo por agora.">Quando houver jogos ao vivo, hoje ou nos próximos dois dias, eles aparecem aqui.</Empty>
            ) : (
                <ul className="space-y-3" data-testid="mesario-games">
                    {games.map((game) => (
                        <li key={game.id} className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
                            <div className="flex items-center justify-between gap-2 text-xs text-gray-500">
                                <span className="truncate">
                                    {game.competition} · {game.stage}
                                </span>
                                <Badge tone={game.live ? 'red' : game.open ? 'amber' : 'green'}>{game.live ? 'Ao vivo' : game.status_label}</Badge>
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
                            <div className="mt-3 flex justify-center">
                                <LinkButton href={route('panel.games.sheet', game.id)}>{canOperate && game.open ? 'Abrir súmula' : 'Ver súmula'}</LinkButton>
                            </div>
                            {canOperate && game.open && game.quick_result && !game.knockout && <QuickResult game={game} />}
                        </li>
                    ))}
                </ul>
            )}
        </PanelLayout>
    );
}
