import { formatClock, isInterval, periodLabel, shownSeconds, useGameClock } from '@/lib/clock';
import { Realtime } from '@/lib/echo';
import { OverlayState, OverlayTeam, useOverlay } from '@/lib/overlay';
import { overlayMotion, overlayPosition, overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';

function Side({ team, align, preset }: { team: OverlayTeam; align: 'left' | 'right'; preset: OverlayState['appearance']['preset'] }) {
    const label = (team.short_name || team.name).toUpperCase();
    const studio = preset === 'studio';
    const logo = team.logo_url ? <img src={team.logo_url} alt="" className={`${studio ? 'h-8 w-8' : 'h-9 w-9'} object-contain`} /> : null;

    return (
        <div className={`relative flex items-center gap-3 ${studio ? 'min-w-[11rem] px-4 py-2.5' : 'min-w-[13rem] px-4 py-2'}`}>
            <span className={`absolute ${studio ? 'inset-y-2 w-1 rounded-full' : 'inset-x-0 bottom-0 h-1'} ${studio && align === 'right' ? 'right-0' : 'left-0'}`} style={{ backgroundColor: team.color ?? 'var(--overlay-accent)' }} />
            {align === 'left' && logo && <span className={`grid place-items-center bg-white/95 p-1 shadow-inner ${studio ? 'h-10 w-10 rounded-md' : 'h-11 w-11 rounded-full'}`}>{logo}</span>}
            <span className={`flex-1 truncate font-black ${studio ? 'max-w-[10rem] text-lg tracking-[0.12em]' : 'max-w-[12rem] text-xl tracking-[0.08em]'} ${align === 'right' ? 'text-right' : ''}`}>{label}</span>
            {align === 'right' && logo && <span className={`grid place-items-center bg-white/95 p-1 shadow-inner ${studio ? 'h-10 w-10 rounded-md' : 'h-11 w-11 rounded-full'}`}>{logo}</span>}
        </div>
    );
}

export default function Scoreboard({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
    const game = state.game;
    const elapsed = useGameClock(game?.clock ?? { state: 'not_started', period: null, base_seconds: 0, running_since_ms: null }, skew);
    const seconds = game ? shownSeconds(game.clock, elapsed) : elapsed;

    if (!game || !game.home || !game.away) {
        return <Head title="Placar" />;
    }

    const sets = game.sets;
    const currentSet = sets && sets.length > 0 && !sets[sets.length - 1].finished ? sets[sets.length - 1] : null;

    let clock: string;
    if (sets) clock = game.finished ? 'FIM' : currentSet ? `${currentSet.home} - ${currentSet.away}` : game.status === 'scheduled' ? 'EM BREVE' : game.status_label.toUpperCase();
    else if (game.finished) clock = 'FIM';
    else if (game.clock.state === 'not_started') clock = game.status === 'scheduled' ? 'EM BREVE' : game.status_label.toUpperCase();
    else if (game.clock.state === 'stopped') clock = isInterval(game.clock.period, game.sport) ? 'INTERVALO' : 'FIM DO TEMPO';
    else clock = formatClock(seconds);

    const appearance = state.appearance;
    const studio = appearance.preset === 'studio';
    const hasMotion = appearance.motion !== 'none';

    return (
        <div className={`fixed inset-0 flex p-8 font-sans ${overlayPosition(appearance.position)}`} style={{ ...overlayStyle(appearance), color: appearance.text_color, textShadow: '0 1px 2px rgba(0,0,0,.65)' }}>
            <Head title="Placar" />
            <div style={{ transform: `scale(${appearance.scale})`, transformOrigin: appearance.position === 'top-right' ? 'top right' : appearance.position === 'top-center' ? 'top center' : 'top left' }}>
                <div className={overlayMotion(appearance.motion)}>
                    <div className={`relative inline-flex items-stretch overflow-hidden border border-white/15 shadow-2xl ${studio ? 'rounded-md' : 'rounded-2xl'}`} style={{ background: studio ? `linear-gradient(90deg, ${appearance.background_color}fa, ${appearance.background_color}e8)` : `linear-gradient(135deg, ${appearance.background_color}f2, ${appearance.background_color}d9)` }} data-testid="overlay-scoreboard" data-preset={appearance.preset} data-motion={appearance.motion}>
                        <div className={`absolute top-0 ${studio ? 'inset-x-0 h-1' : 'inset-x-0 h-0.5'}`} style={{ background: studio ? appearance.accent_color : `linear-gradient(90deg, transparent, ${appearance.accent_color}, transparent)` }} />
                        <Side team={game.home} align="left" preset={appearance.preset} />
                        <div key={`${game.home_score}-${game.away_score}`} className={`flex items-center border-x border-white/10 bg-black/30 font-black tabular-nums ${studio ? 'gap-2 px-5 text-4xl' : 'gap-3 px-6 text-4xl'} ${hasMotion ? 'overlay-score-pulse' : ''}`} data-testid="overlay-score">
                            <span>{game.home_score}</span>
                            <span className={`${studio ? 'text-lg' : 'text-xl'} text-white/35`}>{studio ? ':' : '×'}</span>
                            <span>{game.away_score}</span>
                        </div>
                        <Side team={game.away} align="right" preset={appearance.preset} />
                        <div className={`flex min-w-[8.5rem] flex-col items-center justify-center px-5 py-1 text-center ${studio ? 'border-l-4 bg-black/25' : ''}`} style={studio ? { borderColor: appearance.accent_color } : { background: `linear-gradient(145deg, ${appearance.accent_color}, ${appearance.accent_color}cc)` }} data-testid="overlay-clock">
                            <span className="text-xl font-black tabular-nums" style={studio ? { color: appearance.accent_color } : undefined}>{clock}</span>
                            {sets && currentSet && !game.finished && <span className="text-[10px] font-bold uppercase tracking-[0.18em]">Set {sets.length}</span>}
                            {!sets && (game.clock.state === 'running' || game.clock.state === 'paused') && (
                                <span className="text-[10px] font-bold uppercase tracking-[0.16em]">
                                    {periodLabel(game.clock.period, game.sport)}
                                    {game.clock.state === 'paused' && ' · parado'}
                                </span>
                            )}
                        </div>
                    </div>
                    {sets && sets.some((set) => set.finished) && (
                        <div className={`mt-1 inline-flex items-center gap-3 border border-white/10 px-3 py-1 text-sm tabular-nums shadow-lg ${studio ? 'rounded-sm border-t-2' : 'rounded-lg'}`} style={{ backgroundColor: `${appearance.background_color}e6`, borderTopColor: studio ? appearance.accent_color : undefined }} data-testid="overlay-sets">
                            {sets.filter((set) => set.finished).map((set, index) => (
                                <span key={index}>{set.home}-{set.away}</span>
                            ))}
                        </div>
                    )}
                    {game.fouls && (
                        <div className={`mt-1 inline-flex items-center gap-3 border border-white/10 px-3 py-1 text-sm shadow-lg ${studio ? 'rounded-sm border-t-2' : 'rounded-lg'}`} style={{ backgroundColor: `${appearance.background_color}e6`, borderTopColor: studio ? appearance.accent_color : undefined }} data-testid="overlay-fouls">
                            <span className="text-white/60">FALTAS</span>
                            <span className={game.fouls.home >= game.fouls.limit ? 'font-bold text-amber-300' : ''}>{game.fouls.home}</span>
                            <span className="text-white/30">×</span>
                            <span className={game.fouls.away >= game.fouls.limit ? 'font-bold text-amber-300' : ''}>{game.fouls.away}</span>
                        </div>
                    )}
                    {game.home_penalties !== null && game.away_penalties !== null && (
                        <div className={`mt-1 inline-block border border-white/10 px-3 py-1 text-sm shadow-lg ${studio ? 'rounded-sm border-t-2' : 'rounded-lg'}`} style={{ backgroundColor: `${appearance.background_color}e6`, borderTopColor: studio ? appearance.accent_color : undefined }}>
                            Pênaltis: {game.home_penalties} x {game.away_penalties}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
