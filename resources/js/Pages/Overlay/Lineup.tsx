import { AthletePhoto, FormationPitch } from '@/Components/Overlay/Formation';
import { CardsGrid, FormationCard, IndividualCard, lineupLook, SponsorBanner } from '@/Components/Overlay/LineupShow';
import { LineupStage } from '@/Components/Overlay/LineupStage';
import { lineupSkin } from '@/lib/lineupSkin';
import { stageKey } from '@/lib/lineupStage';
import { Realtime } from '@/lib/echo';
import { LineupSide, OverlayState, useOverlay } from '@/lib/overlay';
import { currentStep, individuals, nextChangeAt, stepContent } from '@/lib/overlayLineup';
import { overlayMotion, overlayOrigin, overlayPosition, overlayStyle } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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

function TacticalSide({ side, sport, background, accent, numberOnly, zoom }: { side: LineupSide; sport: string; background: string; accent: string; numberOnly: boolean; zoom: number }) {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/15 shadow-2xl" style={{ background: `linear-gradient(160deg, ${background}f7, ${background}e8)` }}>
            <TeamHeader side={side} accent={accent} />
            <div className="m-4"><FormationPitch side={side} sport={sport} accent={accent} numberOnly={numberOnly} zoom={zoom} /></div>
        </section>
    );
}

/** Apresentações por equipe: cartões, jogador a jogador, cartões + jogadores e a completa (cartões, esquema tático e jogadores). */
const TEAM_PRESENTATIONS = ['cards', 'individual', 'show', 'full'];

/**
 * Apresentação de uma equipe por vez. A etapa (grade, esquema tático ou jogador) sai do relógio do servidor (ou do passo manual)
 * e a troca entre etapas é animada pelo palco; a faixa de patrocinadores fica fixa por baixo e não reinicia a cada etapa.
 */
export function TeamShow({ state, skew }: { state: OverlayState; skew: number }) {
    const settings = state.lineup_settings;
    const side = state.lineup?.[settings.team];
    const [, tick] = useState(0);
    const players = side ? individuals(side, settings.individual_scope) : [];
    const formationReady = Boolean(side?.formation_ready);
    const serverNow = () => Date.now() - skew;

    // Automático: acorda exatamente quando a etapa muda (todas as telas abertas concordam porque o relógio é o do servidor).
    useEffect(() => {
        const at = nextChangeAt(settings, players.length, serverNow(), formationReady);
        if (at === null) return;
        const timer = window.setTimeout(() => tick((value) => value + 1), Math.max(50, at - serverNow() + 30));

        return () => window.clearTimeout(timer);
    }); // eslint-disable-line react-hooks/exhaustive-deps

    if (!side || !state.game) return null;

    const step = currentStep(settings, players.length, serverNow(), formationReady);
    const content = stepContent(settings, players.length, step, formationReady);
    const appearance = state.appearance;
    const look = lineupLook(settings);
    const skin = lineupSkin(look, appearance.background_color, appearance.accent_color);
    const free = typeof settings.x === 'number' && typeof settings.y === 'number';
    const sport = state.game.sport;
    const transition = settings.transition ?? 'slide';

    return (
        <div className={`absolute inset-0 ${free ? '' : `flex p-12 ${overlayPosition(appearance.position)}`}`} data-step={step} data-kind={content.kind} data-presentation={settings.presentation}>
            <div style={{ ...(free ? { position: 'absolute', left: `${settings.x}%`, top: `${settings.y}%` } : {}), transform: `scale(${appearance.scale})`, transformOrigin: free ? 'top left' : overlayOrigin(appearance.position) }}>
                <div className="flex flex-col gap-3">
                    <LineupStage id={stageKey(content, settings.team)} transition={transition} motion={appearance.motion}>
                        {(first) => content.kind === 'formation' ? (
                            <FormationCard side={side} sport={sport} appearance={appearance} look={look} numberOnly={settings.formation_mark === 'number'} zoom={settings.formation_photo_size ?? 100} entrance={first} />
                        ) : content.kind === 'individual' && players[content.index] ? (
                            <IndividualCard side={side} player={players[content.index]} appearance={appearance} sponsors={state.sponsors} showSponsor={false} look={look} entrance={first} />
                        ) : (
                            <CardsGrid side={side} players={players} appearance={appearance} sponsors={state.sponsors} showSponsor={false} look={look} entrance={first} />
                        )}
                    </LineupStage>
                    {settings.sponsor_banner && state.sponsors.items.length > 0 && (
                        <div className={`${skin.panel.className} ${overlayMotion(appearance.motion === 'dynamic' ? 'subtle' : appearance.motion)}`} style={{ ...skin.panel.style, background: 'transparent' }} data-testid="lineup-sponsor-bar">
                            <SponsorBanner sponsors={state.sponsors} appearance={appearance} size={settings.sponsor_size} />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Lineup({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state, skew } = useOverlay(token, initial, realtime);
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

    const appearance = state.appearance;
    const skin = lineupSkin(lineupLook(state.lineup_settings), appearance.background_color, appearance.accent_color);
    const numberOnly = state.lineup_settings.formation_mark === 'number';
    const zoom = state.lineup_settings.formation_photo_size ?? 100;

    return (
        <div className="fixed inset-0 overflow-hidden font-sans transition-opacity duration-500" style={{ ...overlayStyle(appearance), color: appearance.text_color, opacity: visible ? 1 : 0, textShadow: '0 1px 2px rgba(0,0,0,.55)' }} data-testid="overlay-lineup" data-visible={visible} data-phase={phase}>
            <Head title="Escalação" />
            {state.lineup && state.game && TEAM_PRESENTATIONS.includes(requested) && <TeamShow state={state} skew={skew} />}
            {state.lineup && state.game && !TEAM_PRESENTATIONS.includes(requested) && (
                <div className="absolute inset-0 flex items-center justify-center" style={{ transform: `scale(${appearance.scale})` }}>
                    <div className="flex flex-col gap-3">
                        <LineupStage id={phase} transition={state.lineup_settings.transition ?? 'slide'} motion={appearance.motion}>
                            {() => phase === 'list' ? (
                                <div className="flex gap-8">
                                    <ListColumn side={state.lineup!.home} background={appearance.background_color} accent={appearance.accent_color} />
                                    <ListColumn side={state.lineup!.away} background={appearance.background_color} accent={appearance.accent_color} />
                                </div>
                            ) : (
                                <div className="flex gap-8">
                                    <TacticalSide side={state.lineup!.home} sport={state.game!.sport} background={appearance.background_color} accent={appearance.accent_color} numberOnly={numberOnly} zoom={zoom} />
                                    <TacticalSide side={state.lineup!.away} sport={state.game!.sport} background={appearance.background_color} accent={appearance.accent_color} numberOnly={numberOnly} zoom={zoom} />
                                </div>
                            )}
                        </LineupStage>
                        {state.lineup_settings.sponsor_banner && state.sponsors.items.length > 0 && (
                            <div className={skin.panel.className} style={{ ...skin.panel.style, background: 'transparent' }} data-testid="lineup-sponsor-bar">
                                <SponsorBanner sponsors={state.sponsors} appearance={appearance} size={state.lineup_settings.sponsor_size} />
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
