import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { nextSponsorIndex, overlayMotion, overlayOrigin, overlayPosition, overlayStyle, sponsorFrameClass } from '@/lib/overlayVisual';
import { animationMs, sponsorContent, sponsorFrame, SponsorStyle } from '@/lib/sponsorLook';
import { Head } from '@inertiajs/react';
import { CSSProperties, useEffect, useState } from 'react';

/** Rotação de logotipos dos patrocinadores: um por vez, no formato, estilo e animação escolhidos. */
export default function Sponsors({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const { items, seconds, enabled } = state.sponsors;
    const [index, setIndex] = useState(0);
    const count = items.length;

    useEffect(() => {
        if (count < 2) return;
        const timer = window.setInterval(() => setIndex((current) => nextSponsorIndex(current, count)), seconds * 1000);

        return () => window.clearInterval(timer);
    }, [count, seconds]);

    const appearance = state.appearance;
    const look = state.sponsors;
    const current = enabled && count > 0 ? items[index % count] : null;
    const content = current ? sponsorContent(current, look.format ?? 'logo') : null;
    const style: SponsorStyle = look.style ?? 'boxed';
    const free = typeof look.x === 'number' && typeof look.y === 'number';
    const animation = look.animation ?? 'fade';
    const textZoom = (look.text_scale ?? 100) / 100;
    const scale = appearance.scale * ((look.scale ?? 100) / 100);

    return (
        <div className={`fixed inset-0 font-sans ${free ? '' : `flex p-8 ${overlayPosition(appearance.position)}`}`} style={{ ...overlayStyle(appearance), color: appearance.text_color }} data-testid="overlay-sponsors" data-visible={current !== null}>
            <Head title="Patrocinadores" />
            {current && content && (
                <div style={{ ...(free ? { position: 'absolute', left: `${look.x}%`, top: `${look.y}%` } : {}), transform: `scale(${scale})`, transformOrigin: free ? 'top left' : overlayOrigin(appearance.position) }}>
                    <div key={current.logo_url} className={`${overlayMotion(appearance.motion === 'dynamic' ? 'subtle' : appearance.motion)} ${appearance.motion === 'none' ? '' : `sponsor-in-${animation}`}`} style={{ ['--sponsor-ms' as string]: `${animationMs(look.animation_speed ?? 100)}ms` } as CSSProperties}>
                        <div className={`flex flex-col items-center gap-1 ${sponsorFrame(style)} ${style === 'boxed' ? sponsorFrameClass(appearance.preset) : ''}`} style={style === 'ribbon' ? { borderLeftColor: appearance.accent_color } : undefined}>
                            <span className="text-[10px] font-black uppercase tracking-[0.2em]" style={{ color: appearance.accent_color, zoom: textZoom }}>Patrocínio</span>
                            {content.image && <img src={content.image} alt={current.name} className={content.wide ? 'max-h-28 max-w-[34rem] object-contain' : 'max-h-24 max-w-[20rem] object-contain'} />}
                            {content.name && <span className={`font-black ${style === 'clean' ? 'text-white' : 'text-slate-900'} ${content.image ? 'text-lg' : 'text-4xl'}`} style={{ zoom: textZoom }}>{content.name}</span>}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
