import { formatClock, periodLabel, useGameClock } from '@/lib/clock';
import { Realtime } from '@/lib/echo';
import { OverlayState, OverlayTeam, useOverlay } from '@/lib/overlay';
import { Head } from '@inertiajs/react';

function Side({ team, align }: { team: OverlayTeam; align: 'left' | 'right' }) {
    const label = (team.short_name || team.name).toUpperCase();
    const logo = team.logo_url ? <img src={team.logo_url} alt="" className="h-9 w-9 object-contain" /> : null;

    return (
        <div className="flex items-center gap-3 px-4" style={{ borderBottom: `4px solid ${team.color ?? '#9ca3af'}` }}>
            {align === 'left' && logo}
            <span className="max-w-[12rem] truncate text-xl font-bold tracking-wide">{label}</span>
            {align === 'right' && logo}
        </div>
    );
}

export default function Scoreboard({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
    const game = state.game;
    const seconds = useGameClock(game?.clock ?? { state: 'not_started', period: null, base_seconds: 0, running_since_ms: null }, skew);

    if (!game || !game.home || !game.away) {
        return <Head title="Placar" />;
    }

    let clock: string;
    if (game.finished) clock = 'FIM';
    else if (game.clock.state === 'not_started') clock = game.status === 'scheduled' ? 'EM BREVE' : game.status_label.toUpperCase();
    else if (game.clock.state === 'stopped') clock = game.clock.period === 1 ? 'INTERVALO' : 'FIM DO TEMPO';
    else clock = formatClock(seconds);

    return (
        <div className="p-8 font-sans text-white" style={{ textShadow: '0 1px 2px rgba(0,0,0,.6)' }}>
            <Head title="Placar" />
            <div className="inline-flex items-stretch overflow-hidden rounded-lg bg-gray-900/90 shadow-xl" data-testid="overlay-scoreboard">
                <Side team={game.home} align="left" />
                <div className="flex items-center gap-3 bg-black/60 px-5 text-4xl font-extrabold tabular-nums" data-testid="overlay-score">
                    <span>{game.home_score}</span>
                    <span className="text-2xl text-gray-400">×</span>
                    <span>{game.away_score}</span>
                </div>
                <Side team={game.away} align="right" />
                <div className="flex min-w-[7.5rem] flex-col items-center justify-center bg-emerald-600 px-4 py-1 text-center" data-testid="overlay-clock">
                    <span className="text-xl font-bold tabular-nums">{clock}</span>
                    {game.clock.state === 'running' && <span className="text-[10px] uppercase tracking-wider">{periodLabel(game.clock.period)}</span>}
                </div>
            </div>
            {game.home_penalties !== null && game.away_penalties !== null && (
                <div className="mt-1 inline-block rounded bg-gray-900/90 px-3 py-1 text-sm">
                    Pênaltis: {game.home_penalties} x {game.away_penalties}
                </div>
            )}
        </div>
    );
}
