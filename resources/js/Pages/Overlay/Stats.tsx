import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { overlayStyle } from '@/lib/overlayVisual';
import { barShares, isExpired, leader, StatsState, StatsStyle } from '@/lib/statsLook';
import { Head } from '@inertiajs/react';
import { CSSProperties, useEffect, useState } from 'react';

const SHELL: Record<StatsStyle, string> = {
    broadcast: 'rounded-2xl border border-white/15 shadow-2xl',
    glass: 'rounded-2xl border border-white/25 shadow-2xl backdrop-blur-md',
    minimal: 'rounded-none',
};

function shell(style: StatsStyle, background: string): CSSProperties {
    return style === 'broadcast' ? { background: `${background}f2` } : style === 'glass' ? { background: `${background}80` } : { background: 'transparent', textShadow: '0 2px 4px rgba(0,0,0,.8)' };
}

function Comparative({ stats, state }: { stats: StatsState; state: OverlayState }) {
    const home = state.game?.home;
    const away = state.game?.away;
    const colorHome = home?.color ?? state.appearance.accent_color;
    const colorAway = away?.color ?? '#94a3b8';

    return (
        <div className="space-y-3" data-testid="stats-comparative">
            <div className="flex items-center justify-between text-2xl font-black uppercase tracking-wide">
                <span style={{ color: colorHome }}>{home?.short_name || home?.name}</span>
                <span className="text-sm tracking-[0.3em] text-white/60">Estatísticas</span>
                <span style={{ color: colorAway }}>{away?.short_name || away?.name}</span>
            </div>
            {(stats.rows ?? []).map((row) => {
                const share = barShares(row.home, row.away);
                const lead = leader(row);
                const suffix = row.key === 'possession' ? '%' : '';

                return (
                    <div key={row.key} data-testid={`stats-row-${row.key}`}>
                        <div className="mb-1 flex items-baseline justify-between text-xl font-black tabular-nums">
                            <span className={lead === 'home' ? '' : 'text-white/70'}>{row.home}{suffix}</span>
                            <span className="text-sm font-bold uppercase tracking-widest text-white/70">{row.label}</span>
                            <span className={lead === 'away' ? '' : 'text-white/70'}>{row.away}{suffix}</span>
                        </div>
                        <div className="flex h-3 gap-1">
                            <div className="flex flex-1 justify-end overflow-hidden rounded-l bg-white/10"><span className="h-full" style={{ width: `${share.home}%`, backgroundColor: colorHome }} /></div>
                            <div className="flex flex-1 overflow-hidden rounded-r bg-white/10"><span className="h-full" style={{ width: `${share.away}%`, backgroundColor: colorAway }} /></div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function Events({ stats, state }: { stats: StatsState; state: OverlayState }) {
    const events = stats.events;
    if (!events) return null;
    const names = { home: state.game?.home?.name ?? '', away: state.game?.away?.name ?? '' };

    return (
        <div className="grid grid-cols-2 gap-8" data-testid="stats-events">
            {(['home', 'away'] as const).map((side) => (
                <div key={side}>
                    <div className="mb-2 border-b border-white/20 pb-1 text-2xl font-black uppercase">{names[side]}</div>
                    <ul className="space-y-1 text-lg">
                        {events.goals.filter((goal) => goal.side === side).map((goal, index) => <li key={`g${index}`}>⚽ {goal.player ?? 'Gol'}{goal.own_goal ? ' (contra)' : ''}{goal.minute !== null && <span className="text-white/60"> {goal.minute}'</span>}</li>)}
                        {(events.cards ?? []).filter((card) => card.side === side).map((card, index) => <li key={`c${index}`}>{card.kind === 'red_card' ? '🟥' : '🟨'} {card.player ?? '—'}{card.minute !== null && <span className="text-white/60"> {card.minute}'</span>}</li>)}
                    </ul>
                </div>
            ))}
        </div>
    );
}

function Player({ stats, state }: { stats: StatsState; state: OverlayState }) {
    const player = stats.player;
    if (!player) return null;
    const color = (player.side === 'away' ? state.game?.away?.color : state.game?.home?.color) ?? state.appearance.accent_color;

    return (
        <div className="flex items-stretch gap-6" data-testid="stats-player">
            <div className="relative grid h-56 w-44 place-items-center overflow-hidden rounded-xl" style={{ background: `linear-gradient(180deg, ${color}66, ${color}22)` }}>
                {player.photo_url ? <img src={player.photo_url} alt="" className="absolute inset-0 h-full w-full object-cover object-top" /> : <span className="text-7xl font-black text-white/25">{player.shirt ?? '—'}</span>}
            </div>
            <div className="flex min-w-[22rem] flex-col justify-center gap-2">
                <span className="w-fit px-3 py-1 text-xl font-black uppercase tracking-[0.2em]" style={{ backgroundColor: color }}>{player.team}</span>
                <span className="text-6xl font-black uppercase leading-none">{player.shirt !== null && <span className="mr-3 tabular-nums" style={{ color }}>{player.shirt}</span>}{player.name}</span>
                <div className="flex gap-6 text-2xl font-black tabular-nums">
                    <span>⚽ {player.goals}</span>
                    {player.cards && <span>🟨 {player.cards.yellow}</span>}
                    {player.cards && <span>🟥 {player.cards.red}</span>}
                </div>
                {player.note && <span className="text-lg font-semibold text-white/80">{player.note}</span>}
            </div>
        </div>
    );
}

/** Estatísticas da partida: comparativo, gols e cartões ou destaque do atleta, com saída automática pelo prazo do servidor. */
export default function Stats({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
    const stats = state.stats;
    const [now, setNow] = useState(() => Date.now() - skew);

    useEffect(() => {
        if (!stats || stats.expires_ms === null) return;
        const update = () => setNow(Date.now() - skew);
        update();
        const timer = window.setInterval(update, 500);

        return () => window.clearInterval(timer);
    }, [stats?.expires_ms, skew]); // eslint-disable-line react-hooks/exhaustive-deps

    const visible = state.screens.stats === true && stats !== null && stats !== undefined && !isExpired(stats.expires_ms, now);
    const appearance = state.appearance;
    const free = stats && typeof stats.x === 'number' && typeof stats.y === 'number';
    const zoom = stats && stats.text_scale !== 100 ? stats.text_scale / 100 : undefined;

    return (
        <div className={`fixed inset-0 font-sans ${free ? '' : 'flex items-end justify-center pb-24'}`} style={{ ...overlayStyle(appearance), color: appearance.text_color }} data-testid="overlay-stats" data-visible={visible}>
            <Head title="Estatísticas" />
            {visible && stats && (
                <div style={{ ...(free ? { position: 'absolute', left: `${stats.x}%`, top: `${stats.y}%` } : {}), transform: `scale(${(stats.scale / 100) * appearance.scale})`, transformOrigin: free ? 'top left' : 'bottom center' }}>
                    <div key={stats.view} className={`min-w-[44rem] max-w-[60rem] px-8 py-6 ${SHELL[stats.style]} ${appearance.motion === 'none' ? '' : `stats-in-${stats.animation}`}`} style={{ ...shell(stats.style, appearance.background_color), zoom }}>
                        {stats.view === 'comparative' && <Comparative stats={stats} state={state} />}
                        {stats.view === 'events' && <Events stats={stats} state={state} />}
                        {stats.view === 'player' && <Player stats={stats} state={state} />}
                    </div>
                </div>
            )}
        </div>
    );
}
