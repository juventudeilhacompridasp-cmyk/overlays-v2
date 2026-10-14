import { LineupSide } from '@/lib/overlay';

export type Player = LineupSide['starters'][number];

export function AthletePhoto({ player, size = 'large', numberOnly = false, zoom = 100 }: { player: Pick<Player, 'photo_url' | 'shirt'>; size?: 'small' | 'large'; numberOnly?: boolean; zoom?: number }) {
    const dimension = size === 'large' ? 'h-[4.6rem] w-[4.6rem]' : 'h-11 w-11';

    return (
        <span className={`grid shrink-0 place-items-center overflow-hidden rounded-full border-2 border-white/80 bg-slate-900 font-black shadow-lg ${dimension}`} style={zoom === 100 ? undefined : { zoom: zoom / 100 }}>
            {player.photo_url && !numberOnly ? <img src={player.photo_url} alt="" className="h-full w-full object-cover" /> : <span className={size === 'large' ? 'text-2xl' : 'text-base'}>{player.shirt ?? '—'}</span>}
        </span>
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

function TacticalPlayer({ player, accent, numberOnly, zoom }: { player: Player; accent: string; numberOnly: boolean; zoom: number }) {
    if (player.x === null || player.y === null) return null;
    const x = Math.min(90, Math.max(10, player.x));
    const y = Math.min(84, Math.max(12, player.y));

    return (
        <div className="absolute flex -translate-x-1/2 -translate-y-1/2 flex-col items-center" style={{ left: `${x}%`, top: `${y}%` }}>
            <div className="relative">
                <AthletePhoto player={player} numberOnly={numberOnly} zoom={zoom} />
                {player.captain && <span className="absolute -right-1 -top-1 grid h-6 w-6 place-items-center rounded-full bg-amber-300 text-xs font-black text-slate-900 shadow">C</span>}
            </div>
            <span className="mt-1 max-w-40 truncate rounded-full border border-white/20 bg-slate-950/90 px-3 py-1 text-sm font-black shadow-lg">{player.name}</span>
            {player.position && <span className="mt-0.5 text-[10px] font-black uppercase tracking-[0.18em]" style={{ color: accent }}>{player.position}</span>}
        </div>
    );
}

/** Campo ou quadra com as posições do esquema tático da equipe (x/y de `Formations`), sem moldura. */
export function FormationPitch({ side, sport, accent, numberOnly, zoom, className }: { side: LineupSide; sport: string; accent: string; numberOnly: boolean; zoom: number; className?: string }) {
    const court = sport === 'basketball' || sport === 'volleyball';

    return (
        <div className={`relative overflow-hidden rounded-2xl border-2 border-white/45 ${className ?? (court ? 'h-[31rem] w-[36rem]' : 'h-[43rem] w-[31rem]')}`} style={{ background: sport === 'basketball' ? 'linear-gradient(90deg,#a85f2d,#c77a3b,#a85f2d)' : sport === 'volleyball' ? 'linear-gradient(90deg,#d1943d 0 50%,#3b82a0 50%)' : 'linear-gradient(90deg,#087443,#0a8150,#087443)' }}>
            <div className="absolute inset-3 border-2 border-white/35" />
            <SurfaceMarkings sport={sport} />
            {side.starters.map((player, index) => <TacticalPlayer key={`${player.shirt}-${player.name}-${index}`} player={player} accent={accent} numberOnly={numberOnly} zoom={zoom} />)}
        </div>
    );
}
