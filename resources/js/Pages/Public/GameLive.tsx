import { ConnectionState, Realtime, subscribeToGame } from '@/lib/echo';
import { GameClock, formatClock, periodLabel, useGameClock } from '@/lib/clock';
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Goal {
    side: 'home' | 'away' | null;
    player: string | null;
    minute: number | null;
    period: number;
    own_goal: boolean;
}

interface Team {
    name: string;
    short_name: string | null;
    logo_url: string | null;
}

export interface Snapshot {
    id: string;
    status: string;
    status_label: string;
    finished: boolean;
    home: Team | null;
    away: Team | null;
    home_score: number;
    away_score: number;
    home_penalties: number | null;
    away_penalties: number | null;
    scheduled_at: string | null;
    venue: string | null;
    competition: string;
    division: string;
    stage: string;
    clock: GameClock;
    goals: Goal[];
    server_time_ms: number;
}

const POLL_MS = 5000;

function GoalList({ goals, side }: { goals: Goal[]; side: 'home' | 'away' }) {
    const mine = goals.filter((goal) => goal.side === side);

    return (
        <ul className={`space-y-1 text-sm text-gray-300 ${side === 'home' ? 'text-right' : 'text-left'}`}>
            {mine.map((goal, index) => (
                <li key={index}>
                    ⚽ {goal.player ?? 'Gol'}
                    {goal.own_goal && ' (contra)'}
                    {goal.minute !== null && <span className="ml-1 text-gray-500">{goal.minute}'</span>}
                </li>
            ))}
        </ul>
    );
}

export default function GameLive({ snapshot: initial, realtime }: { snapshot: Snapshot; realtime: Realtime }) {
    const [snapshot, setSnapshot] = useState(initial);
    const [connection, setConnection] = useState<ConnectionState>('connecting');
    // Diferença entre o relógio do navegador e o do servidor, medida na carga da página (não nos deltas, que podem atrasar na fila).
    const skew = useRef(0);
    const [ready, setReady] = useState(false);

    useEffect(() => {
        skew.current = Date.now() - initial.server_time_ms;
        setReady(true);
    }, [initial.server_time_ms]);

    useEffect(() => {
        let cancel: (() => void) | undefined;
        let cancelled = false;

        subscribeToGame<Snapshot>(realtime, initial.id, setSnapshot, setConnection)
            .then((stop) => (cancelled ? stop() : (cancel = stop)))
            .catch(() => setConnection('disconnected'));

        return () => {
            cancelled = true;
            cancel?.();
        };
    }, [initial.id]);

    // Contingência: sem WebSocket, consulta o estado a cada poucos segundos (e para quando o jogo termina).
    useEffect(() => {
        if (connection === 'connected' || snapshot.finished) {
            return;
        }

        const timer = window.setInterval(async () => {
            try {
                const response = await fetch(route('public.games.state', initial.id), { headers: { Accept: 'application/json' } });
                if (response.ok) setSnapshot(await response.json());
            } catch {
                // sem rede: tenta de novo no próximo ciclo
            }
        }, POLL_MS);

        return () => window.clearInterval(timer);
    }, [connection, snapshot.finished, initial.id]);

    const seconds = useGameClock(snapshot.clock, skew.current);
    const home = snapshot.home?.name ?? 'A definir';
    const away = snapshot.away?.name ?? 'A definir';

    let clockText: string;
    if (snapshot.finished) clockText = 'Encerrado';
    else if (snapshot.clock.state === 'not_started') clockText = snapshot.status === 'scheduled' ? 'Em breve' : snapshot.status_label;
    else if (snapshot.clock.state === 'stopped') clockText = snapshot.clock.period === 1 ? 'Intervalo' : `Fim do ${periodLabel(snapshot.clock.period)}`;
    else clockText = `${formatClock(seconds)} · ${periodLabel(snapshot.clock.period)}`;

    return (
        <div className="min-h-screen bg-gray-900 px-4 py-8 text-white">
            <Head title={`${home} x ${away}`} />
            <main className="mx-auto max-w-3xl">
                <p className="text-center text-sm text-gray-400">
                    {snapshot.competition} · {snapshot.division} · {snapshot.stage}
                </p>
                <div className="mt-6 grid grid-cols-[1fr_auto_1fr] items-center gap-4">
                    <div className="flex flex-col items-end gap-2">
                        {snapshot.home?.logo_url && <img src={snapshot.home.logo_url} alt="" className="h-16 w-16 object-contain sm:h-24 sm:w-24" />}
                        <h1 className="text-right text-xl font-bold sm:text-2xl">{home}</h1>
                    </div>
                    <div className="rounded-lg bg-black/40 px-6 py-3 text-center" data-testid="live-score">
                        <span className="text-5xl font-extrabold tabular-nums">
                            {snapshot.home_score} x {snapshot.away_score}
                        </span>
                        {snapshot.home_penalties !== null && snapshot.away_penalties !== null && (
                            <div className="text-sm text-gray-400">
                                ({snapshot.home_penalties}) pên. ({snapshot.away_penalties})
                            </div>
                        )}
                    </div>
                    <div className="flex flex-col items-start gap-2">
                        {snapshot.away?.logo_url && <img src={snapshot.away.logo_url} alt="" className="h-16 w-16 object-contain sm:h-24 sm:w-24" />}
                        <h1 className="text-left text-xl font-bold sm:text-2xl">{away}</h1>
                    </div>
                </div>
                <p className="mt-4 text-center text-lg font-semibold tabular-nums text-emerald-400" data-testid="live-clock" suppressHydrationWarning>
                    {ready || snapshot.clock.state !== 'running' ? clockText : '—'}
                </p>
                <div className="mt-6 grid grid-cols-2 gap-6">
                    <GoalList goals={snapshot.goals} side="home" />
                    <GoalList goals={snapshot.goals} side="away" />
                </div>
                {snapshot.venue && <p className="mt-8 text-center text-sm text-gray-500">{snapshot.venue}</p>}
                <p className="mt-6 text-center text-xs text-gray-600" data-testid="live-connection">
                    {connection === 'connected' ? '● ao vivo' : connection === 'connecting' ? '○ conectando…' : '○ atualizando a cada poucos segundos'}
                </p>
            </main>
        </div>
    );
}
