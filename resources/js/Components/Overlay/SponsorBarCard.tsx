import { OverlayAppearance, OverlayState } from '@/lib/overlay';
import { nextSponsorIndex } from '@/lib/overlayVisual';
import { animationMs, barFrame, barItems, BarLook } from '@/lib/sponsorLook';
import { CSSProperties, useEffect, useState } from 'react';

/** Aparência da barra a partir dos patrocinadores do estado (com os mesmos padrões da tela `barra-patrocinio`). */
export function barLookOf(sponsors: OverlayState['sponsors']): BarLook {
    return {
        bar_seconds: sponsors.bar_seconds ?? 10, bar_transition: sponsors.bar_transition ?? 'fade', bar_fit: sponsors.bar_fit ?? 'cover', bar_border: sponsors.bar_border ?? 'thin', bar_shadow: sponsors.bar_shadow ?? 'none',
        bar_scale: sponsors.bar_scale ?? 100, bar_x: sponsors.bar_x ?? 50, bar_y: sponsors.bar_y ?? 91, bar_opacity: sponsors.bar_opacity ?? 100, bar_radius: sponsors.bar_radius ?? 0, bar_background: sponsors.bar_background ?? '#08090d',
    };
}

/**
 * Barra de patrocinadores de 1500 x 200: UM banner por vez, passando por todos em loop (`bar_seconds`, `bar_transition`);
 * patrocinador sem banner mostra o logotipo centralizado. `scale` (em %) só reduz ou amplia o conjunto, sem mudar a proporção.
 * Usada na tela `barra-patrocinio` e fixa por baixo da apresentação da escalação.
 */
export function SponsorBarCard({ sponsors, appearance, scale = 100, testId = 'sponsor-bar-card' }: { sponsors: OverlayState['sponsors']; appearance: OverlayAppearance; scale?: number; testId?: string }) {
    const look = barLookOf(sponsors);
    const items = barItems(sponsors.items);
    const count = items.length;
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (count < 2) return;
        const timer = window.setInterval(() => setIndex((current) => nextSponsorIndex(current, count)), look.bar_seconds * 1000);

        return () => window.clearInterval(timer);
    }, [count, look.bar_seconds]);

    if (count === 0) return null;

    const current = items[index % count];
    const image = current.banner_url ?? current.logo_url;
    const wide = Boolean(current.banner_url);

    return (
        <div style={scale === 100 ? undefined : { zoom: scale / 100 }} data-testid={testId} data-count={count} data-index={index % count}>
            <div key={image} className={appearance.motion === 'none' ? '' : `sponsor-in-${look.bar_transition}`} style={{ ...barFrame(look, appearance.accent_color), ['--sponsor-ms' as string]: `${animationMs(100, 700)}ms` } as CSSProperties}>
                <div className="grid h-full w-full place-items-center">
                    <img src={image} alt={current.name} className="h-full w-full" style={{ objectFit: wide ? look.bar_fit : 'contain', padding: wide ? 0 : 12 }} />
                </div>
            </div>
        </div>
    );
}
