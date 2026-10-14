import { Realtime } from '@/lib/echo';
import { OverlayState, useOverlay } from '@/lib/overlay';
import { nextSponsorIndex, overlayStyle } from '@/lib/overlayVisual';
import { animationMs, barFrame, barItems, BarLook } from '@/lib/sponsorLook';
import { Head } from '@inertiajs/react';
import { CSSProperties, useEffect, useState } from 'react';

/** Barra de patrocinadores 1500 x 200: um banner por vez, na posição e moldura escolhidas, só no ar quando a tela está ligada. */
export default function SponsorBar({ token, state: initial, realtime }: { token: string; state: OverlayState; realtime: Realtime }) {
    const { state } = useOverlay(token, initial, realtime);
    const live = state.screens.sponsor_bar === true;
    const items = barItems(state.sponsors.items);
    const look: BarLook = {
        bar_seconds: state.sponsors.bar_seconds ?? 10, bar_transition: state.sponsors.bar_transition ?? 'fade', bar_fit: state.sponsors.bar_fit ?? 'cover', bar_border: state.sponsors.bar_border ?? 'thin', bar_shadow: state.sponsors.bar_shadow ?? 'none',
        bar_scale: state.sponsors.bar_scale ?? 100, bar_x: state.sponsors.bar_x ?? 50, bar_y: state.sponsors.bar_y ?? 91, bar_opacity: state.sponsors.bar_opacity ?? 100, bar_radius: state.sponsors.bar_radius ?? 0, bar_background: state.sponsors.bar_background ?? '#08090d',
    };
    const [index, setIndex] = useState(0);
    const count = items.length;

    useEffect(() => {
        if (count < 2) return;
        const timer = window.setInterval(() => setIndex((current) => nextSponsorIndex(current, count)), look.bar_seconds * 1000);

        return () => window.clearInterval(timer);
    }, [count, look.bar_seconds]);

    const current = live && count > 0 ? items[index % count] : null;
    const image = current ? current.banner_url ?? current.logo_url : null;
    const appearance = state.appearance;
    const wideArt = Boolean(current?.banner_url);

    return (
        <div className="fixed inset-0 font-sans" style={{ ...overlayStyle(appearance), color: appearance.text_color }} data-testid="overlay-sponsor-bar" data-visible={current !== null}>
            <Head title="Barra de patrocinadores" />
            {current && image && (
                <div style={{ position: 'absolute', left: `${look.bar_x}%`, top: `${look.bar_y}%`, transform: `translate(-50%, -50%) scale(${look.bar_scale / 100})` }}>
                    <div key={image} className={appearance.motion === 'none' ? '' : `sponsor-in-${look.bar_transition}`} style={{ ...barFrame(look, appearance.accent_color), ['--sponsor-ms' as string]: `${animationMs(100, 700)}ms` } as CSSProperties}>
                        <div className="grid h-full w-full place-items-center">
                            <img src={image} alt={current.name} className="h-full w-full" style={{ objectFit: wideArt ? look.bar_fit : 'contain', padding: wideArt ? 0 : 12 }} />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
