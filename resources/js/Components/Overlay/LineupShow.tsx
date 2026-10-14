import { AthletePhoto, FormationPitch } from '@/Components/Overlay/Formation';
import { enterMotion, LineupLook, lineupSkin } from '@/lib/lineupSkin';
import { Individual } from '@/lib/overlayLineup';
import { LineupSide, OverlayAppearance, OverlayState } from '@/lib/overlay';
import { nextSponsorIndex, overlayMotion } from '@/lib/overlayVisual';
import { CSSProperties, useEffect, useState } from 'react';

/** Visual escolhido para as apresentações por equipe (conceito, estilo, animação, opacidade, cantos e texto). */
export function lineupLook(settings: OverlayState['lineup_settings']): LineupLook {
    return { layout: settings.layout ?? 'classic', style: settings.style ?? 'editorial', animation: settings.animation ?? 'slide', surface: settings.surface ?? 96, radius: settings.radius ?? null, text_scale: settings.text_scale ?? 100 };
}

const zoomOf = (look: LineupLook): CSSProperties | undefined => (look.text_scale === 100 ? undefined : { zoom: look.text_scale / 100 });

const pad = (shirt: number | null) => (shirt === null ? '' : String(shirt).padStart(2, '0'));

function Silhouette() {
    return (
        <svg viewBox="0 0 100 100" className="h-3/5 w-3/5 text-white/25" fill="currentColor" aria-hidden="true">
            <circle cx="50" cy="34" r="18" />
            <path d="M14 96c0-22 16-36 36-36s36 14 36 36z" />
        </svg>
    );
}

function Crest({ side }: { side: LineupSide }) {
    return (
        <span className="grid h-14 w-14 shrink-0 place-items-center bg-white p-1.5 shadow">
            {side.logo_url ? <img src={side.logo_url} alt="" className="h-full w-full object-contain" /> : <span className="text-sm font-black text-slate-700">{side.name.slice(0, 3).toUpperCase()}</span>}
        </span>
    );
}

/** Faixa de patrocínio no rodapé da escalação: um logotipo por vez, na cadência da rotação de patrocinadores. */
export function SponsorBanner({ sponsors, appearance, size = 100 }: { sponsors: OverlayState['sponsors']; appearance: OverlayAppearance; size?: number }) {
    const [index, setIndex] = useState(0);
    const count = sponsors.items.length;

    useEffect(() => {
        if (count < 2) return;
        const timer = window.setInterval(() => setIndex((current) => nextSponsorIndex(current, count)), sponsors.seconds * 1000);

        return () => window.clearInterval(timer);
    }, [count, sponsors.seconds]);

    if (count === 0) return null;
    const current = sponsors.items[index % count];

    return (
        <div key={current.logo_url} className={`flex items-center gap-6 px-8 py-3 ${overlayMotion(appearance.motion === 'dynamic' ? 'subtle' : appearance.motion)}`} style={{ background: `linear-gradient(90deg, ${appearance.accent_color}, ${appearance.accent_color}cc)`, ...(size === 100 ? {} : { zoom: size / 100 }) }} data-testid="lineup-sponsor">
            <span className="grid h-20 w-32 shrink-0 place-items-center"><img src={current.logo_url} alt="" className="max-h-full max-w-full object-contain" /></span>
            <span className="text-3xl font-black leading-tight text-slate-950">{current.name}</span>
        </div>
    );
}

/** Um jogador por vez: foto grande, camisa e nome (a "apresentação individual" da transmissão). */
export function IndividualCard({ side, player, appearance, sponsors, showSponsor, look, sponsorSize = 100, entrance = true }: { side: LineupSide; player: Individual; appearance: OverlayAppearance; sponsors: OverlayState['sponsors']; showSponsor: boolean; look: LineupLook; sponsorSize?: number; entrance?: boolean }) {
    const color = side.color ?? appearance.accent_color;
    const photo = player.photo_large_url ?? player.photo_url;
    const skin = lineupSkin(look, appearance.background_color, appearance.accent_color);
    const enter = enterMotion(look.animation, 0);
    const light = skin.ink !== null;

    return (
        <section key={`${player.shirt}-${player.name}`} className={`w-[68rem] ${skin.panel.className} ${entrance ? enter.className : ''} ${overlayMotion(appearance.motion)}`} style={{ ...skin.panel.style, ...zoomOf(look) }} data-testid="lineup-individual" data-layout={look.layout}>
            <header className="flex items-center gap-4 px-5 py-3">
                <Crest side={side} />
                <span className="truncate text-4xl font-black uppercase tracking-wide">{side.name}</span>
            </header>
            <div className="relative grid h-[19rem] grid-cols-[22rem_10rem_1fr]">
                <span className="absolute right-0 top-0 h-1 w-1/2" style={{ backgroundColor: appearance.accent_color }} />
                <div className="relative grid place-items-center overflow-hidden" style={{ background: light ? '#e7e0cf' : `linear-gradient(180deg, ${color}66, ${color}22), ${appearance.background_color}` }}>
                    {photo ? <img src={photo} alt="" className="absolute inset-0 h-full w-full object-cover object-top" /> : <Silhouette />}
                </div>
                <div className="grid place-items-center text-[8rem] font-black leading-none tabular-nums" style={{ backgroundColor: look.layout === 'stadium' ? '#0b0b0b' : light ? '#7f1d1d' : color, color: look.layout === 'stadium' ? '#fcd34d' : light ? '#ffffff' : undefined, textShadow: look.layout === 'stadium' ? '0 0 22px rgba(252,211,77,.9)' : undefined }}>{player.kind === 'coach' ? '' : pad(player.shirt)}</div>
                <div className="flex min-w-0 flex-col justify-center gap-3 px-10" style={{ background: light ? 'transparent' : `linear-gradient(90deg, ${appearance.background_color}, ${color}33)` }}>
                    {(player.position ?? player.position_label) && (
                        <span className="w-fit px-3 py-1 text-xl font-black uppercase tracking-[0.2em]" style={{ backgroundColor: light ? '#7f1d1d' : color, color: light ? '#ffffff' : undefined }}>{player.position ?? player.position_label}</span>
                    )}
                    <span className={`truncate text-7xl font-black uppercase leading-none ${look.layout === 'matchday' ? 'font-serif' : ''}`}>{player.name}</span>
                    {player.captain && <span className="text-xl font-black uppercase tracking-widest text-amber-500">Capitão</span>}
                </div>
            </div>
            {showSponsor && <SponsorBanner sponsors={sponsors} appearance={appearance} size={sponsorSize} />}
        </section>
    );
}

/** Grade de cartões da equipe: titulares e comissão (e reservas, se o escopo pedir), no conceito visual escolhido. */
export function CardsGrid({ side, players, appearance, sponsors, showSponsor, look, sponsorSize = 100, entrance = true }: { side: LineupSide; players: Individual[]; appearance: OverlayAppearance; sponsors: OverlayState['sponsors']; showSponsor: boolean; look: LineupLook; sponsorSize?: number; entrance?: boolean }) {
    const color = side.color ?? appearance.accent_color;
    const athletes = players.filter((player) => player.kind === 'player');
    const coach = players.find((player) => player.kind === 'coach');
    const skin = lineupSkin(look, appearance.background_color, appearance.accent_color);

    return (
        <section className={`${skin.width} ${skin.panel.className} ${overlayMotion(appearance.motion)}`} style={{ ...skin.panel.style, ...zoomOf(look) }} data-testid="lineup-cards" data-layout={look.layout} data-animation={look.animation}>
            <header className="flex items-center gap-4 px-5 py-3">
                <Crest side={side} />
                <span className={`flex-1 truncate font-black uppercase tracking-wide ${skin.list ? 'text-2xl' : 'text-4xl'}`}>{side.name}</span>
                {!skin.list && <span className="border-l-4 pl-3 text-sm font-black uppercase tracking-[0.2em]" style={{ borderColor: appearance.accent_color, color: skin.ink ?? appearance.accent_color }}>Titulares + comissão</span>}
            </header>
            <div className={`grid ${skin.grid} ${skin.list ? 'gap-0 pb-2' : 'gap-3 px-5 pb-4'}`}>
                {athletes.map((player, index) => {
                    const enter = enterMotion(look.animation, index);
                    const picture = player.photo_large_url ?? player.photo_url;

                    return (
                        <figure key={`${player.shirt}-${player.name}-${index}`} className={`${skin.card.className} ${entrance ? enter.className : ''}`} style={{ ...skin.card.style(color), ...(entrance ? { animationDelay: `${enter.delay}ms` } : {}) }}>
                            {skin.list ? (
                                <>
                                    <span className={skin.number.className} style={skin.number.style(color)}>{pad(player.shirt)}</span>
                                    <span className="min-w-0 flex-1"><span className={skin.name}>{player.name}</span>{(player.position ?? player.position_label) && <span className="block text-xs font-bold uppercase tracking-widest opacity-70">{player.position ?? player.position_label}</span>}</span>
                                </>
                            ) : (
                                <>
                                    <div className={`relative grid ${skin.photo} place-items-center overflow-hidden`}>
                                        {picture ? <img src={picture as string} alt="" className="absolute inset-0 h-full w-full object-cover object-top" /> : <Silhouette />}
                                        <span className={skin.number.className} style={skin.number.style(color)}>{pad(player.shirt)}</span>
                                    </div>
                                    <figcaption className={skin.vertical ? 'flex justify-center px-1 py-2' : 'px-2 py-1.5'}>
                                        <span className={skin.name}>{player.name}</span>
                                        {!skin.vertical && (player.position ?? player.position_label) && <span className="mt-0.5 inline-block px-1.5 text-xs font-black uppercase tracking-widest" style={{ backgroundColor: color, color: skin.ink ? '#ffffff' : undefined }}>{player.position ?? player.position_label}</span>}
                                    </figcaption>
                                </>
                            )}
                        </figure>
                    );
                })}
            </div>
            {coach && (
                <div className={`flex px-5 pb-4 ${skin.list ? 'justify-start' : 'justify-end'}`}>
                    <div className="flex w-72 items-center gap-3 border px-3 py-2" style={{ borderColor: appearance.accent_color, background: skin.ink ? 'transparent' : `${color}22` }}>
                        <span className="grid h-10 w-10 place-items-center bg-white/10"><Silhouette /></span>
                        <span className="min-w-0"><span className="block text-xs font-black uppercase tracking-widest opacity-60">Técnico</span><span className="block truncate text-xl font-black uppercase">{coach.name}</span></span>
                    </div>
                </div>
            )}
            {showSponsor && <SponsorBanner sponsors={sponsors} appearance={appearance} size={sponsorSize} />}
        </section>
    );
}

/** Esquema tático da equipe no mesmo conceito visual da grade e do jogador a jogador: campo/quadra à esquerda e a lista dos titulares ao lado. */
export function FormationCard({ side, sport, appearance, look, numberOnly, zoom, entrance = true }: { side: LineupSide; sport: string; appearance: OverlayAppearance; look: LineupLook; numberOnly: boolean; zoom: number; entrance?: boolean }) {
    const color = side.color ?? appearance.accent_color;
    const skin = lineupSkin(look, appearance.background_color, appearance.accent_color);
    const enter = enterMotion(look.animation, 0);
    const court = sport === 'basketball' || sport === 'volleyball';
    const light = skin.ink !== null;

    return (
        <section className={`w-[68rem] ${skin.panel.className} ${entrance ? enter.className : ''} ${overlayMotion(appearance.motion)}`} style={{ ...skin.panel.style, ...zoomOf(look) }} data-testid="lineup-formation" data-layout={look.layout}>
            <header className="flex items-center gap-4 px-5 py-3">
                <Crest side={side} />
                <span className="flex-1 truncate text-4xl font-black uppercase tracking-wide">{side.name}</span>
                {side.formation && <span className="border-l-4 pl-3 text-3xl font-black tabular-nums" style={{ borderColor: appearance.accent_color, color: skin.ink ?? appearance.accent_color }} data-testid="lineup-formation-name">{side.formation}</span>}
            </header>
            <div className="grid grid-cols-[auto_1fr] items-center gap-6 px-5 pb-5">
                <FormationPitch side={side} sport={sport} accent={appearance.accent_color} numberOnly={numberOnly} zoom={zoom} className={court ? 'h-[26rem] w-[34rem]' : 'h-[36rem] w-[27rem]'} />
                <ol className="min-w-0">
                    {side.starters.map((player, index) => (
                        <li key={`${player.shirt}-${player.name}-${index}`} className={`flex min-w-0 items-center gap-3 border-b py-1.5 ${light ? 'border-stone-300' : 'border-white/10'}`}>
                            <span className="grid h-9 w-11 shrink-0 place-items-center text-xl font-black tabular-nums" style={{ backgroundColor: light ? '#7f1d1d' : color, color: light ? '#ffffff' : undefined }}>{pad(player.shirt)}</span>
                            <AthletePhoto player={player} size="small" numberOnly={numberOnly} />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-lg font-black uppercase leading-tight">{player.name}{player.captain && <span className="ml-1 text-sm text-amber-400">C</span>}</span>
                                {(player.position ?? player.position_label) && <span className="block text-xs font-bold uppercase tracking-widest opacity-70">{player.position_label ?? player.position}</span>}
                            </span>
                        </li>
                    ))}
                    {side.coach && <li className="flex items-center gap-3 pt-2"><span className="text-xs font-black uppercase tracking-widest opacity-60">Técnico</span><span className="truncate text-lg font-black uppercase">{side.coach}</span></li>}
                </ol>
            </div>
        </section>
    );
}
