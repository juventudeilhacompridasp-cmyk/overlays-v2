import { Individual } from '@/lib/overlayLineup';
import { LineupSide, OverlayAppearance, OverlayState } from '@/lib/overlay';
import { nextSponsorIndex, overlayMotion } from '@/lib/overlayVisual';
import { useEffect, useState } from 'react';

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
export function SponsorBanner({ sponsors, appearance }: { sponsors: OverlayState['sponsors']; appearance: OverlayAppearance }) {
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
        <div key={current.logo_url} className={`flex items-center gap-6 px-8 py-3 ${overlayMotion(appearance.motion === 'dynamic' ? 'subtle' : appearance.motion)}`} style={{ background: `linear-gradient(90deg, ${appearance.accent_color}, ${appearance.accent_color}cc)` }} data-testid="lineup-sponsor">
            <span className="grid h-20 w-32 shrink-0 place-items-center"><img src={current.logo_url} alt="" className="max-h-full max-w-full object-contain" /></span>
            <span className="text-3xl font-black leading-tight text-slate-950">{current.name}</span>
        </div>
    );
}

/** Um jogador por vez: foto grande, camisa e nome (a "apresentação individual" da transmissão). */
export function IndividualCard({ side, player, appearance, sponsors, showSponsor }: { side: LineupSide; player: Individual; appearance: OverlayAppearance; sponsors: OverlayState['sponsors']; showSponsor: boolean }) {
    const color = side.color ?? appearance.accent_color;
    const photo = player.photo_large_url ?? player.photo_url;
    const studio = appearance.preset === 'studio';

    return (
        <section key={`${player.shirt}-${player.name}`} className={`relative w-[68rem] overflow-hidden border border-white/10 shadow-2xl ${studio ? 'rounded-sm' : 'rounded-xl'} ${overlayMotion(appearance.motion)}`} style={{ background: appearance.background_color }} data-testid="lineup-individual">
            {studio && <span className="absolute inset-x-0 top-0 z-10 h-[3px]" style={{ backgroundColor: appearance.accent_color }} />}
            <header className="flex items-center gap-4 px-5 py-3">
                <Crest side={side} />
                <span className="truncate text-4xl font-black uppercase tracking-wide">{side.name}</span>
            </header>
            <div className="relative grid h-[19rem] grid-cols-[22rem_10rem_1fr]">
                <span className="absolute right-0 top-0 h-1 w-1/2" style={{ backgroundColor: appearance.accent_color }} />
                <div className="relative grid place-items-center overflow-hidden" style={{ background: `linear-gradient(180deg, ${color}66, ${color}22), ${appearance.background_color}` }}>
                    {photo ? <img src={photo} alt="" className="absolute inset-0 h-full w-full object-cover object-top" /> : <Silhouette />}
                </div>
                <div className="grid place-items-center text-[8rem] font-black leading-none tabular-nums" style={{ backgroundColor: color }}>{player.kind === 'coach' ? '' : pad(player.shirt)}</div>
                <div className="flex min-w-0 flex-col justify-center gap-3 px-10" style={{ background: `linear-gradient(90deg, ${appearance.background_color}, ${color}33)` }}>
                    {(player.position ?? player.position_label) && (
                        <span className="w-fit px-3 py-1 text-xl font-black uppercase tracking-[0.2em]" style={{ backgroundColor: color }}>{player.position ?? player.position_label}</span>
                    )}
                    <span className="truncate text-7xl font-black uppercase leading-none">{player.name}</span>
                    {player.captain && <span className="text-xl font-black uppercase tracking-widest text-amber-300">Capitão</span>}
                </div>
            </div>
            {showSponsor && <SponsorBanner sponsors={sponsors} appearance={appearance} />}
        </section>
    );
}

/** Grade de cartões da equipe: titulares e comissão (e reservas, se o escopo pedir). */
export function CardsGrid({ side, players, appearance, sponsors, showSponsor }: { side: LineupSide; players: Individual[]; appearance: OverlayAppearance; sponsors: OverlayState['sponsors']; showSponsor: boolean }) {
    const color = side.color ?? appearance.accent_color;
    const studio = appearance.preset === 'studio';
    const athletes = players.filter((player) => player.kind === 'player');
    const coach = players.find((player) => player.kind === 'coach');

    return (
        <section className={`relative w-[68rem] overflow-hidden border border-white/10 shadow-2xl ${studio ? 'rounded-sm' : 'rounded-xl'} ${overlayMotion(appearance.motion)}`} style={{ background: appearance.background_color }} data-testid="lineup-cards">
            {studio && <span className="absolute inset-x-0 top-0 z-10 h-[3px]" style={{ backgroundColor: appearance.accent_color }} />}
            <header className="flex items-center gap-4 px-5 py-3">
                <Crest side={side} />
                <span className="flex-1 truncate text-4xl font-black uppercase tracking-wide">{side.name}</span>
                <span className="border-l-4 pl-3 text-sm font-black uppercase tracking-[0.2em]" style={{ borderColor: appearance.accent_color, color: appearance.accent_color }}>Titulares + comissão</span>
            </header>
            <div className="grid grid-cols-6 gap-3 px-5 pb-4">
                {athletes.map((player, index) => (
                    <figure key={`${player.shirt}-${player.name}-${index}`} className={`overflow-hidden border border-white/10 ${studio ? 'rounded-sm' : 'rounded-lg'}`} style={{ background: studio ? appearance.background_color : `linear-gradient(180deg, ${color}44, ${appearance.background_color})` }}>
                        <div className="relative grid aspect-[4/5] place-items-center overflow-hidden">
                            {player.photo_large_url ?? player.photo_url ? <img src={(player.photo_large_url ?? player.photo_url) as string} alt="" className="absolute inset-0 h-full w-full object-cover object-top" /> : <Silhouette />}
                            <span className="absolute bottom-1 left-1 px-2 py-0.5 text-2xl font-black tabular-nums" style={{ backgroundColor: color }}>{pad(player.shirt)}</span>
                        </div>
                        <figcaption className="px-2 py-1.5">
                            <span className="block truncate text-lg font-black uppercase">{player.name}</span>
                            {(player.position ?? player.position_label) && <span className="mt-0.5 inline-block px-1.5 text-xs font-black uppercase tracking-widest" style={{ backgroundColor: color }}>{player.position ?? player.position_label}</span>}
                        </figcaption>
                    </figure>
                ))}
            </div>
            {coach && (
                <div className="flex justify-end px-5 pb-4">
                    <div className="flex w-72 items-center gap-3 border px-3 py-2" style={{ borderColor: appearance.accent_color, background: `${color}22` }}>
                        <span className="grid h-10 w-10 place-items-center bg-white/10"><Silhouette /></span>
                        <span className="min-w-0"><span className="block text-xs font-black uppercase tracking-widest text-white/60">Técnico</span><span className="block truncate text-xl font-black uppercase">{coach.name}</span></span>
                    </div>
                </div>
            )}
            {showSponsor && <SponsorBanner sponsors={sponsors} appearance={appearance} />}
        </section>
    );
}
