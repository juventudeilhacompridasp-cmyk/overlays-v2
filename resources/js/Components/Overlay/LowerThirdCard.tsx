import { LowerThirdBannerData, LowerThirdStyle, inkOn, lowerThirdTone, scoreLine, shirtLabel, teamAccent } from '@/lib/lowerThird';
import { OverlayAppearance } from '@/lib/overlay';
import { CSSProperties, ReactNode } from 'react';

interface CardProps {
    banner: LowerThirdBannerData;
    appearance: OverlayAppearance;
}

const panel = (appearance: OverlayAppearance): CSSProperties => ({ background: `linear-gradient(135deg, ${appearance.background_color}f5, ${appearance.background_color}e0)` });

function Crest({ banner, size = 'h-12 w-12' }: { banner: LowerThirdBannerData; size?: string }) {
    return banner.team_logo ? (
        <span className={`grid ${size} flex-none place-items-center rounded-full bg-white/95 p-1 shadow-inner`}>
            <img src={banner.team_logo} alt="" className="h-full w-full object-contain" />
        </span>
    ) : null;
}

function Chip({ banner }: { banner: LowerThirdBannerData }) {
    const tone = lowerThirdTone(banner.kind);

    return (
        <span className="inline-flex items-center gap-2 rounded px-3 py-1 text-lg font-black uppercase tracking-[0.12em]" style={{ backgroundColor: tone.accent, color: tone.ink }}>
            <span aria-hidden="true">{tone.icon}</span>
            {banner.title}
            {banner.minute !== null && <span className="font-semibold opacity-80">{banner.minute}'</span>}
        </span>
    );
}

/** Nome em destaque: o jogador do lance, ou o texto livre da faixa. */
function mainText(banner: LowerThirdBannerData): string {
    return banner.kind === 'player' || banner.kind === 'custom' ? banner.title : banner.subtitle ?? banner.title;
}

function subText(banner: LowerThirdBannerData): string | null {
    if (banner.kind === 'player' || banner.kind === 'custom') return banner.subtitle;

    return banner.subtitle ? banner.title : null;
}

function Arena({ banner, appearance }: CardProps) {
    const tone = lowerThirdTone(banner.kind);

    return (
        <div className="inline-flex items-stretch overflow-hidden rounded-2xl border border-white/15 shadow-2xl" style={panel(appearance)}>
            <div className="relative flex items-center px-5 text-4xl" style={{ backgroundColor: tone.accent }}>
                <span className="absolute inset-x-0 top-0 h-0.5 bg-white/50" />
                {tone.icon}
            </div>
            <div className="px-6 py-3">
                <div className="flex items-baseline gap-3">
                    <span className="text-3xl font-black tracking-[0.06em]">{banner.title}</span>
                    {banner.minute !== null && <span className="text-xl text-white/60">{banner.minute}'</span>}
                </div>
                {banner.subtitle && <div className="text-2xl font-semibold">{banner.subtitle}</div>}
                {banner.detail && <div className="text-sm font-bold uppercase tracking-wider text-amber-300">{banner.detail}</div>}
                {banner.team && (
                    <div className="mt-1 inline-block rounded px-2 text-sm font-bold uppercase" style={{ backgroundColor: banner.color ?? '#6b7280' }}>
                        {banner.team}
                    </div>
                )}
                {scoreLine(banner) && <div className="mt-1 text-xl font-black tabular-nums text-white/90">{scoreLine(banner)}</div>}
            </div>
        </div>
    );
}

function Broadcast({ banner, appearance }: CardProps) {
    const accent = teamAccent(banner);
    const shirt = shirtLabel(banner);

    return (
        <div className="flex w-full items-stretch overflow-hidden rounded-lg shadow-2xl" style={panel(appearance)}>
            <div className="flex items-center gap-4 px-7" style={{ backgroundColor: accent, color: inkOn(accent) }}>
                {shirt ? <span className="text-5xl font-black tabular-nums">{shirt}</span> : <Crest banner={banner} />}
            </div>
            <div className="min-w-0 flex-1 px-7 py-3">
                <Chip banner={banner} />
                <div className="mt-1 truncate text-5xl font-black uppercase tracking-wide">{mainText(banner)}</div>
                <div className="flex items-center gap-3 text-xl font-semibold text-white/70">
                    {banner.team && <span>{banner.team}</span>}
                    {banner.detail && <span className="font-bold uppercase tracking-wider text-amber-300">{banner.detail}</span>}
                </div>
            </div>
            {scoreLine(banner) && <div className="flex items-center bg-black/40 px-8 text-4xl font-black tabular-nums">{scoreLine(banner)}</div>}
            <span className="w-2" style={{ backgroundColor: accent }} />
        </div>
    );
}

function Player({ banner, appearance }: CardProps) {
    const accent = teamAccent(banner);
    const shirt = shirtLabel(banner);
    const tone = lowerThirdTone(banner.kind);

    return (
        <div className="inline-flex items-center gap-5 overflow-hidden rounded-3xl border border-white/15 py-3 pl-3 pr-8 shadow-2xl" style={panel(appearance)}>
            <span className="grid h-28 w-28 flex-none place-items-center rounded-2xl text-6xl font-black tabular-nums shadow-inner" style={{ backgroundColor: accent, color: inkOn(accent) }}>
                {shirt ?? tone.icon}
            </span>
            <div>
                <Chip banner={banner} />
                <div className="mt-1 text-5xl font-black uppercase leading-none tracking-wide">{mainText(banner)}</div>
                <div className="mt-1 flex items-center gap-3 text-xl font-semibold text-white/75">
                    <Crest banner={banner} size="h-7 w-7" />
                    {[banner.player?.position, banner.team].filter(Boolean).join(' · ') || subText(banner)}
                </div>
                {banner.detail && <div className="mt-0.5 text-sm font-bold uppercase tracking-wider text-amber-300">{banner.detail}</div>}
                {scoreLine(banner) && <div className="mt-1 text-2xl font-black tabular-nums">{scoreLine(banner)}</div>}
            </div>
        </div>
    );
}

function Minimal({ banner, appearance }: CardProps) {
    return (
        <div className="inline-block" style={{ textShadow: '0 2px 12px rgba(0,0,0,.85)' }}>
            <div className="flex items-center gap-3 text-xl font-black uppercase tracking-[0.2em]" style={{ color: appearance.accent_color }}>
                <span aria-hidden="true">{lowerThirdTone(banner.kind).icon}</span>
                {banner.title}
                {banner.minute !== null && <span className="text-white/70">{banner.minute}'</span>}
            </div>
            <div className="text-6xl font-black uppercase leading-tight tracking-wide">{mainText(banner)}</div>
            <div className="mt-1 h-1.5 w-full rounded" style={{ backgroundColor: appearance.accent_color }} />
            <div className="mt-1 flex items-center gap-4 text-2xl font-semibold">
                {[subText(banner), banner.team].filter(Boolean).join(' · ')}
                {scoreLine(banner) && <span className="font-black tabular-nums">{scoreLine(banner)}</span>}
            </div>
            {banner.detail && <div className="text-base font-bold uppercase tracking-wider text-amber-300">{banner.detail}</div>}
        </div>
    );
}

function Slash({ banner, appearance }: CardProps) {
    const tone = lowerThirdTone(banner.kind);
    const accent = teamAccent(banner);
    const skew = { transform: 'skewX(-14deg)' } as CSSProperties;
    const unskew = { transform: 'skewX(14deg)' } as CSSProperties;

    return (
        <div className="inline-flex items-stretch shadow-2xl">
            <div className="flex items-center px-8" style={{ ...skew, backgroundColor: tone.accent, color: tone.ink }}>
                <span className="flex items-center gap-2 text-2xl font-black uppercase tracking-wider" style={unskew}>
                    <span aria-hidden="true" className="text-3xl">{tone.icon}</span>
                    {banner.title}
                    {banner.minute !== null && <span className="opacity-80">{banner.minute}'</span>}
                </span>
            </div>
            <div className="-ml-2 px-10 py-3" style={{ ...skew, background: appearance.background_color }}>
                <div style={unskew}>
                    <div className="text-5xl font-black uppercase tracking-wide">{mainText(banner)}</div>
                    <div className="flex items-center gap-4 text-xl font-semibold text-white/75">
                        {[subText(banner), banner.team].filter(Boolean).join(' · ')}
                        {scoreLine(banner) && <span className="font-black tabular-nums text-white">{scoreLine(banner)}</span>}
                    </div>
                    {banner.detail && <div className="text-sm font-bold uppercase tracking-wider text-amber-300">{banner.detail}</div>}
                </div>
            </div>
            <div className="-ml-2 w-5" style={{ ...skew, backgroundColor: accent }} />
        </div>
    );
}

function Flash({ banner, appearance }: CardProps) {
    const accent = teamAccent(banner);
    const shirt = shirtLabel(banner);

    return (
        <div className="flex w-full items-center gap-8 px-16 py-6 shadow-2xl" style={{ background: `linear-gradient(100deg, ${accent} 0%, ${accent}ee 38%, ${appearance.background_color}f2 38.2%, ${appearance.background_color}f2 100%)` }}>
            <div className="w-[34%] flex-none" style={{ color: inkOn(accent) }}>
                <div className="text-8xl font-black italic leading-none tracking-wide">{banner.title}</div>
                {banner.minute !== null && <div className="text-3xl font-bold opacity-80">{banner.minute}'</div>}
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-5">
                    {shirt && <span className="text-6xl font-black tabular-nums text-white/50">{shirt}</span>}
                    <span className="truncate text-7xl font-black uppercase tracking-wide">{mainText(banner)}</span>
                </div>
                <div className="flex items-center gap-4 text-3xl font-semibold text-white/80">
                    <Crest banner={banner} size="h-10 w-10" />
                    {[subText(banner), banner.team].filter(Boolean).join(' · ')}
                    {banner.detail && <span className="font-bold uppercase tracking-wider text-amber-300">{banner.detail}</span>}
                </div>
            </div>
            {scoreLine(banner) && <div className="flex-none text-6xl font-black tabular-nums">{scoreLine(banner)}</div>}
        </div>
    );
}

const CARDS: Record<LowerThirdStyle, (props: CardProps) => ReactNode> = {
    arena: Arena,
    broadcast: Broadcast,
    player: Player,
    minimal: Minimal,
    slash: Slash,
    flash: Flash,
};

/** Estilos que ocupam a largura da tela (os demais ficam no canto). */
export const WIDE_STYLES: LowerThirdStyle[] = ['broadcast', 'flash'];

export default function LowerThirdCard({ style, ...props }: CardProps & { style: LowerThirdStyle }) {
    const Card = CARDS[style] ?? Arena;

    return <>{Card(props)}</>;
}
