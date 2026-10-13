import { ConnectionState, Realtime, subscribeToGame } from '@/lib/echo';
import { GameClock, formatClock, periodLabel, useGameClock } from '@/lib/clock';
import { Head, Link } from '@inertiajs/react';
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
    score_model: string;
    sets: { home: number; away: number; winner: 'home' | 'away' | null; finished: boolean }[] | null;
    fouls: { home: number; away: number; limit: number; period: number } | null;
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
    else if (snapshot.sets) clockText = snapshot.sets.length > 0 && snapshot.status !== 'scheduled' ? `Set ${snapshot.sets.length} em andamento` : snapshot.status === 'scheduled' ? 'Em breve' : snapshot.status_label;
    else if (snapshot.clock.state === 'not_started') clockText = snapshot.status === 'scheduled' ? 'Em breve' : snapshot.status_label;
    else if (snapshot.clock.state === 'stopped') clockText = snapshot.clock.period === 1 ? 'Intervalo' : `Fim do ${periodLabel(snapshot.clock.period)}`;
    else clockText = `${formatClock(seconds)} · ${periodLabel(snapshot.clock.period)}`;

    return (
        <div className="min-h-screen bg-gray-900 px-4 py-8 text-white">
            <Head title={`${home} x ${away}`}>
                <meta name="description" content={`${home} x ${away} — ${snapshot.competition}, ${snapshot.division}. Placar e lances ao vivo.`} />
                <link rel="canonical" href={route('public.games.show', snapshot.id)} />
                <meta property="og:type" content="website" />
                <meta property="og:title" content={`${home} ${snapshot.home_score} x ${snapshot.away_score} ${away}`} />
                <meta property="og:description" content={`${snapshot.competition} · ${snapshot.division} · ${snapshot.stage}`} />
                <meta property="og:url" content={route('public.games.show', snapshot.id)} />
                {snapshot.home?.logo_url && <meta property="og:image" content={snapshot.home.logo_url} />}
            </Head>
            <main className="mx-auto max-w-3xl">
                <Link href={route('portal.home')} className="text-sm text-gray-400 hover:text-white">
                    ← Portal
                </Link>
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
                {snapshot.sets && snapshot.sets.length > 0 && (
                    <p className="mt-1 text-center text-sm tabular-nums text-gray-400" data-testid="live-sets">
                        {snapshot.sets.map((set) => `${set.home}-${set.away}${set.finished ? '' : ' (em andamento)'}`).join(' · ')}
                    </p>
                )}
                {snapshot.fouls && (
                    <p className="mt-1 text-center text-sm text-gray-400" data-testid="live-fouls">
                        Faltas: {snapshot.fouls.home} x {snapshot.fouls.away}
                    </p>
                )}
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
