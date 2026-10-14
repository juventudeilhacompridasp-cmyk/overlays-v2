import { formatClock, isInterval, periodLabel, shownSeconds, useGameClock } from '@/lib/clock';
import { Realtime } from '@/lib/echo';
import { OverlayState, OverlayTeam, useOverlay } from '@/lib/overlay';
import { overlayMotion, overlayOrigin, overlayPosition, overlayStyle, scoreboardFrame } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';

function Crest({ team, size }: { team: OverlayTeam; size: 'sm' | 'md' }) {
    if (!team.logo_url) return null;
    const box = size === 'sm' ? 'h-7 w-7 p-0.5' : 'h-11 w-11 p-1';

    return (
        <span className={`grid ${box} flex-none place-items-center rounded-full bg-white/95 shadow-inner`}>
            <img src={team.logo_url} alt="" className="h-full w-full object-contain" />
        </span>
    );
}

function Side({ team, align, wide = false }: { team: OverlayTeam; align: 'left' | 'right'; wide?: boolean }) {
    const label = (team.short_name || team.name).toUpperCase();

    return (
        <div className={`relative flex items-center gap-3 px-4 py-2 ${wide ? 'min-w-0 flex-1 gap-5 px-8 py-3' : 'min-w-[13rem]'}`}>
            <span className="absolute inset-x-0 bottom-0 h-1" style={{ backgroundColor: team.color ?? 'var(--overlay-accent)' }} />
            {align === 'left' && <Crest team={team} size="md" />}
            <span className={`flex-1 truncate font-black tracking-[0.08em] ${wide ? 'text-3xl' : 'max-w-[12rem] text-xl'} ${align === 'right' ? 'text-right' : ''}`}>{wide ? (team.name).toUpperCase() : label}</span>
            {align === 'right' && <Crest team={team} size="md" />}
        </div>
    );
}

/** Equipes em duas linhas (escudo, sigla, gols): placar estreito, bom para o canto da tela. */
function StackedTeams({ game, pulse }: { game: NonNullable<OverlayState['game']>; pulse: boolean }) {
    const rows = [
        { team: game.home!, score: game.home_score },
        { team: game.away!, score: game.away_score },
    ];

    return (
        <div className="flex min-w-[14rem] flex-col justify-center divide-y divide-white/10" data-testid="overlay-score">
            {rows.map(({ team, score }, index) => (
                <div key={index} className="relative flex items-center gap-3 px-4 py-2">
                    <span className="absolute inset-y-1 left-0 w-1 rounded-r" style={{ backgroundColor: team.color ?? 'var(--overlay-accent)' }} />
                    <Crest team={team} size="sm" />
                    <span className="flex-1 truncate text-lg font-black tracking-[0.08em]">{(team.short_name || team.name).toUpperCase()}</span>
                    <span key={score} className={`min-w-[2rem] text-right text-3xl font-black tabular-nums ${pulse ? 'overlay-score-pulse' : ''}`}>{score}</span>
                </div>
            ))}
        </div>
    );
}

export default function Scoreboard({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
    const game = state.game;
    const elapsed = useGameClock(game?.clock ?? { state: 'not_started', period: null, base_seconds: 0, running_since_ms: null }, skew);
    const seconds = game ? shownSeconds(game.clock, elapsed) : elapsed;

    // Comando de ocultar: o placar sai na hora, sem animação (fiel ao clique do operador).
    if (!game || !game.home || !game.away || !state.layers.scoreboard) {
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
    const layout = appearance.layout;
    const hasMotion = appearance.motion !== 'none';
    const frame = scoreboardFrame(appearance);
    const clockStyle = appearance.preset === 'minimal' ? { color: appearance.accent_color } : { background: `linear-gradient(145deg, ${appearance.accent_color}, ${appearance.accent_color}cc)` };

    return (
        <div className={`fixed inset-0 flex p-8 font-sans ${overlayPosition(appearance.position)}`} style={{ ...overlayStyle(appearance), color: appearance.text_color, textShadow: '0 1px 2px rgba(0,0,0,.65)' }}>
            <Head title="Placar" />
            <div className={layout === 'wide' ? 'w-full' : undefined} style={{ transform: `scale(${appearance.scale})`, transformOrigin: layout === 'wide' ? 'center' : overlayOrigin(appearance.position) }}>
                <div className={overlayMotion(appearance.motion)}>
                    <div className={frame.className} style={frame.style} data-testid="overlay-scoreboard" data-preset={appearance.preset} data-layout={layout} data-motion={appearance.motion}>
                        {appearance.preset === 'arena' && <div className="absolute inset-x-0 top-0 h-0.5" style={{ background: `linear-gradient(90deg, transparent, ${appearance.accent_color}, transparent)` }} />}
                        {layout === 'stacked' && <StackedTeams game={game} pulse={hasMotion} />}
                        {layout === 'compact' && (
                            <div key={`${game.home_score}-${game.away_score}`} className={`flex items-center gap-2 px-4 py-1.5 ${hasMotion ? 'overlay-score-pulse' : ''}`} data-testid="overlay-score">
                                <Crest team={game.home} size="sm" />
                                <span className="text-sm font-black tracking-[0.08em]">{(game.home.short_name || game.home.name).slice(0, 3).toUpperCase()}</span>
                                <span className="text-2xl font-black tabular-nums">{game.home_score}</span>
                                <span className="text-sm text-white/40">×</span>
                                <span className="text-2xl font-black tabular-nums">{game.away_score}</span>
                                <span className="text-sm font-black tracking-[0.08em]">{(game.away.short_name || game.away.name).slice(0, 3).toUpperCase()}</span>
                                <Crest team={game.away} size="sm" />
                            </div>
                        )}
                        {(layout === 'bar' || layout === 'wide') && (
                            <>
                                <Side team={game.home} align="left" wide={layout === 'wide'} />
                                <div key={`${game.home_score}-${game.away_score}`} className={`flex items-center gap-3 border-x border-white/10 bg-black/30 font-black tabular-nums ${layout === 'wide' ? 'px-12 text-6xl' : 'px-6 text-4xl'} ${hasMotion ? 'overlay-score-pulse' : ''}`} data-testid="overlay-score">
                                    <span>{game.home_score}</span>
                                    <span className="text-xl text-white/35">×</span>
                                    <span>{game.away_score}</span>
                                </div>
                                <Side team={game.away} align="right" wide={layout === 'wide'} />
                            </>
                        )}
                        <div className={`flex flex-col items-center justify-center text-center ${layout === 'compact' ? 'min-w-[5.5rem] px-3 py-0.5' : layout === 'wide' ? 'min-w-[12rem] px-8 py-1' : 'min-w-[8.5rem] px-5 py-1'}`} style={clockStyle} data-testid="overlay-clock">
                            <span className={`${layout === 'compact' ? 'text-base' : layout === 'wide' ? 'text-3xl' : 'text-xl'} font-black tabular-nums`}>{clock}</span>
                            {layout !== 'compact' && sets && currentSet && !game.finished && <span className="text-[10px] font-bold uppercase tracking-[0.18em]">Set {sets.length}</span>}
                            {layout !== 'compact' && !sets && (game.clock.state === 'running' || game.clock.state === 'paused') && (
                                <span className="text-[10px] font-bold uppercase tracking-[0.16em]">
                                    {periodLabel(game.clock.period, game.sport)}
                                    {game.clock.state === 'paused' && ' · parado'}
                                </span>
                            )}
                        </div>
                    </div>
                    {sets && sets.some((set) => set.finished) && (
                        <div className="mt-1 inline-flex items-center gap-3 rounded-lg border border-white/10 px-3 py-1 text-sm tabular-nums shadow-lg" style={{ backgroundColor: `${appearance.background_color}e6` }} data-testid="overlay-sets">
                            {sets.filter((set) => set.finished).map((set, index) => (
                                <span key={index}>{set.home}-{set.away}</span>
                            ))}
                        </div>
                    )}
                    {game.fouls && (
                        <div className="mt-1 inline-flex items-center gap-3 rounded-lg border border-white/10 px-3 py-1 text-sm shadow-lg" style={{ backgroundColor: `${appearance.background_color}e6` }} data-testid="overlay-fouls">
                            <span className="text-white/60">FALTAS</span>
                            <span className={game.fouls.home >= game.fouls.limit ? 'font-bold text-amber-300' : ''}>{game.fouls.home}</span>
                            <span className="text-white/30">×</span>
                            <span className={game.fouls.away >= game.fouls.limit ? 'font-bold text-amber-300' : ''}>{game.fouls.away}</span>
                        </div>
                    )}
                    {game.home_penalties !== null && game.away_penalties !== null && (
                        <div className="mt-1 inline-block rounded-lg border border-white/10 px-3 py-1 text-sm shadow-lg" style={{ backgroundColor: `${appearance.background_color}e6` }}>
                            Pênaltis: {game.home_penalties} x {game.away_penalties}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
