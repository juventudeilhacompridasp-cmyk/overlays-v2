import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { nextSponsorIndex, overlayMotion, overlayOrigin, overlayPosition, overlayStyle, sponsorFrameClass } from '@/lib/overlayVisual';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Rotação de logotipos dos patrocinadores: um por vez, troca por opacidade (barata para o Chromium do OBS). */
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
    const current = enabled && count > 0 ? items[index % count] : null;

    return (
        <div className={`fixed inset-0 flex p-8 font-sans ${overlayPosition(appearance.position)}`} style={{ ...overlayStyle(appearance), color: appearance.text_color }} data-testid="overlay-sponsors" data-visible={current !== null}>
            <Head title="Patrocinadores" />
            {current && (
                <div style={{ transform: `scale(${appearance.scale})`, transformOrigin: overlayOrigin(appearance.position) }}>
                    <div key={current.logo_url} className={overlayMotion(appearance.motion === 'dynamic' ? 'subtle' : appearance.motion)}>
                        <div className={`flex flex-col items-center gap-1 bg-white/95 px-6 py-3 shadow-xl ${sponsorFrameClass(appearance.preset)}`}>
                            <span className="text-[10px] font-black uppercase tracking-[0.2em]" style={{ color: appearance.accent_color }}>Patrocínio</span>
                            <img src={current.logo_url} alt={current.name} className="max-h-24 max-w-[20rem] object-contain" />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
