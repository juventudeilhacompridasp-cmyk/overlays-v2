import { Realtime } from '@/lib/echo';
import { LineupSide, OverlayState, useOverlay } from '@/lib/overlay';
import { overlayMotion, overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Player = LineupSide['starters'][number];

function AthletePhoto({ player, size = 'large' }: { player: Pick<Player, 'photo_url' | 'shirt'>; size?: 'small' | 'large' }) {
    const dimension = size === 'large' ? 'h-[4.6rem] w-[4.6rem]' : 'h-11 w-11';

    return (
        <span className={`grid shrink-0 place-items-center overflow-hidden rounded-full border-2 border-white/80 bg-slate-900 font-black shadow-lg ${dimension}`}>
            {player.photo_url ? <img src={player.photo_url} alt="" className="h-full w-full object-cover" /> : <span className={size === 'large' ? 'text-2xl' : 'text-base'}>{player.shirt ?? '—'}</span>}
        </span>
    );
}

function TeamHeader({ side, accent }: { side: LineupSide; accent: string }) {
    return (
        <div className="relative flex min-h-[5.25rem] items-center gap-4 overflow-hidden px-6 py-3">
            <span className="absolute inset-x-0 bottom-0 h-1" style={{ backgroundColor: side.color ?? accent }} />
            {side.logo_url && <span className="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-white/95 p-1.5"><img src={side.logo_url} alt="" className="h-full w-full object-contain" /></span>}
            <span className="min-w-0 flex-1 truncate text-2xl font-black uppercase tracking-wide">{side.name}</span>
            {side.formation && <span className="rounded-full bg-white/10 px-3 py-1 text-lg font-black" style={{ color: accent }}>{side.formation}</span>}
        </div>
    );
}

function ListColumn({ side, background, accent }: { side: LineupSide; background: string; accent: string }) {
    return (
        <section className="w-[32rem] overflow-hidden rounded-3xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(160deg, ${background}f7, ${background}e8)` }}>
            <TeamHeader side={side} accent={accent} />
            <ol className="grid grid-cols-2 gap-x-3 px-5 py-4">
                {side.starters.map((player, index) => (
                    <li key={`${player.shirt}-${player.name}-${index}`} className="flex min-w-0 items-center gap-2 border-b border-white/10 py-1.5">
                        <AthletePhoto player={player} size="small" />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-base font-bold">{player.name}{player.captain && <span className="ml-1 text-xs text-amber-300">C</span>}</span>
                            <span className="block text-xs font-semibold uppercase tracking-wider text-white/60">{player.position_label ?? 'Titular'}</span>
                        </span>
                    </li>
                ))}
            </ol>
            {side.bench.length > 0 && (
                <div className="border-t border-white/10 px-5 py-3 text-sm text-white/70">
                    <span className="font-bold uppercase tracking-wide">Reservas · </span>
                    {side.bench.map((player) => `${player.shirt ?? ''} ${player.name}${player.captain ? ' (C)' : ''}`.trim()).join(' · ')}
                </div>
            )}
            {side.coach && <div className="border-t border-white/10 px-5 py-3 text-sm"><span className="font-semibold uppercase text-white/60">Técnico · </span>{side.coach}</div>}
        </section>
    );
}

function SurfaceMarkings({ sport }: { sport: string }) {
    if (sport === 'basketball') {
        return <><span className="absolute inset-y-0 left-1/2 border-l-2 border-white/30" /><span className="absolute left-1/2 top-1/2 h-28 w-28 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/30" /><span className="absolute inset-x-[18%] top-0 h-[24%] border-x-2 border-b-2 border-white/30" /><span className="absolute inset-x-[18%] bottom-0 h-[24%] border-x-2 border-t-2 border-white/30" /></>;
    }

    if (sport === 'volleyball') {
        return <><span className="absolute inset-y-0 left-1/2 border-l-[3px] border-white/60" /><span className="absolute inset-y-0 left-[33%] border-l-2 border-white/30" /><span className="absolute inset-y-0 left-[67%] border-l-2 border-white/30" /></>;
    }

    return <><span className="absolute inset-x-0 top-1/2 border-t-2 border-white/30" /><span className="absolute left-1/2 top-1/2 h-32 w-32 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white/30" /><span className="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/40" /><span className="absolute inset-x-[22%] top-0 h-[17%] border-x-2 border-b-2 border-white/30" /><span className="absolute inset-x-[22%] bottom-0 h-[17%] border-x-2 border-t-2 border-white/30" /></>;
}

function TacticalPlayer({ player, accent }: { player: Player; accent: string }) {
    if (player.x === null || player.y === null) return null;
    const x = Math.min(90, Math.max(10, player.x));
    const y = Math.min(84, Math.max(12, player.y));

    return (
        <div className="absolute flex -translate-x-1/2 -translate-y-1/2 flex-col items-center" style={{ left: `${x}%`, top: `${y}%` }}>
            <div className="relative">
                <AthletePhoto player={player} />
                {player.captain && <span className="absolute -right-1 -top-1 grid h-6 w-6 place-items-center rounded-full bg-amber-300 text-xs font-black text-slate-900 shadow">C</span>}
            </div>
            <span className="mt-1 max-w-40 truncate rounded-full border border-white/20 bg-slate-950/90 px-3 py-1 text-sm font-black shadow-lg">{player.name}</span>
            {player.position && <span className="mt-0.5 text-[10px] font-black uppercase tracking-[0.18em]" style={{ color: accent }}>{player.position}</span>}
        </div>
    );
}

function TacticalSide({ side, sport, background, accent }: { side: LineupSide; sport: string; background: string; accent: string }) {
    const court = sport === 'basketball' || sport === 'volleyball';

    return (
        <section className="overflow-hidden rounded-3xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(160deg, ${background}f7, ${background}e8)` }}>
            <TeamHeader side={side} accent={accent} />
            <div className={`relative m-4 overflow-hidden rounded-2xl border-2 border-white/45 ${court ? 'h-[31rem] w-[36rem]' : 'h-[43rem] w-[31rem]'}`} style={{ background: sport === 'basketball' ? 'linear-gradient(90deg,#a85f2d,#c77a3b,#a85f2d)' : sport === 'volleyball' ? 'linear-gradient(90deg,#d1943d 0 50%,#3b82a0 50%)' : 'linear-gradient(90deg,#087443,#0a8150,#087443)' }}>
                <div className="absolute inset-3 border-2 border-white/35" />
                <SurfaceMarkings sport={sport} />
                {side.starters.map((player, index) => <TacticalPlayer key={`${player.shirt}-${player.name}-${index}`} player={player} accent={accent} />)}
            </div>
        </section>
    );
}

export default function Lineup({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const visible = state.lineup !== null;
    const formationAvailable = Boolean(state.lineup?.home.formation_ready && state.lineup?.away.formation_ready);
    const requested = state.lineup_settings.presentation;
    const [phase, setPhase] = useState<'list' | 'formation'>(requested === 'formation' && formationAvailable ? 'formation' : 'list');

    useEffect(() => {
        const first = requested === 'formation' && formationAvailable ? 'formation' : 'list';
        setPhase(first);

        if (!visible || requested !== 'sequence' || !formationAvailable) return;
        const timer = window.setTimeout(() => setPhase('formation'), state.lineup_settings.list_seconds * 1000);

        return () => window.clearTimeout(timer);
    }, [formationAvailable, requested, state.game?.id, state.lineup_settings.list_seconds, visible]);

    const transition = state.appearance.motion === 'none' ? 'duration-0' : state.appearance.motion === 'subtle' ? 'duration-300' : 'duration-700';
    const common = `absolute inset-0 flex items-center justify-center gap-8 transition-[opacity,transform] ${transition}`;

    return (
        <div className="fixed inset-0 overflow-hidden font-sans transition-opacity duration-500" style={{ ...overlayStyle(state.appearance), color: state.appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 2px rgba(0,0,0,.55)' }} data-testid="overlay-lineup" data-visible={visible} data-phase={phase}>
            <Head title="Escalação" />
            {state.lineup && state.game && (
                <div className="absolute inset-0" style={{ transform: `scale(${state.appearance.scale})` }}>
                    <div className={`${common} ${phase === 'list' ? 'translate-y-0 opacity-100' : '-translate-y-8 opacity-0 pointer-events-none'}`}>
                        <div className={`flex gap-8 ${phase === 'list' ? overlayMotion(state.appearance.motion) : ''}`}>
                            <ListColumn side={state.lineup.home} background={state.appearance.background_color} accent={state.appearance.accent_color} />
                            <ListColumn side={state.lineup.away} background={state.appearance.background_color} accent={state.appearance.accent_color} />
                        </div>
                    </div>
                    <div className={`${common} ${phase === 'formation' ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0 pointer-events-none'}`}>
                        <div className={`flex gap-8 ${phase === 'formation' ? overlayMotion(state.appearance.motion) : ''}`}>
                            <TacticalSide side={state.lineup.home} sport={state.game.sport} background={state.appearance.background_color} accent={state.appearance.accent_color} />
                            <TacticalSide side={state.lineup.away} sport={state.game.sport} background={state.appearance.background_color} accent={state.appearance.accent_color} />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
